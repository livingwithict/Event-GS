<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class DiagnoseAuth extends Command
{
    protected $signature = 'auth:diagnose {email=admin@ictfoundation.org.np} {password=password} {--attempt : Actually try logging in via HTTP}';

    protected $description = 'Diagnose authentication issues — checks user exists, password hash, rate limit, and live login attempt';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $this->info("\n=== Authentication Diagnostic ===\n");

        // 1. User lookup
        $this->line('[1] User lookup');
        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("    User NOT FOUND: {$email}");
            $this->line('    Run: php artisan db:seed --class=RoleSeeder');

            return self::FAILURE;
        }
        $this->line("    Found user: {$user->name} (ID {$user->id}, role={$user->role})");

        // 2. Password hash check
        $this->line('[2] Password hash check');
        $this->line('    Hash algorithm: '.$this->detectHashAlgo($user->password));
        $this->line("    Provided password: \"{$password}\"");
        $match = Hash::check($password, $user->password);
        $this->line('    Match: '.($match ? '<fg=green>YES</>' : '<fg=red>NO</>'));
        if (! $match) {
            $this->line('    Hashed version: '.Hash::make($password));
            $this->line('    Stored hash: '.substr($user->password, 0, 30).'...');
        }

        // 3. Role check
        $this->line('[3] Role check for admin panel');
        $allowed = ['super_admin', 'admin', 'manager', 'finance'];
        $roleOk = in_array($user->role, $allowed);
        $this->line("    Role: {$user->role}");
        $this->line('    In allowed list ['.implode(', ', $allowed).']: '.($roleOk ? '<fg=green>YES</>' : '<fg=red>NO</>'));

        // 4. Rate limit check
        $this->line('[4] Rate limit check');
        $throttleKey = strtolower($email).'|127.0.0.1';
        $tooMany = RateLimiter::tooManyAttempts($throttleKey, 5);
        $remaining = RateLimiter::remaining($throttleKey, 5);
        $this->line("    Throttle key: {$throttleKey}");
        $this->line('    Too many attempts: '.($tooMany ? '<fg=red>YES (LOCKED)</>' : '<fg=green>NO</>'));
        $this->line("    Remaining attempts: {$remaining}");

        // 5. Session driver check
        $this->line('[5] Session driver');
        $this->line('    Driver: '.config('session.driver'));
        $this->line('    Lifetime: '.config('session.lifetime').' min');

        // 6. Auth driver check
        $this->line('[6] Auth driver');
        $this->line('    Default guard: '.config('auth.defaults.guard'));
        $this->line('    Web provider: '.config('auth.providers.users.driver'));
        $this->line('    Users model: '.config('auth.providers.users.model'));

        // 7. Live HTTP login attempt
        if ($this->option('attempt')) {
            $this->line('[7] Live HTTP login attempt');
            $url = config('app.url').'/admin/login';

            // First, get the login page to extract CSRF + cookies
            $cookies = [];
            $response = Http::withOptions(['verify' => false])
                ->get($url);
            foreach ($response->cookies() as $cookie) {
                $cookies[$cookie->getName()] = $cookie->getValue();
            }
            $csrf = $this->extractCsrf($response->body());
            $this->line("    GET {$url} → HTTP {$response->status()}, CSRF: ".($csrf ? 'found' : 'missing'));

            if (! $csrf) {
                $this->error('    Cannot proceed without CSRF token');

                return self::FAILURE;
            }

            // Capture auth events
            $loginEventFired = false;
            $failedEventFired = false;
            Event::listen(Login::class, function () use (&$loginEventFired) {
                $loginEventFired = true;
            });
            Event::listen(Failed::class, function () use (&$failedEventFired) {
                $failedEventFired = true;
            });

            // Submit login
            $response = Http::withOptions(['verify' => false])
                ->withCookies($cookies, parse_url($url, PHP_URL_HOST))
                ->withHeaders([
                    'X-CSRF-TOKEN' => $csrf,
                    'X-Livewire' => '1',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Referer' => $url,
                ])
                ->asForm()
                ->post($url, [
                    'email' => $email,
                    'password' => $password,
                ]);

            $body = $response->json() ?: [];
            $this->line("    POST {$url} → HTTP {$response->status()}");
            if (isset($body['components'][0]['effects']['redirect'])) {
                $this->line('    Server says: redirect to '.$body['components'][0]['effects']['redirect']);
            }
            $this->line('    Login event fired: '.($loginEventFired ? '<fg=green>YES</>' : '<fg=red>NO</>'));
            $this->line('    Failed event fired: '.($failedEventFired ? '<fg=red>YES</>' : '<fg=green>NO</>'));

            if ($response->status() === 200 && $loginEventFired) {
                $this->info('    <fg=green>✓ Login would succeed in browser</>');
            } elseif ($failedEventFired) {
                $this->error('    ✗ Login would FAIL — check password or rate limit');
            } else {
                $this->warn('    ? Inconclusive — check auth.log for details');
            }
        } else {
            $this->line("\n[7] Skipped live attempt (use --attempt to run)");
        }

        $this->line("\nAuth log: ".storage_path('logs/auth.log'));
        $this->line("Run with --attempt to do a live HTTP login test.\n");

        return self::SUCCESS;
    }

    private function detectHashAlgo(string $hash): string
    {
        $info = password_get_info($hash);

        return $info['algoName'] ?? 'unknown';
    }

    private function extractCsrf(string $html): ?string
    {
        if (preg_match('/name="csrf-token"\s+content="([^"]+)"/', $html, $m)) {
            return $m[1];
        }
        if (preg_match('/csrfToken:\s*["\']([^"\']+)["\']/', $html, $m)) {
            return $m[1];
        }
        if (preg_match('/_token["\']\s*:\s*["\']([^"\']+)["\']/', $html, $m)) {
            return $m[1];
        }

        return null;
    }
}
