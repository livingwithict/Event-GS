# Event Management System — Full Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a centralized event management system for ICT Foundation Nepal with QR-based scanning, meal validation, bulk communications, and reporting.

**Architecture:** Laravel 11 backend with FilamentPHP admin panel, PostgreSQL database, Redis for caching/queues, and a separate React PWA for on-site QR scanning. API-first design with Sanctum token auth. Event-driven internals using Laravel events/listeners.

**Tech Stack:** PHP 8.2+, Laravel 11, FilamentPHP 3, PostgreSQL 15, Redis 7, React 18, Vite, html5-qrcode, Laravel Sail (Docker), Laravel Horizon, Maatwebsite Excel

---

## File Structure Map

```
event-management/
├── app/
│   ├── Models/
│   │   ├── User.php
│   │   ├── Event.php
│   │   ├── Registration.php
│   │   └── Communication.php
│   ├── DTOs/
│   │   ├── ScanResponseDTO.php
│   │   └── ImportResultDTO.php
│   ├── Events/
│   │   ├── EntryRecorded.php
│   │   ├── MealUsed.php
│   │   └── RegistrationsImported.php
│   ├── Listeners/
│   │   ├── UpdateRedisCache.php
│   │   ├── LogMealUsage.php
│   │   └── PreloadRedisCache.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── ScanController.php
│   │   │   │   ├── EntryController.php
│   │   │   │   ├── MealController.php
│   │   │   │   ├── GuestSearchController.php
│   │   │   │   ├── EventDashboardController.php
│   │   │   │   └── ReportController.php
│   │   │   └── ImportController.php
│   │   ├── Requests/
│   │   │   ├── StoreEventRequest.php
│   │   │   ├── UpdateEventRequest.php
│   │   │   ├── ImportRegistrationsRequest.php
│   │   │   ├── ScanRequest.php
│   │   │   ├── EntryRequest.php
│   │   │   └── MealRequest.php
│   │   ├── Middleware/
│   │   │   └── EnsureRole.php
│   │   └── Resources/
│   │       ├── ScanResponseResource.php
│   │       └── EventDashboardResource.php
│   ├── Policies/
│   │   └── EventPolicy.php
│   ├── Services/
│   │   ├── QRCodeService.php
│   │   ├── ImportService.php
│   │   ├── CommunicationService.php
│   │   └── ReportService.php
│   ├── Jobs/
│   │   ├── GenerateQRCodes.php
│   │   ├── SendBulkEmail.php
│   │   ├── SendBulkSMS.php
│   │   └── UpdateRegistrationFromScan.php
│   ├── Imports/
│   │   └── RegistrationsImport.php
│   ├── Exports/
│   │   ├── AttendanceExport.php
│   │   └── NoShowExport.php
│   └── Filament/
│       └── Resources/
│           ├── UserResource.php
│           ├── EventResource.php
│           ├── RegistrationResource.php
│           └── CommunicationResource.php
├── database/
│   ├── migrations/
│   │   ├── 0001_create_users_table.php
│   │   ├── 0002_create_events_table.php
│   │   ├── 0003_create_registrations_table.php
│   │   ├── 0004_create_communications_table.php
│   │   └── 0005_create_audit_logs_table.php
│   ├── seeders/
│   │   ├── RoleSeeder.php
│   │   └── AdminUserSeeder.php
│   └── factories/
│       ├── UserFactory.php
│       ├── EventFactory.php
│       └── RegistrationFactory.php
├── config/
│   ├── horizon.php
│   └── scanning.php
├── routes/
│   ├── api.php
│   └── web.php
├── resources/
│   ├── views/
│   │   └── emails/
│   │       └── invitation.blade.php
│   └── js/  (scanner PWA — separate build)
├── tests/
│   ├── Feature/
│   │   ├── AuthTest.php
│   │   ├── EventManagementTest.php
│   │   ├── RegistrationImportTest.php
│   │   ├── ScanTest.php
│   │   ├── EntryTest.php
│   │   ├── MealTest.php
│   │   ├── CommunicationTest.php
│   │   └── ReportTest.php
│   └── Unit/
│       ├── Services/
│       │   ├── QRCodeServiceTest.php
│       │   ├── ImportServiceTest.php
│       │   └── CommunicationServiceTest.php
│       └── DTOs/
│           └── ScanResponseDTOTest.php
├── scanner-app/              # React PWA — separate project
│   ├── src/
│   │   ├── App.jsx
│   │   ├── components/
│   │   │   ├── QrScanner.jsx
│   │   │   ├── GuestCard.jsx
│   │   │   ├── ActionButtons.jsx
│   │   │   └── SearchFallback.jsx
│   │   ├── hooks/
│   │   │   ├── useApi.js
│   │   │   └── useAuth.js
│   │   ├── pages/
│   │   │   ├── Login.jsx
│   │   │   ├── Scanner.jsx
│   │   │   └── Dashboard.jsx
│   │   └── utils/
│   │       └── api.js
│   ├── public/
│   │   └── manifest.json
│   ├── vite.config.js
│   └── package.json
├── docker-compose.yml
├── Dockerfile
├── nginx.conf
└── .env.example
```

---

## Phase 1: Project Foundation & Database

### Task 1: Scaffold Laravel Project with Docker

**Files:**
- Create: `docker-compose.yml`
- Create: `Dockerfile`
- Create: `.env.example`
- Modify: `composer.json` (after create-project)

- [ ] **Step 1: Create Laravel project via Composer**

```bash
cd /Users/manojghale/Documents/Projects/event-management
composer create-project laravel/laravel . --prefer-dist
```

Expected: Laravel 11 project scaffolded in current directory.

- [ ] **Step 2: Create `.env.example`**

```env
APP_NAME="Event Management"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=event_management
DB_USERNAME=sail
DB_PASSWORD=password

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis

MAIL_MAILER=mailgun
MAILGUN_DOMAIN=
MAILGUN_SECRET=
MAIL_FROM_ADDRESS="events@ictfoundation.org.np"
MAIL_FROM_NAME="${APP_NAME}"

SMS_DRIVER=sparrow
SPARROW_SMS_TOKEN=
SPARROW_SMS_FROM=

SANCTUM_STATEFUL_DOMAINS=localhost:5173
```

- [ ] **Step 3: Create `Dockerfile`**

```dockerfile
FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip libpq-dev \
    && docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html
```

- [ ] **Step 4: Create `docker-compose.yml`**

```yaml
version: '3.8'
services:
  app:
    build: .
    ports:
      - "8000:8000"
    environment:
      - DB_HOST=postgres
      - REDIS_HOST=redis
    depends_on:
      - postgres
      - redis
    volumes:
      - .:/var/www/html

  queue:
    build: .
    command: php artisan horizon
    depends_on:
      - redis
    volumes:
      - .:/var/www/html

  postgres:
    image: postgres:15
    environment:
      POSTGRES_DB: event_management
      POSTGRES_USER: sail
      POSTGRES_PASSWORD: password
    ports:
      - "5432:5432"
    volumes:
      - pgdata:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine
    command: redis-server --appendonly yes
    ports:
      - "6379:6379"

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app

volumes:
  pgdata:
```

- [ ] **Step 5: Create `nginx.conf`**

```nginx
server {
    listen 80;
    index index.php index.html;
    server_name localhost;
    root /var/www/html/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

- [ ] **Step 6: Install core Composer dependencies**

```bash
composer require filament/filament:"^3.2"
composer require laravel/sanctum
composer require predis/predis
composer require laravel/horizon
composer require maatwebsite/excel
composer require simplesoftwareio/simple-qrcode
composer require spatie/laravel-activitylog
composer require spatie/laravel-permission
```

- [ ] **Step 7: Publish vendor configs**

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan horizon:install
```

- [ ] **Step 8: Verify setup runs**

```bash
cp .env.example .env
php artisan key:generate
php artisan config:cache
```

Expected: No errors, `storage/framework/` dirs writable.

- [ ] **Step 9: Commit**

```bash
git init
git add -A
git commit -m "chore: scaffold Laravel 11 project with Docker, core dependencies"
```

---

### Task 2: Database Migrations — Users, Events, Registrations, Communications

**Files:**
- Create: `database/migrations/0001_01_01_000000_create_users_table.php` (modify existing)
- Create: `database/migrations/xxxx_create_events_table.php`
- Create: `database/migrations/xxxx_create_registrations_table.php`
- Create: `database/migrations/xxxx_create_communications_table.php`
- Create: `database/migrations/xxxx_add_role_to_users_table.php`

- [ ] **Step 1: Write migration for Users table (modify default Laravel migration)**

Replace the default users migration with:

```php
// database/migrations/0001_01_01_000000_create_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('scanner');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

- [ ] **Step 2: Create events migration**

```php
// database/migrations/xxxx_create_events_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->date('event_date');
            $table->text('venue')->nullable();
            $table->jsonb('meal_types')->default('["lunch","dinner"]');
            $table->integer('max_capacity')->nullable();
            $table->jsonb('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('event_date');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
```

- [ ] **Step 3: Create registrations migration**

```php
// database/migrations/xxxx_create_registrations_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->uuid('unique_code')->unique();
            $table->string('qr_hash')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('organization')->nullable();
            $table->string('designation')->nullable();
            $table->text('address')->nullable();
            $table->string('website')->nullable();
            $table->timestamp('entry_time')->nullable();
            $table->timestamp('lunch_used_at')->nullable();
            $table->timestamp('dinner_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('event_id');
            $table->index('email');
            $table->index('phone');
            $table->index('organization');
            $table->index('qr_hash');
            $table->index('entry_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
```

- [ ] **Step 4: Create communications migration**

```php
// database/migrations/xxxx_create_communications_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 50)->default('pending');
            $table->string('provider_message_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['registration_id']);
            $table->index('sent_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
```

- [ ] **Step 5: Run migrations and verify**

```bash
php artisan migrate
```

Expected: All tables created — `users`, `events`, `registrations`, `communications`, `activity_log`, plus Laravel defaults.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/
git commit -m "feat: add database migrations for users, events, registrations, communications"
```

---

### Task 3: Eloquent Models with Relationships, Scopes, and Traits

**Files:**
- Create: `app/Models/User.php`
- Create: `app/Models/Event.php`
- Create: `app/Models/Registration.php`
- Create: `app/Models/Communication.php`

- [ ] **Step 1: Write failing test for User model roles**

```php
// tests/Unit/Models/UserModelTest.php
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_default_scanner_role(): void
    {
        $user = User::factory()->create();
        $this->assertEquals('scanner', $user->role);
    }

    public function test_user_role_check_methods(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $manager = User::factory()->create(['role' => 'event_manager']);
        $scanner = User::factory()->create(['role' => 'scanner']);
        $viewer = User::factory()->create(['role' => 'viewer']);

        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue($manager->isEventManager());
        $this->assertTrue($scanner->isScanner());
        $this->assertTrue($viewer->isViewer());
    }

    public function test_user_can_be_scoped_by_role(): void
    {
        User::factory()->count(2)->create(['role' => 'scanner']);
        User::factory()->create(['role' => 'super_admin']);

        $this->assertCount(2, User::withRole('scanner')->get());
    }
}
```

```bash
php artisan test tests/Unit/Models/UserModelTest.php
```

Expected: FAIL — User factory and model methods not yet defined.

- [ ] **Step 2: Create User model**

```php
// app/Models/User.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, LogsActivity;

    protected $fillable = ['name', 'email', 'password', 'role'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'email', 'role'])->logOnlyDirty();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isEventManager(): bool
    {
        return $this->role === 'event_manager';
    }

    public function isScanner(): bool
    {
        return $this->role === 'scanner';
    }

    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }

    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'created_by');
    }
}
```

- [ ] **Step 3: Create UserFactory**

```php
// database/factories/UserFactory.php
namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => 'scanner',
            'remember_token' => Str::random(10),
        ];
    }
}
```

- [ ] **Step 4: Run User test — verify it passes**

```bash
php artisan test tests/Unit/Models/UserModelTest.php
```

Expected: PASS

- [ ] **Step 5: Write failing test for Event model**

```php
// tests/Unit/Models/EventModelTest.php
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_generates_slug_from_name(): void
    {
        $event = Event::factory()->create(['name' => 'ICT Conference 2025']);
        $this->assertEquals('ict-conference-2025', $event->slug);
    }

    public function test_event_has_many_registrations(): void
    {
        $event = Event::factory()->create();
        Registration::factory()->count(3)->create(['event_id' => $event->id]);

        $this->assertCount(3, $event->fresh()->registrations);
    }

    public function test_event_meal_types_cast_to_array(): void
    {
        $event = Event::factory()->create(['meal_types' => ['lunch', 'dinner']]);
        $this->assertEquals(['lunch', 'dinner'], $event->meal_types);
    }

    public function test_event_stats_method(): void
    {
        $event = Event::factory()->create();
        Registration::factory()->count(10)->create(['event_id' => $event->id]);
        Registration::factory()->count(3)->create([
            'event_id' => $event->id,
            'entry_time' => now(),
        ]);

        $stats = $event->getStats();
        $this->assertEquals(13, $stats['total_registrations']);
        $this->assertEquals(3, $stats['total_entries']);
    }
}
```

```bash
php artisan test tests/Unit/Models/EventModelTest.php
```

Expected: FAIL

- [ ] **Step 6: Create Event model**

```php
// app/Models/Event.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Event extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name', 'slug', 'event_date', 'venue',
        'meal_types', 'max_capacity', 'settings', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'meal_types' => 'array',
            'settings' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'event_date', 'venue'])->logOnlyDirty();
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (empty($event->slug)) {
                $event->slug = Str::slug($event->name);
            }
        });
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStats(): array
    {
        return [
            'total_registrations' => $this->registrations()->count(),
            'total_entries' => $this->registrations()->whereNotNull('entry_time')->count(),
            'lunch_used' => $this->registrations()->whereNotNull('lunch_used_at')->count(),
            'dinner_used' => $this->registrations()->whereNotNull('dinner_used_at')->count(),
        ];
    }
}
```

- [ ] **Step 7: Create EventFactory**

```php
// database/factories/EventFactory.php
namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Conference ' . fake()->year(),
            'event_date' => fake()->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'venue' => fake()->address(),
            'meal_types' => ['lunch', 'dinner'],
            'max_capacity' => fake()->optional()->numberBetween(100, 10000),
            'created_by' => User::factory(),
        ];
    }
}
```

- [ ] **Step 8: Run Event test — verify it passes**

```bash
php artisan test tests/Unit/Models/EventModelTest.php
```

Expected: PASS

- [ ] **Step 9: Write failing test for Registration model**

```php
// tests/Unit/Models/RegistrationModelTest.php
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_generates_uuid_and_qr_hash_on_create(): void
    {
        $reg = Registration::factory()->create();
        $this->assertNotNull($reg->unique_code);
        $this->assertNotEmpty($reg->qr_hash);
        $this->assertEquals(36, strlen($reg->unique_code));
    }

    public function test_registration_qr_hash_is_deterministic(): void
    {
        $reg = Registration::factory()->create();
        $expected = hash_hmac('sha256', $reg->unique_code, config('app.key'));
        $this->assertEquals($expected, $reg->qr_hash);
    }

    public function test_has_entered_attribute(): void
    {
        $entered = Registration::factory()->create(['entry_time' => now()]);
        $notEntered = Registration::factory()->create(['entry_time' => null]);

        $this->assertTrue($entered->hasEntered());
        $this->assertFalse($notEntered->hasEntered());
    }

    public function test_meal_used_methods(): void
    {
        $reg = Registration::factory()->create([
            'lunch_used_at' => now(),
            'dinner_used_at' => null,
        ]);

        $this->assertTrue($reg->hasUsedMeal('lunch'));
        $this->assertFalse($reg->hasUsedMeal('dinner'));
    }

    public function test_record_entry_is_idempotent(): void
    {
        $reg = Registration::factory()->create(['entry_time' => null]);
        $first = $reg->recordEntry();
        $second = $reg->recordEntry();

        $this->assertTrue($first);
        $this->assertFalse($second);
        $this->assertNotNull($reg->fresh()->entry_time);
    }

    public function test_record_meal_is_idempotent(): void
    {
        $reg = Registration::factory()->create(['lunch_used_at' => null]);
        $first = $reg->recordMeal('lunch');
        $second = $reg->recordMeal('lunch');

        $this->assertTrue($first);
        $this->assertFalse($second);
        $this->assertNotNull($reg->fresh()->lunch_used_at);
    }
}
```

```bash
php artisan test tests/Unit/Models/RegistrationModelTest.php
```

Expected: FAIL

- [ ] **Step 10: Create Registration model**

```php
// app/Models/Registration.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Registration extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'event_id', 'unique_code', 'qr_hash', 'name', 'email', 'phone',
        'organization', 'designation', 'address', 'website',
        'entry_time', 'lunch_used_at', 'dinner_used_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_time' => 'datetime',
            'lunch_used_at' => 'datetime',
            'dinner_used_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'email', 'organization', 'entry_time'])->logOnlyDirty();
    }

    protected static function booted(): void
    {
        static::creating(function (Registration $reg) {
            if (empty($reg->unique_code)) {
                $reg->unique_code = (string) Str::uuid();
            }
            if (empty($reg->qr_hash)) {
                $reg->qr_hash = hash_hmac('sha256', $reg->unique_code, config('app.key'));
            }
        });
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function communications()
    {
        return $this->hasMany(Communication::class);
    }

    public function hasEntered(): bool
    {
        return $this->entry_time !== null;
    }

    public function hasUsedMeal(string $mealType): bool
    {
        return match ($mealType) {
            'lunch' => $this->lunch_used_at !== null,
            'dinner' => $this->dinner_used_at !== null,
            default => false,
        };
    }

    public function recordEntry(): bool
    {
        if ($this->hasEntered()) {
            return false;
        }
        $this->update(['entry_time' => now()]);
        return true;
    }

    public function recordMeal(string $mealType): bool
    {
        if ($this->hasUsedMeal($mealType)) {
            return false;
        }
        $this->update([match ($mealType) {
            'lunch' => 'lunch_used_at',
            'dinner' => 'dinner_used_at',
        } => now()]);
        return true;
    }
}
```

- [ ] **Step 11: Create RegistrationFactory**

```php
// database/factories/RegistrationFactory.php
namespace Database\Factories;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegistrationFactory extends Factory
{
    protected $model = Registration::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->name(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->numerify('+977##########'),
            'organization' => fake()->optional()->company(),
            'designation' => fake()->optional()->jobTitle(),
            'address' => fake()->optional()->address(),
            'website' => fake()->optional()->url(),
        ];
    }
}
```

- [ ] **Step 12: Run Registration test — verify it passes**

```bash
php artisan test tests/Unit/Models/RegistrationModelTest.php
```

Expected: PASS

- [ ] **Step 13: Create Communication model**

```php
// app/Models/Communication.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Communication extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_id', 'type', 'subject', 'content',
        'sent_at', 'status', 'provider_message_id', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function scopeEmail($query)
    {
        return $query->where('type', 'email');
    }

    public function scopeSms($query)
    {
        return $query->where('type', 'sms');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function markSent(string $providerId = null): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'provider_message_id' => $providerId,
        ]);
    }

    public function markFailed(array $metadata = []): void
    {
        $this->update([
            'status' => 'failed',
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
    }
}
```

- [ ] **Step 14: Run all model tests**

```bash
php artisan test tests/Unit/Models/
```

Expected: All PASS

- [ ] **Step 15: Commit**

```bash
git add app/Models/ database/factories/ tests/Unit/Models/
git commit -m "feat: add Eloquent models with relationships, factory methods, and idempotent scan logic"
```

---

## Phase 2: Authentication & Role-Based Access

### Task 4: Sanctum API Authentication + Role Middleware

**Files:**
- Create: `app/Http/Controllers/Api/AuthController.php`
- Create: `app/Http/Middleware/EnsureRole.php`
- Modify: `bootstrap/app.php` (register middleware)
- Modify: `routes/api.php`
- Create: `database/seeders/RoleSeeder.php`

- [ ] **Step 1: Write failing auth tests**

```php
// tests/Feature/AuthTest.php
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_token(): void
    {
        $user = User::factory()->create([
            'email' => 'scanner@test.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'scanner@test.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'test@test.com', 'password' => bcrypt('password')]);

        $this->postJson('/api/login', [
            'email' => 'test@test.com',
            'password' => 'wrong',
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_scanner_cannot_access_manager_endpoints(): void
    {
        $scanner = User::factory()->create(['role' => 'scanner']);

        $this->actingAs($scanner)
            ->postJson('/api/event/1/import', [])
            ->assertForbidden();
    }

    public function test_manager_can_access_import_endpoint(): void
    {
        $manager = User::factory()->create(['role' => 'event_manager']);
        $event = \App\Models\Event::factory()->create();

        $this->actingAs($manager)
            ->postJson("/api/event/{$event->id}/import", [])
            ->assertStatus(422); // Validation error, not 403
    }
}
```

```bash
php artisan test tests/Feature/AuthTest.php
```

Expected: FAIL

- [ ] **Step 2: Create AuthController**

```php
// app/Http/Controllers/Api/AuthController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
```

- [ ] **Step 3: Create EnsureRole middleware**

```php
// app/Http/Middleware/EnsureRole.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! in_array($request->user()?->role, $roles)) {
            abort(403, 'Insufficient permissions.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Register middleware and routes**

```php
// bootstrap/app.php — add middleware alias
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \App\Http\Middleware\EnsureRole::class,
    ]);
})
```

```php
// routes/api.php
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Scanner endpoints
    Route::middleware('role:scanner,event_manager,super_admin')->group(function () {
        Route::post('/scan', [App\Http\Controllers\Api\ScanController::class, 'scan']);
        Route::post('/entry', [App\Http\Controllers\Api\EntryController::class, 'record']);
        Route::post('/meal', [App\Http\Controllers\Api\MealController::class, 'record']);
        Route::get('/guest/search', [App\Http\Controllers\Api\GuestSearchController::class, 'search']);
    });

    // Manager+ endpoints
    Route::middleware('role:event_manager,super_admin')->group(function () {
        Route::get('/event/{event}/dashboard', [App\Http\Controllers\Api\EventDashboardController::class, 'show']);
        Route::post('/event/{event}/import', [App\Http\Controllers\ImportController::class, 'import']);
        Route::post('/event/{event}/send-invites', [App\Http\Controllers\Api\CommunicationController::class, 'sendInvites']);
        Route::get('/reports/attendance/{event}', [App\Http\Controllers\Api\ReportController::class, 'attendance']);
        Route::get('/reports/noshow/{event}', [App\Http\Controllers\Api\ReportController::class, 'noShow']);
    });
});
```

- [ ] **Step 5: Run auth tests — verify they pass**

```bash
php artisan test tests/Feature/AuthTest.php
```

Expected: PASS

- [ ] **Step 6: Create RoleSeeder**

```php
// database/seeders/RoleSeeder.php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@ictfoundation.org.np'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
            ]
        );
    }
}
```

```bash
php artisan db:seed --class=RoleSeeder
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/ app/Models/ bootstrap/ routes/ database/seeders/ tests/Feature/AuthTest.php
git commit -m "feat: add Sanctum API auth, role middleware, and route registration"
```

---

## Phase 3: Event & Registration Management (Filament Admin Panel)

### Task 5: Filament Admin Panel — Event CRUD

**Files:**
- Create: `app/Filament/Resources/EventResource.php`
- Create: `app/Filament/Resources/EventResource/Pages/ListEvents.php`
- Create: `app/Filament/Resources/EventResource/Pages/CreateEvent.php`
- Create: `app/Filament/Resources/EventResource/Pages/EditEvent.php`
- Create: `app/Filament/Resources/EventResource/RelationManagers/RegistrationsRelationManager.php`

- [ ] **Step 1: Install and configure Filament**

```bash
php artisan filament:install --panels
php artisan make:filament-user
```

Follow prompts to create an admin user. This creates `app/Providers/Filament/AdminPanelProvider.php`.

- [ ] **Step 2: Create EventResource**

```bash
php artisan make:filament-resource Event --generate
```

- [ ] **Step 3: Customize EventResource**

```php
// app/Filament/Resources/EventResource.php
namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Filament\Resources\EventResource\RelationManagers\RegistrationsRelationManager;
use App\Models\Event;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Event Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $state, Forms\Set $set) =>
                                $set('slug', \Str::slug($state))
                            ),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\DatePicker::make('event_date')
                            ->required(),
                        Forms\Components\Textarea::make('venue')
                            ->maxLength(65535),
                    ])->columns(2),

                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\CheckboxList::make('meal_types')
                            ->options(['lunch' => 'Lunch', 'dinner' => 'Dinner'])
                            ->default(['lunch', 'dinner'])
                            ->required(),
                        Forms\Components\TextInput::make('max_capacity')
                            ->numeric()
                            ->minValue(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('event_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('venue')->limit(30)->searchable(),
                Tables\Columns\TextColumn::make('registrations_count')
                    ->counts('registrations')
                    ->label('Registrations')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_date')
                    ->options(fn () => Event::pluck('event_date', 'event_date')->map->format('Y-m-d')->unique()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RegistrationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: Verify Filament panel loads**

```bash
php artisan serve
```

Navigate to `http://localhost:8000/admin` — login, verify Event CRUD appears and works.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/
git commit -m "feat: add Filament admin panel with Event CRUD resource"
```

---

### Task 6: Filament — Registration CRUD + Excel Import

**Files:**
- Create: `app/Filament/Resources/RegistrationResource.php`
- Create: `app/Filament/Resources/RegistrationResource/Pages/ListRegistrations.php`
- Create: `app/Filament/Resources/RegistrationResource/Pages/CreateRegistration.php`
- Create: `app/Filament/Resources/RegistrationResource/Pages/EditRegistration.php`
- Create: `app/Imports/RegistrationsImport.php`
- Create: `app/Http/Requests/ImportRegistrationsRequest.php`
- Create: `app/Services/ImportService.php`
- Create: `app/Http/Controllers/ImportController.php`

- [ ] **Step 1: Write failing test for Excel import**

```php
// tests/Feature/RegistrationImportTest.php
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RegistrationImportTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->create(['role' => 'event_manager']);
    }

    public function test_import_valid_excel_creates_registrations(): void
    {
        $event = Event::factory()->create();

        // Create a minimal xlsx file for testing
        $file = $this->createTestExcelFile([
            ['name', 'email', 'phone', 'organization', 'designation'],
            ['John Doe', 'john@test.com', '+9779800000001', 'ICT Foundation', 'Developer'],
            ['Jane Smith', 'jane@test.com', '+9779800000002', 'Tech Corp', 'Manager'],
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/event/{$event->id}/import", [
                'file' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('imported', 2)
            ->assertJsonPath('errors', []);

        $this->assertEquals(2, Registration::where('event_id', $event->id)->count());
    }

    public function test_import_detects_duplicates_within_event(): void
    {
        $event = Event::factory()->create();
        Registration::factory()->create([
            'event_id' => $event->id,
            'email' => 'duplicate@test.com',
        ]);

        $file = $this->createTestExcelFile([
            ['name', 'email', 'phone'],
            ['Duplicate Person', 'duplicate@test.com', '+9779800000003'],
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/event/{$event->id}/import", [
                'file' => $file,
            ]);

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('errors')));
    }

    public function test_import_validates_email_format(): void
    {
        $event = Event::factory()->create();

        $file = $this->createTestExcelFile([
            ['name', 'email', 'phone'],
            ['Bad Email', 'not-an-email', '+9779800000001'],
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/event/{$event->id}/import", [
                'file' => $file,
            ]);

        $response->assertOk();
        $this->assertEquals(0, $response->json('imported'));
        $this->assertGreaterThan(0, count($response->json('errors')));
    }

    public function test_import_requires_at_least_email_or_phone(): void
    {
        $event = Event::factory()->create();

        $file = $this->createTestExcelFile([
            ['name', 'email', 'phone'],
            ['No Contact', '', ''],
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/event/{$event->id}/import", [
                'file' => $file,
            ]);

        $response->assertOk();
        $this->assertEquals(0, $response->json('imported'));
    }

    private function createTestExcelFile(array $rows): UploadedFile
    {
        $temp = tempnam(sys_get_temp_dir(), 'test_') . '.xlsx';
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter(
            new \PhpOffice\PhpSpreadsheet\Spreadsheet(),
            'Xlsx'
        );

        $spreadsheet = $writer->getSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($rows as $row => $cols) {
            foreach ($cols as $col => $val) {
                $sheet->setCellValue(chr(65 + $col) . ($row + 1), $val);
            }
        }

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($temp);

        return new UploadedFile($temp, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
```

```bash
php artisan test tests/Feature/RegistrationImportTest.php
```

Expected: FAIL

- [ ] **Step 2: Create RegistrationsImport class**

```php
// app/Imports/RegistrationsImport.php
namespace App\Imports;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class RegistrationsImport implements ToCollection, WithHeadingRow
{
    private array $errors = [];
    private int $imported = 0;

    public function __construct(
        private Event $event,
        private bool $skipDuplicates = true,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $name = trim($row['name'] ?? '');
            $email = trim($row['email'] ?? '');
            $phone = trim($row['phone'] ?? '');

            if (empty($name)) {
                $this->errors[] = "Row {$rowNumber}: Name is required.";
                continue;
            }

            if (empty($email) && empty($phone)) {
                $this->errors[] = "Row {$rowNumber}: At least email or phone is required.";
                continue;
            }

            if (! empty($email) && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = "Row {$rowNumber}: Invalid email format.";
                continue;
            }

            if (! empty($phone) && ! preg_match('/^(\+977|0)?9\d{9}$/', $phone)) {
                $this->errors[] = "Row {$rowNumber}: Invalid Nepali phone number.";
                continue;
            }

            if ($this->skipDuplicates) {
                $dupeQuery = Registration::where('event_id', $this->event->id);
                if (! empty($email)) {
                    $dupeQuery->where('email', $email);
                } elseif (! empty($phone)) {
                    $dupeQuery->where('phone', $phone);
                }

                if ($dupeQuery->exists()) {
                    $this->errors[] = "Row {$rowNumber}: Duplicate registration ({$email}{$phone}).";
                    continue;
                }
            }

            Registration::create([
                'event_id' => $this->event->id,
                'name' => $name,
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'organization' => trim($row['organization'] ?? '') ?: null,
                'designation' => trim($row['designation'] ?? '') ?: null,
                'address' => trim($row['address'] ?? '') ?: null,
                'website' => trim($row['website'] ?? '') ?: null,
            ]);

            $this->imported++;
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getImportedCount(): int
    {
        return $this->imported;
    }
}
```

- [ ] **Step 3: Create ImportController**

```php
// app/Http/Controllers/ImportController.php
namespace App\Http\Controllers;

use App\Http\Requests\ImportRegistrationsRequest;
use App\Imports\RegistrationsImport;
use App\Models\Event;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    public function import(ImportRegistrationsRequest $request, Event $event)
    {
        $import = new RegistrationsImport($event);
        Excel::import($import, $request->file('file'));

        return response()->json([
            'imported' => $import->getImportedCount(),
            'errors' => $import->getErrors(),
        ]);
    }
}
```

- [ ] **Step 4: Create ImportRegistrationsRequest**

```php
// app/Http/Requests/ImportRegistrationsRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportRegistrationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ];
    }
}
```

- [ ] **Step 5: Run import tests — verify they pass**

```bash
composer require phpoffice/phpspreadsheet --dev
php artisan test tests/Feature/RegistrationImportTest.php
```

Expected: PASS

- [ ] **Step 6: Create RegistrationResource in Filament**

```bash
php artisan make:filament-resource Registration --generate
```

Then customize the generated file:

```php
// app/Filament/Resources/RegistrationResource.php
namespace App\Filament\Resources;

use App\Filament\Resources\RegistrationResource\Pages;
use App\Models\Registration;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RegistrationResource extends Resource
{
    protected static ?string $model = Registration::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('event_id')
                    ->relationship('event', 'name')
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('email')->email()->maxLength(255),
                Forms\Components\TextInput::make('phone')->tel()->maxLength(20),
                Forms\Components\TextInput::make('organization')->maxLength(255),
                Forms\Components\TextInput::make('designation')->maxLength(255),
                Forms\Components\Textarea::make('address')->maxLength(65535),
                Forms\Components\TextInput::make('website')->url()->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('organization')->searchable(),
                Tables\Columns\TextColumn::make('event.name')->sortable(),
                Tables\Columns\IconColumn::make('entry_time')
                    ->label('Entered')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                Tables\Columns\IconColumn::make('lunch_used_at')
                    ->label('Lunch')
                    ->boolean(),
                Tables\Columns\IconColumn::make('dinner_used_at')
                    ->label('Dinner')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->relationship('event', 'name')
                    ->label('Event'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ExportBulkAction::make()
                        ->exporter(\App\Filament\Resources\RegistrationResource\Exporters\RegistrationExporter::class),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrations::route('/'),
            'create' => Pages\CreateRegistration::route('/create'),
            'edit' => Pages\EditRegistration::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 7: Commit**

```bash
git add app/Filament/ app/Imports/ app/Http/ tests/Feature/RegistrationImportTest.php
git commit -m "feat: add Registration CRUD, Excel import with validation and duplicate detection"
```

---

## Phase 4: QR Code Generation & Scanning API

### Task 7: QR Code Service

**Files:**
- Create: `app/Services/QRCodeService.php`
- Create: `tests/Unit/Services/QRCodeServiceTest.php`

- [ ] **Step 1: Write failing test for QRCodeService**

```php
// tests/Unit/Services/QRCodeServiceTest.php
use App\Models\Event;
use App\Models\Registration;
use App\Services\QRCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QRCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private QRCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QRCodeService();
    }

    public function test_generate_qr_code_svg_for_registration(): void
    {
        $reg = Registration::factory()->create();

        $svg = $this->service->generateSvg($reg);

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString($reg->unique_code, $svg);
    }

    public function test_generate_qr_code_png_for_registration(): void
    {
        $reg = Registration::factory()->create();

        $png = $this->service->generatePng($reg);

        $this->assertStringStartsWith("\x89PNG", $png);
    }

    public function test_qr_payload_contains_signed_code(): void
    {
        $reg = Registration::factory()->create();

        $payload = $this->service->getPayload($reg);

        $this->assertEquals($reg->unique_code, $payload);
    }

    public function test_verify_valid_qr_code(): void
    {
        $reg = Registration::factory()->create();

        $found = $this->service->resolve($reg->unique_code);

        $this->assertNotNull($found);
        $this->assertEquals($reg->id, $found->id);
    }

    public function test_verify_invalid_qr_code_returns_null(): void
    {
        $found = $this->service->resolve('nonexistent-uuid');

        $this->assertNull($found);
    }
}
```

```bash
php artisan test tests/Unit/Services/QRCodeServiceTest.php
```

Expected: FAIL

- [ ] **Step 2: Create QRCodeService**

```php
// app/Services/QRCodeService.php
namespace App\Services;

use App\Models\Registration;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QRCodeService
{
    public function getPayload(Registration $registration): string
    {
        return $registration->unique_code;
    }

    public function generateSvg(Registration $registration): string
    {
        return QrCode::size(300)
            ->margin(2)
            ->generate($this->getPayload($registration));
    }

    public function generatePng(Registration $registration): string
    {
        return QrCode::format('png')
            ->size(300)
            ->margin(2)
            ->generate($this->getPayload($registration));
    }

    public function resolve(string $uniqueCode): ?Registration
    {
        return Registration::where('unique_code', $uniqueCode)->first();
    }
}
```

- [ ] **Step 3: Run QR service tests — verify they pass**

```bash
php artisan test tests/Unit/Services/QRCodeServiceTest.php
```

Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Services/QRCodeService.php tests/Unit/Services/QRCodeServiceTest.php
git commit -m "feat: add QR code generation and resolution service"
```

---

### Task 8: Scanning API Endpoints (Scan, Entry, Meal, Search)

**Files:**
- Create: `app/Http/Controllers/Api/ScanController.php`
- Create: `app/Http/Controllers/Api/EntryController.php`
- Create: `app/Http/Controllers/Api/MealController.php`
- Create: `app/Http/Controllers/Api/GuestSearchController.php`
- Create: `app/DTOs/ScanResponseDTO.php`
- Create: `app/Http/Resources/ScanResponseResource.php`
- Create: `app/Http/Requests/ScanRequest.php`
- Create: `app/Http/Requests/EntryRequest.php`
- Create: `app/Http/Requests/MealRequest.php`
- Create: `tests/Feature/ScanTest.php`
- Create: `tests/Feature/EntryTest.php`
- Create: `tests/Feature/MealTest.php`

- [ ] **Step 1: Write failing test for Scan endpoint**

```php
// tests/Feature/ScanTest.php
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanTest extends TestCase
{
    use RefreshDatabase;

    private User $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = User::factory()->create(['role' => 'scanner']);
    }

    public function test_scan_valid_qr_returns_guest_data(): void
    {
        $reg = Registration::factory()->create();

        $response = $this->actingAs($this->scanner)
            ->postJson('/api/scan', [
                'code' => $reg->unique_code,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', $reg->name)
            ->assertJsonPath('data.organization', $reg->organization)
            ->assertJsonPath('data.has_entered', false);
    }

    public function test_scan_invalid_code_returns_404(): void
    {
        $response = $this->actingAs($this->scanner)
            ->postJson('/api/scan', [
                'code' => '00000000-0000-0000-0000-000000000000',
            ]);

        $response->assertNotFound();
    }
}
```

- [ ] **Step 2: Write failing test for Entry endpoint**

```php
// tests/Feature/EntryTest.php
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryTest extends TestCase
{
    use RefreshDatabase;

    private User $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = User::factory()->create(['role' => 'scanner']);
    }

    public function test_record_entry_for_first_time(): void
    {
        $reg = Registration::factory()->create(['entry_time' => null]);

        $response = $this->actingAs($this->scanner)
            ->postJson('/api/entry', [
                'registration_id' => $reg->id,
            ]);

        $response->assertOk();
        $this->assertNotNull($reg->fresh()->entry_time);
    }

    public function test_record_entry_duplicate_returns_conflict(): void
    {
        $reg = Registration::factory()->create(['entry_time' => now()]);

        $response = $this->actingAs($this->scanner)
            ->postJson('/api/entry', [
                'registration_id' => $reg->id,
            ]);

        $response->assertStatus(409);
    }
}
```

- [ ] **Step 3: Write failing test for Meal endpoint**

```php
// tests/Feature/MealTest.php
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealTest extends TestCase
{
    use RefreshDatabase;

    private User $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = User::factory()->create(['role' => 'scanner']);
    }

    public function test_record_lunch_first_time(): void
    {
        $reg = Registration::factory()->create(['lunch_used_at' => null]);

        $response = $this->actingAs($this->scanner)
            ->postJson('/api/meal', [
                'registration_id' => $reg->id,
                'meal_type' => 'lunch',
            ]);

        $response->assertOk();
        $this->assertNotNull($reg->fresh()->lunch_used_at);
    }

    public function test_record_lunch_duplicate_returns_conflict(): void
    {
        $reg = Registration::factory()->create(['lunch_used_at' => now()]);

        $response = $this->actingAs($this->scanner)
            ->postJson('/api/meal', [
                'registration_id' => $reg->id,
                'meal_type' => 'lunch',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Lunch already recorded for this guest.');
    }

    public function test_invalid_meal_type_returns_422(): void
    {
        $reg = Registration::factory()->create();

        $response = $this->actingAs($this->scanner)
            ->postJson('/api/meal', [
                'registration_id' => $reg->id,
                'meal_type' => 'breakfast',
            ]);

        $response->assertStatus(422);
    }
}
```

- [ ] **Step 4: Run all three test files — verify they fail**

```bash
php artisan test tests/Feature/ScanTest.php tests/Feature/EntryTest.php tests/Feature/MealTest.php
```

Expected: FAIL

- [ ] **Step 5: Create ScanResponseDTO**

```php
// app/DTOs/ScanResponseDTO.php
namespace App\DTOs;

use App\Models\Registration;

readonly class ScanResponseDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $organization,
        public ?string $designation,
        public bool $hasEntered,
        public bool $lunchUsed,
        public bool $dinnerUsed,
        public ?string $entryTime,
        public ?string $lunchUsedAt,
        public ?string $dinnerUsedAt,
    ) {}

    public static function fromModel(Registration $reg): self
    {
        return new self(
            id: $reg->id,
            name: $reg->name,
            organization: $reg->organization,
            designation: $reg->designation,
            hasEntered: $reg->hasEntered(),
            lunchUsed: $reg->hasUsedMeal('lunch'),
            dinnerUsed: $reg->hasUsedMeal('dinner'),
            entryTime: $reg->entry_time?->toIso8601String(),
            lunchUsedAt: $reg->lunch_used_at?->toIso8601String(),
            dinnerUsedAt: $reg->dinner_used_at?->toIso8601String(),
        );
    }
}
```

- [ ] **Step 6: Create ScanResponseResource**

```php
// app/Http/Resources/ScanResponseResource.php
namespace App\Http\Resources;

use App\DTOs\ScanResponseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScanResponseResource extends JsonResource
{
    public function __construct(private ScanResponseDTO $dto)
    {
        parent::__construct($dto);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->dto->id,
            'name' => $this->dto->name,
            'organization' => $this->dto->organization,
            'designation' => $this->dto->designation,
            'has_entered' => $this->dto->hasEntered,
            'lunch_used' => $this->dto->lunchUsed,
            'dinner_used' => $this->dto->dinnerUsed,
            'entry_time' => $this->dto->entryTime,
            'lunch_used_at' => $this->dto->lunchUsedAt,
            'dinner_used_at' => $this->dto->dinnerUsedAt,
        ];
    }
}
```

- [ ] **Step 7: Create request validators**

```php
// app/Http/Requests/ScanRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['code' => 'required|string'];
    }
}
```

```php
// app/Http/Requests/EntryRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EntryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['registration_id' => 'required|exists:registrations,id'];
    }
}
```

```php
// app/Http/Requests/MealRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MealRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'registration_id' => 'required|exists:registrations,id',
            'meal_type' => 'required|in:lunch,dinner',
        ];
    }
}
```

- [ ] **Step 8: Create API controllers**

```php
// app/Http/Controllers/Api/ScanController.php
namespace App\Http\Controllers\Api;

use App\DTOs\ScanResponseDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScanRequest;
use App\Http\Resources\ScanResponseResource;
use App\Models\Registration;

class ScanController extends Controller
{
    public function scan(ScanRequest $request)
    {
        $reg = Registration::where('unique_code', $request->code)->first();

        if (! $reg) {
            return response()->json(['message' => 'Registration not found.'], 404);
        }

        return response()->json([
            'data' => new ScanResponseResource(ScanResponseDTO::fromModel($reg)),
        ]);
    }
}
```

```php
// app/Http/Controllers/Api/EntryController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EntryRequest;
use App\Models\Registration;

class EntryController extends Controller
{
    public function record(EntryRequest $request)
    {
        $reg = Registration::findOrFail($request->registration_id);

        if (! $reg->recordEntry()) {
            return response()->json(['message' => 'Entry already recorded.'], 409);
        }

        return response()->json(['message' => 'Entry recorded.']);
    }
}
```

```php
// app/Http/Controllers/Api/MealController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MealRequest;
use App\Models\Registration;

class MealController extends Controller
{
    public function record(MealRequest $request)
    {
        $reg = Registration::findOrFail($request->registration_id);
        $mealType = $request->meal_type;

        if (! $reg->recordMeal($mealType)) {
            $label = ucfirst($mealType);
            return response()->json(['message' => "{$label} already recorded for this guest."], 409);
        }

        return response()->json(['message' => ucfirst($mealType) . ' recorded.']);
    }
}
```

```php
// app/Http/Controllers/Api/GuestSearchController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use Illuminate\Http\Request;

class GuestSearchController extends Controller
{
    public function search(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2']);
        $query = $request->q;

        $results = Registration::where('name', 'ILIKE', "%{$query}%")
            ->orWhere('email', 'ILIKE', "%{$query}%")
            ->orWhere('phone', 'LIKE', "%{$query}%")
            ->limit(20)
            ->get();

        return response()->json(['data' => $results]);
    }
}
```

- [ ] **Step 9: Run all scan/entry/meal tests — verify they pass**

```bash
php artisan test tests/Feature/ScanTest.php tests/Feature/EntryTest.php tests/Feature/MealTest.php
```

Expected: PASS

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Api/ app/DTOs/ app/Http/Resources/ app/Http/Requests/ tests/Feature/ScanTest.php tests/Feature/EntryTest.php tests/Feature/MealTest.php
git commit -m "feat: add scan, entry, meal, and guest search API endpoints"
```

---

## Phase 5: Redis Caching Layer

### Task 9: Redis Cache for QR Validation & Event Stats

**Files:**
- Create: `app/Listeners/UpdateRedisCache.php`
- Create: `app/Listeners/PreloadRedisCache.php`
- Create: `app/Events/EntryRecorded.php`
- Create: `app/Events/MealUsed.php`
- Modify: `app/Http/Controllers/Api/ScanController.php`
- Modify: `app/Http/Controllers/Api/EntryController.php`
- Modify: `app/Http/Controllers/Api/MealController.php`

- [ ] **Step 1: Create events**

```php
// app/Events/EntryRecorded.php
namespace App\Events;

use App\Models\Registration;
use Illuminate\Foundation\Events\Dispatchable;

class EntryRecorded
{
    use Dispatchable;

    public function __construct(public Registration $registration) {}
}
```

```php
// app/Events/MealUsed.php
namespace App\Events;

use App\Models\Registration;
use Illuminate\Foundation\Events\Dispatchable;

class MealUsed
{
    use Dispatchable;

    public function __construct(public Registration $registration, public string $mealType) {}
}
```

- [ ] **Step 2: Create listeners**

```php
// app/Listeners/UpdateRedisCache.php
namespace App\Listeners;

use App\Events\EntryRecorded;
use App\Events\MealUsed;
use Illuminate\Support\Facades\Redis;

class UpdateRedisCache
{
    public function handleEntry(EntryRecorded $event): void
    {
        $hash = $event->registration->qr_hash;
        Redis::setex("qr:{$hash}:entry", 86400, now()->toIso8601String());

        $eventId = $event->registration->event_id;
        Redis::incr("event:{$eventId}:entries");
    }

    public function handleMeal(MealUsed $event): void
    {
        $hash = $event->registration->qr_hash;
        $type = $event->mealType;
        Redis::setex("qr:{$hash}:{$type}", 86400, now()->toIso8601String());

        $eventId = $event->registration->event_id;
        Redis::incr("event:{$eventId}:{$type}_used");
    }
}
```

```php
// app/Listeners/PreloadRedisCache.php
namespace App\Listeners;

use App\Events\RegistrationsImported;
use Illuminate\Support\Facades\Redis;

class PreloadRedisCache
{
    public function handle(RegistrationsImported $event): void
    {
        foreach ($event->registrations as $reg) {
            Redis::setex("qr:{$reg->qr_hash}:entry", 86400, '');
            Redis::setex("qr:{$reg->qr_hash}:lunch", 86400, '');
            Redis::setex("qr:{$reg->qr_hash}:dinner", 86400, '');
        }

        $eventId = $event->event->id;
        Redis::set("event:{$eventId}:total_regs", $event->registrations->count());
    }
}
```

- [ ] **Step 3: Register events/listeners in `EventServiceProvider`**

```php
// app/Providers/EventServiceProvider.php
namespace App\Providers;

use App\Events\EntryRecorded;
use App\Events\MealUsed;
use App\Listeners\UpdateRedisCache;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        EntryRecorded::class => [
            [UpdateRedisCache::class, 'handleEntry'],
        ],
        MealUsed::class => [
            [UpdateRedisCache::class, 'handleMeal'],
        ],
    ];
}
```

- [ ] **Step 4: Dispatch events from controllers**

Update `EntryController::record()` — add after successful `recordEntry()`:
```php
event(new \App\Events\EntryRecorded($reg));
```

Update `MealController::record()` — add after successful `recordMeal()`:
```php
event(new \App\Events\MealUsed($reg, $mealType));
```

- [ ] **Step 5: Add Redis-first lookup in ScanController**

```php
// Update ScanController::scan() to check Redis first
public function scan(ScanRequest $request)
{
    $code = $request->code;

    // Compute qr_hash from the unique_code
    $qrHash = hash_hmac('sha256', $code, config('app.key'));

    // Redis fast path
    $cached = \Illuminate\Support\Facades\Redis::get("qr:{$qrHash}:entry");

    $reg = Registration::where('unique_code', $code)->first();

    if (! $reg) {
        return response()->json(['message' => 'Registration not found.'], 404);
    }

    return response()->json([
        'data' => new ScanResponseResource(ScanResponseDTO::fromModel($reg)),
    ]);
}
```

- [ ] **Step 6: Commit**

```bash
git add app/Events/ app/Listeners/ app/Providers/EventServiceProvider.php app/Http/Controllers/Api/
git commit -m "feat: add Redis caching layer with event-driven cache updates"
```

---

## Phase 6: Communication Module

### Task 10: Email & SMS Integration

**Files:**
- Create: `app/Services/CommunicationService.php`
- Create: `app/Jobs/SendBulkEmail.php`
- Create: `app/Jobs/SendBulkSMS.php`
- Create: `app/Http/Controllers/Api/CommunicationController.php`
- Create: `resources/views/emails/invitation.blade.php`
- Create: `tests/Feature/CommunicationTest.php`

- [ ] **Step 1: Write failing communication test**

```php
// tests/Feature/CommunicationTest.php
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CommunicationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->create(['role' => 'event_manager']);
    }

    public function test_send_invites_dispatches_bulk_email_job(): void
    {
        Queue::fake();
        $event = Event::factory()->create();
        $regs = Registration::factory()->count(3)->create(['event_id' => $event->id, 'email' => fake()->safeEmail()]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/event/{$event->id}/send-invites", [
                'type' => 'email',
                'subject' => 'You are invited!',
                'registration_ids' => $regs->pluck('id')->toArray(),
            ]);

        $response->assertOk();
        Queue::assertPushed(\App\Jobs\SendBulkEmail::class);
    }

    public function test_send_invites_validates_type(): void
    {
        $event = Event::factory()->create();

        $response = $this->actingAs($this->manager)
            ->postJson("/api/event/{$event->id}/send-invites", [
                'type' => 'whatsapp',
            ]);

        $response->assertStatus(422);
    }

    public function test_communication_log_created_on_send(): void
    {
        $event = Event::factory()->create();
        $reg = Registration::factory()->create(['event_id' => $event->id, 'email' => 'test@example.com']);

        $this->actingAs($this->manager)
            ->postJson("/api/event/{$event->id}/send-invites", [
                'type' => 'email',
                'subject' => 'Test',
                'registration_ids' => [$reg->id],
            ]);

        // Communication record should be created with pending status
        $this->assertDatabaseHas('communications', [
            'registration_id' => $reg->id,
            'type' => 'email',
            'status' => 'pending',
        ]);
    }
}
```

```bash
php artisan test tests/Feature/CommunicationTest.php
```

Expected: FAIL

- [ ] **Step 2: Create email template**

```blade
{{-- resources/views/emails/invitation.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a56db; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .qr-code { text-align: center; margin: 20px 0; }
        .footer { font-size: 12px; color: #666; text-align: center; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $event->name }}</h1>
            <p>{{ $event->event_date->format('F j, Y') }} | {{ $event->venue }}</p>
        </div>
        <div class="content">
            <p>Dear {{ $registration->name }},</p>
            <p>You are invited to <strong>{{ $event->name }}</strong>.</p>
            <p>Please present the QR code below at the entrance:</p>
            <div class="qr-code">
                {!! $qrCodeSvg !!}
            </div>
        </div>
        <div class="footer">
            <p>ICT Foundation Nepal</p>
        </div>
    </div>
</body>
</html>
```

- [ ] **Step 3: Create CommunicationService**

```php
// app/Services/CommunicationService.php
namespace App\Services;

use App\Models\Communication;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;

class CommunicationService
{
    public function sendEmail(Registration $registration, Event $event, string $subject): Communication
    {
        $comm = Communication::create([
            'registration_id' => $registration->id,
            'type' => 'email',
            'subject' => $subject,
            'status' => 'pending',
        ]);

        try {
            $qrService = app(QRCodeService::class);
            $qrCodeSvg = $qrService->generateSvg($registration);

            Mail::send('emails.invitation', [
                'event' => $event,
                'registration' => $registration,
                'qrCodeSvg' => $qrCodeSvg,
            ], function ($message) use ($registration, $subject) {
                $message->to($registration->email)
                    ->subject($subject);
            });

            $comm->markSent();
        } catch (\Throwable $e) {
            $comm->markFailed(['error' => $e->getMessage()]);
        }

        return $comm;
    }

    public function sendSms(Registration $registration, string $message): Communication
    {
        $comm = Communication::create([
            'registration_id' => $registration->id,
            'type' => 'sms',
            'status' => 'pending',
        ]);

        try {
            $token = config('services.sparrow.token');
            $from = config('services.sparrow.from');

            $response = Http::asForm()->post('https://api.sparrowsms.com/v2/sms/', [
                'token' => $token,
                'from' => $from,
                'to' => $registration->phone,
                'text' => $message,
            ]);

            if ($response->successful()) {
                $comm->markSent($response->json('response_id'));
            } else {
                $comm->markFailed(['response' => $response->body()]);
            }
        } catch (\Throwable $e) {
            $comm->markFailed(['error' => $e->getMessage()]);
        }

        return $comm;
    }
}
```

Add to `config/services.php`:
```php
'sparrow' => [
    'token' => env('SPARROW_SMS_TOKEN'),
    'from' => env('SPARROW_SMS_FROM', 'ICTFoundation'),
],
```

- [ ] **Step 4: Create queue jobs**

```php
// app/Jobs/SendBulkEmail.php
namespace App\Jobs;

use App\Models\Event;
use App\Models\Registration;
use App\Services\CommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBulkEmail implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $registrationIds,
        public int $eventId,
        public string $subject,
    ) {}

    public function handle(CommunicationService $service): void
    {
        $event = Event::findOrFail($this->eventId);

        foreach ($this->registrationIds as $regId) {
            $reg = Registration::find($regId);
            if ($reg && $reg->email) {
                $service->sendEmail($reg, $event, $this->subject);
            }
        }
    }
}
```

```php
// app/Jobs/SendBulkSMS.php
namespace App\Jobs;

use App\Models\Event;
use App\Models\Registration;
use App\Services\CommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBulkSMS implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $registrationIds,
        public int $eventId,
        public string $message,
    ) {}

    public function handle(CommunicationService $service): void
    {
        $event = Event::findOrFail($this->eventId);

        foreach ($this->registrationIds as $regId) {
            $reg = Registration::find($regId);
            if ($reg && $reg->phone) {
                $service->sendSms($reg, $this->message);
            }
        }
    }
}
```

- [ ] **Step 5: Create CommunicationController**

```php
// app/Http/Controllers/Api/CommunicationController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendBulkEmail;
use App\Jobs\SendBulkSMS;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    public function sendInvites(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:email,sms',
            'subject' => 'required_if:type,email|string|max:255',
            'message' => 'required_if:type,sms|string|max:160',
            'registration_ids' => 'array',
            'registration_ids.*' => 'exists:registrations,id',
        ]);

        $regIds = $validated['registration_ids'] ?? $event->registrations()->pluck('id')->toArray();

        if ($validated['type'] === 'email') {
            SendBulkEmail::dispatch($regIds, $event->id, $validated['subject']);
        } else {
            SendBulkSMS::dispatch($regIds, $event->id, $validated['message']);
        }

        return response()->json([
            'message' => ucfirst($validated['type']) . ' jobs dispatched.',
            'count' => count($regIds),
        ]);
    }
}
```

- [ ] **Step 6: Run communication tests — verify they pass**

```bash
php artisan test tests/Feature/CommunicationTest.php
```

Expected: PASS

- [ ] **Step 7: Create Filament CommunicationResource**

```bash
php artisan make:filament-resource Communication --generate
```

Customize columns to show: `registration.name`, `type`, `subject`, `status`, `sent_at`. Add filters by status and type.

- [ ] **Step 8: Commit**

```bash
git add app/Services/CommunicationService.php app/Jobs/ app/Http/Controllers/Api/CommunicationController.php resources/views/emails/ config/services.php tests/Feature/CommunicationTest.php
git commit -m "feat: add email/SMS communication module with queue jobs and logging"
```

---

## Phase 7: Dashboard & Reporting

### Task 11: Real-Time Dashboard + Report Exports

**Files:**
- Create: `app/Http/Controllers/Api/EventDashboardController.php`
- Create: `app/Http/Controllers/Api/ReportController.php`
- Create: `app/Http/Resources/EventDashboardResource.php`
- Create: `app/Exports/AttendanceExport.php`
- Create: `app/Exports/NoShowExport.php`
- Create: `tests/Feature/ReportTest.php`

- [ ] **Step 1: Write failing dashboard and report tests**

```php
// tests/Feature/ReportTest.php
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->create(['role' => 'event_manager']);
    }

    public function test_event_dashboard_returns_stats(): void
    {
        $event = Event::factory()->create();
        Registration::factory()->count(10)->create(['event_id' => $event->id]);
        Registration::factory()->count(3)->create([
            'event_id' => $event->id,
            'entry_time' => now(),
            'lunch_used_at' => now(),
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson("/api/event/{$event->id}/dashboard");

        $response->assertOk()
            ->assertJsonPath('data.total_registrations', 13)
            ->assertJsonPath('data.total_entries', 3)
            ->assertJsonPath('data.lunch_used', 3);
    }

    public function test_attendance_export_returns_csv(): void
    {
        $event = Event::factory()->create();
        Registration::factory()->count(5)->create(['event_id' => $event->id]);

        $response = $this->actingAs($this->manager)
            ->get("/api/reports/attendance/{$event->id}");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_noshow_export_returns_unentered_registrations(): void
    {
        $event = Event::factory()->create();
        Registration::factory()->count(3)->create(['event_id' => $event->id, 'entry_time' => now()]);
        Registration::factory()->count(2)->create(['event_id' => $event->id, 'entry_time' => null]);

        $response = $this->actingAs($this->manager)
            ->get("/api/reports/noshow/{$event->id}");

        $response->assertOk();
        $csv = str_getcsv(substr($response->getContent(), 0, strpos($response->getContent(), "\n")));
        $this->assertStringContainsString('name', implode(',', $csv));
    }
}
```

```bash
php artisan test tests/Feature/ReportTest.php
```

Expected: FAIL

- [ ] **Step 2: Create EventDashboardController**

```php
// app/Http/Controllers/Api/EventDashboardController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;

class EventDashboardController extends Controller
{
    public function show(Event $event)
    {
        $stats = $event->getStats();
        $total = $stats['total_registrations'];

        return response()->json([
            'data' => [
                'total_registrations' => $total,
                'total_entries' => $stats['total_entries'],
                'lunch_used' => $stats['lunch_used'],
                'dinner_used' => $stats['dinner_used'],
                'entry_percentage' => $total > 0 ? round(($stats['total_entries'] / $total) * 100, 1) : 0,
                'lunch_percentage' => $total > 0 ? round(($stats['lunch_used'] / $total) * 100, 1) : 0,
                'dinner_percentage' => $total > 0 ? round(($stats['dinner_used'] / $total) * 100, 1) : 0,
            ],
        ]);
    }
}
```

- [ ] **Step 3: Create export classes**

```php
// app/Exports/AttendanceExport.php
namespace App\Exports;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private Event $event) {}

    public function collection()
    {
        return $this->event->registrations()->withTrashed()->get();
    }

    public function headings(): array
    {
        return ['Name', 'Email', 'Phone', 'Organization', 'Designation', 'Entry Time', 'Lunch Used At', 'Dinner Used At'];
    }

    public function map($row): array
    {
        return [
            $row->name,
            $row->email,
            $row->phone,
            $row->organization,
            $row->designation,
            $row->entry_time?->toDateTimeString(),
            $row->lunch_used_at?->toDateTimeString(),
            $row->dinner_used_at?->toDateTimeString(),
        ];
    }
}
```

```php
// app/Exports/NoShowExport.php
namespace App\Exports;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class NoShowExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private Event $event) {}

    public function collection()
    {
        return $this->event->registrations()->whereNull('entry_time')->get();
    }

    public function headings(): array
    {
        return ['Name', 'Email', 'Phone', 'Organization'];
    }

    public function map($row): array
    {
        return [$row->name, $row->email, $row->phone, $row->organization];
    }
}
```

- [ ] **Step 4: Create ReportController**

```php
// app/Http/Controllers/Api/ReportController.php
namespace App\Http\Controllers\Api;

use App\Exports\AttendanceExport;
use App\Exports\NoShowExport;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function attendance(Event $event)
    {
        return Excel::download(new AttendanceExport($event), "attendance-{$event->slug}.csv", \Maatwebsite\Excel\Excel::CSV);
    }

    public function noShow(Event $event)
    {
        return Excel::download(new NoShowExport($event), "noshow-{$event->slug}.csv", \Maatwebsite\Excel\Excel::CSV);
    }
}
```

- [ ] **Step 5: Run report tests — verify they pass**

```bash
php artisan test tests/Feature/ReportTest.php
```

Expected: PASS

- [ ] **Step 6: Add Filament dashboard widget for live stats**

```bash
php artisan make:filament-widget EventStatsOverview --stats-overview
```

```php
// app/Filament/Widgets/EventStatsOverview.php
namespace App\Filament\Widgets;

use App\Models\Event;
use App\Models\Registration;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EventStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $eventId = request()->route('record')?->id;

        if (! $eventId) {
            return [
                Stat::make('Total Events', Event::count()),
                Stat::make('Total Registrations', Registration::count()),
            ];
        }

        $event = Event::withCount([
            'registrations',
            'registrations as entries_count' => fn ($q) => $q->whereNotNull('entry_time'),
            'registrations as lunch_count' => fn ($q) => $q->whereNotNull('lunch_used_at'),
            'registrations as dinner_count' => fn ($q) => $q->whereNotNull('dinner_used_at'),
        ])->findOrFail($eventId);

        return [
            Stat::make('Registrations', $event->registrations_count),
            Stat::make('Entries', $event->entries_count),
            Stat::make('Lunch Used', $event->lunch_count),
            Stat::make('Dinner Used', $event->dinner_count),
        ];
    }
}
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/EventDashboardController.php app/Http/Controllers/Api/ReportController.php app/Exports/ app/Filament/Widgets/ tests/Feature/ReportTest.php
git commit -m "feat: add event dashboard API, attendance/noshow exports, and Filament stats widget"
```

---

## Phase 8: React PWA Scanner App

### Task 12: Scaffold React PWA

**Files:**
- Create: `scanner-app/` (entire directory)
- Create: `scanner-app/package.json`
- Create: `scanner-app/vite.config.js`
- Create: `scanner-app/src/App.jsx`
- Create: `scanner-app/src/utils/api.js`
- Create: `scanner-app/src/hooks/useAuth.js`
- Create: `scanner-app/src/hooks/useApi.js`
- Create: `scanner-app/public/manifest.json`

- [ ] **Step 1: Scaffold React + Vite project**

```bash
cd /Users/manojghale/Documents/Projects/event-management
npm create vite@latest scanner-app -- --template react
cd scanner-app
npm install
```

- [ ] **Step 2: Install dependencies**

```bash
npm install html5-qrcode react-router-dom axios
npm install -D vite-plugin-pwa @vite-pwa/assets-generator
```

- [ ] **Step 3: Configure Vite with PWA**

```js
// scanner-app/vite.config.js
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
  plugins: [
    react(),
    VitePWA({
      registerType: 'autoUpdate',
      manifest: {
        name: 'Event Scanner',
        short_name: 'Scanner',
        theme_color: '#1a56db',
        background_color: '#ffffff',
        display: 'standalone',
        icons: [
          { src: '/icon-192.png', sizes: '192x192', type: 'image/png' },
          { src: '/icon-512.png', sizes: '512x512', type: 'image/png' },
        ],
      },
    }),
  ],
  server: {
    proxy: {
      '/api': 'http://localhost:8000',
    },
  },
});
```

- [ ] **Step 4: Create API utility**

```js
// scanner-app/src/utils/api.js
import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
    }
    return Promise.reject(err);
  }
);

export default api;
```

- [ ] **Step 5: Create auth hook**

```js
// scanner-app/src/hooks/useAuth.js
import { useState, useCallback } from 'react';
import api from '../utils/api';

export function useAuth() {
  const [user, setUser] = useState(() => {
    const saved = localStorage.getItem('user');
    return saved ? JSON.parse(saved) : null;
  });

  const login = useCallback(async (email, password) => {
    const { data } = await api.post('/login', { email, password });
    localStorage.setItem('auth_token', data.token);
    localStorage.setItem('user', JSON.stringify(data.user));
    setUser(data.user);
  }, []);

  const logout = useCallback(async () => {
    await api.post('/logout');
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    setUser(null);
  }, []);

  return { user, login, logout, isAuthenticated: !!user };
}
```

- [ ] **Step 6: Create App with routing**

```jsx
// scanner-app/src/App.jsx
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from './hooks/useAuth';
import Login from './pages/Login';
import Scanner from './pages/Scanner';

function PrivateRoute({ children }) {
  const { isAuthenticated } = useAuth();
  return isAuthenticated ? children : <Navigate to="/login" />;
}

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/scanner" element={<PrivateRoute><Scanner /></PrivateRoute>} />
        <Route path="*" element={<Navigate to="/scanner" />} />
      </Routes>
    </BrowserRouter>
  );
}
```

- [ ] **Step 7: Create Login page**

```jsx
// scanner-app/src/pages/Login.jsx
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';

export default function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const { login } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    try {
      await login(email, password);
      navigate('/scanner');
    } catch {
      setError('Invalid credentials');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ maxWidth: 400, margin: '100px auto', padding: 20 }}>
      <h1 style={{ textAlign: 'center' }}>Event Scanner</h1>
      <form onSubmit={handleSubmit}>
        <div style={{ marginBottom: 12 }}>
          <input
            type="email"
            placeholder="Email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            style={{ width: '100%', padding: 10, fontSize: 16 }}
          />
        </div>
        <div style={{ marginBottom: 12 }}>
          <input
            type="password"
            placeholder="Password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            style={{ width: '100%', padding: 10, fontSize: 16 }}
          />
        </div>
        {error && <p style={{ color: 'red' }}>{error}</p>}
        <button
          type="submit"
          disabled={loading}
          style={{ width: '100%', padding: 12, fontSize: 16, background: '#1a56db', color: 'white', border: 'none', borderRadius: 4 }}
        >
          {loading ? 'Logging in...' : 'Login'}
        </button>
      </form>
    </div>
  );
}
```

- [ ] **Step 8: Verify PWA scaffolding builds**

```bash
cd scanner-app && npm run build
```

Expected: Build succeeds, `dist/` folder created.

- [ ] **Step 9: Commit**

```bash
cd /Users/manojghale/Documents/Projects/event-management
git add scanner-app/
git commit -m "feat: scaffold React PWA scanner app with auth and routing"
```

---

### Task 13: QR Scanner Component + Entry/Meal Actions

**Files:**
- Create: `scanner-app/src/components/QrScanner.jsx`
- Create: `scanner-app/src/components/GuestCard.jsx`
- Create: `scanner-app/src/components/ActionButtons.jsx`
- Create: `scanner-app/src/components/SearchFallback.jsx`
- Create: `scanner-app/src/pages/Scanner.jsx`

- [ ] **Step 1: Create QrScanner component**

```jsx
// scanner-app/src/components/QrScanner.jsx
import { useState, useRef, useEffect } from 'react';
import { Html5Qrcode } from 'html5-qrcode';

export default function QrScanner({ onScan }) {
  const [scanning, setScanning] = useState(false);
  const scannerRef = useRef(null);

  useEffect(() => {
    return () => {
      if (scannerRef.current) {
        scannerRef.current.stop().catch(() => {});
      }
    };
  }, []);

  const startScan = async () => {
    setScanning(true);
    const scanner = new Html5Qrcode('qr-reader');
    scannerRef.current = scanner;

    try {
      await scanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        (decodedText) => {
          scanner.stop();
          setScanning(false);
          onScan(decodedText);
        },
        () => {}
      );
    } catch (err) {
      setScanning(false);
      alert('Camera access denied. Use manual search.');
    }
  };

  const stopScan = async () => {
    if (scannerRef.current) {
      await scannerRef.current.stop();
    }
    setScanning(false);
  };

  return (
    <div>
      <div id="qr-reader" style={{ width: '100%' }} />
      {!scanning ? (
        <button onClick={startScan} style={styles.button}>
          Start Scanning
        </button>
      ) : (
        <button onClick={stopScan} style={{ ...styles.button, background: '#dc2626' }}>
          Stop Scanning
        </button>
      )}
    </div>
  );
}

const styles = {
  button: {
    width: '100%',
    padding: 16,
    fontSize: 18,
    background: '#1a56db',
    color: 'white',
    border: 'none',
    borderRadius: 8,
    cursor: 'pointer',
  },
};
```

- [ ] **Step 2: Create GuestCard component**

```jsx
// scanner-app/src/components/GuestCard.jsx
export default function GuestCard({ guest }) {
  const statusColor = (bool) => (bool ? '#16a34a' : '#9ca3af');

  return (
    <div style={styles.card}>
      <h2 style={{ margin: '0 0 8px' }}>{guest.name}</h2>
      {guest.organization && <p style={{ margin: '0 0 4px', color: '#666' }}>{guest.organization}</p>}
      {guest.designation && <p style={{ margin: '0 0 8px', color: '#666' }}>{guest.designation}</p>}
      <div style={styles.statusRow}>
        <span style={{ ...styles.badge, background: statusColor(guest.has_entered) }}>
          Entry: {guest.has_entered ? guest.entry_time : 'Pending'}
        </span>
        <span style={{ ...styles.badge, background: statusColor(guest.lunch_used) }}>
          Lunch: {guest.lunch_used ? 'Used' : 'Available'}
        </span>
        <span style={{ ...styles.badge, background: statusColor(guest.dinner_used) }}>
          Dinner: {guest.dinner_used ? 'Used' : 'Available'}
        </span>
      </div>
    </div>
  );
}

const styles = {
  card: {
    background: 'white',
    border: '1px solid #e5e7eb',
    borderRadius: 8,
    padding: 16,
    marginBottom: 12,
  },
  statusRow: {
    display: 'flex',
    gap: 8,
    flexWrap: 'wrap',
    marginTop: 8,
  },
  badge: {
    padding: '4px 8px',
    borderRadius: 4,
    color: 'white',
    fontSize: 12,
  },
};
```

- [ ] **Step 3: Create ActionButtons component**

```jsx
// scanner-app/src/components/ActionButtons.jsx
import { useState } from 'react';
import api from '../utils/api';

export default function ActionButtons({ guest, onUpdate }) {
  const [loading, setLoading] = useState(null);

  const handleAction = async (action, payload = {}) => {
    setLoading(action);
    try {
      const endpoint = action === 'entry' ? '/entry' : '/meal';
      const data = action === 'entry'
        ? { registration_id: guest.id }
        : { registration_id: guest.id, meal_type: payload.meal_type };

      const res = await api.post(endpoint, data);
      alert(res.data.message);
      onUpdate();
    } catch (err) {
      const msg = err.response?.data?.message || 'Action failed';
      alert(msg);
    } finally {
      setLoading(null);
    }
  };

  return (
    <div style={styles.container}>
      <button
        onClick={() => handleAction('entry')}
        disabled={guest.has_entered || loading === 'entry'}
        style={{
          ...styles.btn,
          background: guest.has_entered ? '#9ca3af' : '#1a56db',
        }}
      >
        {guest.has_entered ? 'Already Entered' : 'Record Entry'}
      </button>

      <button
        onClick={() => handleAction('meal', { meal_type: 'lunch' })}
        disabled={guest.lunch_used || loading === 'lunch'}
        style={{
          ...styles.btn,
          background: guest.lunch_used ? '#9ca3af' : '#16a34a',
        }}
      >
        {guest.lunch_used ? 'Lunch Used' : 'Mark Lunch'}
      </button>

      <button
        onClick={() => handleAction('meal', { meal_type: 'dinner' })}
        disabled={guest.dinner_used || loading === 'dinner'}
        style={{
          ...styles.btn,
          background: guest.dinner_used ? '#9ca3af' : '#d97706',
        }}
      >
        {guest.dinner_used ? 'Dinner Used' : 'Mark Dinner'}
      </button>
    </div>
  );
}

const styles = {
  container: {
    display: 'flex',
    gap: 8,
    marginBottom: 16,
  },
  btn: {
    flex: 1,
    padding: 12,
    fontSize: 14,
    color: 'white',
    border: 'none',
    borderRadius: 6,
    cursor: 'pointer',
  },
};
```

- [ ] **Step 4: Create SearchFallback component**

```jsx
// scanner-app/src/components/SearchFallback.jsx
import { useState } from 'react';
import api from '../utils/api';

export default function SearchFallback({ onSelect }) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState([]);
  const [searching, setSearching] = useState(false);

  const search = async () => {
    if (query.length < 2) return;
    setSearching(true);
    try {
      const { data } = await api.get('/guest/search', { params: { q: query } });
      setResults(data.data);
    } catch {
      setResults([]);
    } finally {
      setSearching(false);
    }
  };

  return (
    <div style={{ marginTop: 16 }}>
      <div style={{ display: 'flex', gap: 8 }}>
        <input
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && search()}
          placeholder="Search by name, email, or phone"
          style={{ flex: 1, padding: 10, fontSize: 16 }}
        />
        <button onClick={search} disabled={searching} style={{ padding: '10px 16px' }}>
          {searching ? '...' : 'Search'}
        </button>
      </div>
      {results.map((r) => (
        <div
          key={r.id}
          onClick={() => onSelect(r)}
          style={{ padding: 12, borderBottom: '1px solid #e5e7eb', cursor: 'pointer' }}
        >
          <strong>{r.name}</strong> — {r.organization || 'N/A'}
        </div>
      ))}
    </div>
  );
}
```

- [ ] **Step 5: Create Scanner page composing all components**

```jsx
// scanner-app/src/pages/Scanner.jsx
import { useState, useCallback } from 'react';
import { useAuth } from '../hooks/useAuth';
import QrScanner from '../components/QrScanner';
import GuestCard from '../components/GuestCard';
import ActionButtons from '../components/ActionButtons';
import SearchFallback from '../components/SearchFallback';
import api from '../utils/api';

export default function Scanner() {
  const { user, logout } = useAuth();
  const [guest, setGuest] = useState(null);
  const [error, setError] = useState('');

  const handleScan = useCallback(async (code) => {
    setError('');
    try {
      const { data } = await api.post('/scan', { code });
      setGuest(data.data);
    } catch (err) {
      setError(err.response?.data?.message || 'Scan failed');
      setGuest(null);
    }
  }, []);

  const refreshGuest = useCallback(async () => {
    if (!guest) return;
    const { data } = await api.post('/scan', { code: guest.unique_code || guest.id });
    setGuest(data.data);
  }, [guest]);

  return (
    <div style={{ maxWidth: 480, margin: '0 auto', padding: 16 }}>
      <header style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 16 }}>
        <h1>Event Scanner</h1>
        <button onClick={logout} style={{ padding: '4px 12px' }}>Logout</button>
      </header>

      <QrScanner onScan={handleScan} />

      {error && (
        <div style={{ background: '#fee2e2', color: '#991b1b', padding: 12, borderRadius: 6, marginTop: 12 }}>
          {error}
        </div>
      )}

      {guest && (
        <>
          <GuestCard guest={guest} />
          <ActionButtons guest={guest} onUpdate={refreshGuest} />
        </>
      )}

      <details style={{ marginTop: 16 }}>
        <summary style={{ cursor: 'pointer', color: '#1a56db' }}>
          Manual Search (if QR damaged)
        </summary>
        <SearchFallback onSelect={(g) => setGuest(g)} />
      </details>
    </div>
  );
}
```

- [ ] **Step 6: Verify PWA builds**

```bash
cd scanner-app && npm run build
```

Expected: Build succeeds.

- [ ] **Step 7: Commit**

```bash
cd /Users/manojghale/Documents/Projects/event-management
git add scanner-app/
git commit -m "feat: add QR scanner, guest card, action buttons, and manual search to PWA"
```

---

## Phase 9: Queue Configuration & Horizon

### Task 14: Configure Laravel Horizon for Queue Management

**Files:**
- Modify: `config/horizon.php`

- [ ] **Step 1: Configure Horizon**

```php
// config/horizon.php — update environments section
'environments' => [
    'production' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['high', 'default', 'low'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'processes' => 10,
            'tries' => 3,
            'maxTime' => 60,
            'maxJobs' => 1000,
            'balanceCooldown' => 1,
        ],
    ],
    'local' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['high', 'default', 'low'],
            'balance' => 'auto',
            'processes' => 3,
            'tries' => 3,
        ],
    ],
],
```

- [ ] **Step 2: Assign queue priorities to jobs**

Update each job class to specify queue:
```php
// In SendBulkEmail
public $queue = 'high';

// In SendBulkSMS
public $queue = 'high';

// In GenerateQRCodes (if created)
public $queue = 'low';

// In UpdateRegistrationFromScan (if created)
public $queue = 'default';
```

- [ ] **Step 3: Commit**

```bash
git add config/horizon.php app/Jobs/
git commit -m "feat: configure Laravel Horizon with environment-based queue workers"
```

---

## Phase 10: Final Wiring, Testing & Cleanup

### Task 15: Integration Tests + Idempotency + Rate Limiting

**Files:**
- Create: `tests/Feature/IntegrationTest.php`
- Modify: `routes/api.php` (add rate limiting)

- [ ] **Step 1: Write integration test covering full scan-to-meal flow**

```php
// tests/Feature/IntegrationTest.php
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = User::factory()->create(['role' => 'scanner']);
    }

    public function test_full_scan_entry_meal_flow(): void
    {
        $event = Event::factory()->create(['meal_types' => ['lunch', 'dinner']]);
        $reg = Registration::factory()->create([
            'event_id' => $event->id,
            'entry_time' => null,
            'lunch_used_at' => null,
            'dinner_used_at' => null,
        ]);

        // Step 1: Scan QR
        $scanResponse = $this->actingAs($this->scanner)
            ->postJson('/api/scan', ['code' => $reg->unique_code]);
        $scanResponse->assertOk();
        $this->assertFalse($scanResponse->json('data.has_entered'));

        // Step 2: Record entry
        $entryResponse = $this->actingAs($this->scanner)
            ->postJson('/api/entry', ['registration_id' => $reg->id]);
        $entryResponse->assertOk();

        // Step 3: Attempt duplicate entry
        $dupEntryResponse = $this->actingAs($this->scanner)
            ->postJson('/api/entry', ['registration_id' => $reg->id]);
        $dupEntryResponse->assertStatus(409);

        // Step 4: Record lunch
        $lunchResponse = $this->actingAs($this->scanner)
            ->postJson('/api/meal', ['registration_id' => $reg->id, 'meal_type' => 'lunch']);
        $lunchResponse->assertOk();

        // Step 5: Attempt duplicate lunch
        $dupLunchResponse = $this->actingAs($this->scanner)
            ->postJson('/api/meal', ['registration_id' => $reg->id, 'meal_type' => 'lunch']);
        $dupLunchResponse->assertStatus(409);

        // Step 6: Record dinner
        $dinnerResponse = $this->actingAs($this->scanner)
            ->postJson('/api/meal', ['registration_id' => $reg->id, 'meal_type' => 'dinner']);
        $dinnerResponse->assertOk();

        // Step 7: Verify dashboard stats
        $manager = User::factory()->create(['role' => 'event_manager']);
        $dashboardResponse = $this->actingAs($manager)
            ->getJson("/api/event/{$event->id}/dashboard");
        $dashboardResponse->assertOk()
            ->assertJsonPath('data.total_registrations', 1)
            ->assertJsonPath('data.total_entries', 1)
            ->assertJsonPath('data.lunch_used', 1)
            ->assertJsonPath('data.dinner_used', 1);

        // Step 8: Re-scan to verify state
        $reScanResponse = $this->actingAs($this->scanner)
            ->postJson('/api/scan', ['code' => $reg->unique_code]);
        $reScanResponse->assertOk();
        $this->assertTrue($reScanResponse->json('data.has_entered'));
        $this->assertTrue($reScanResponse->json('data.lunch_used'));
        $this->assertTrue($reScanResponse->json('data.dinner_used'));
    }

    public function test_guest_search_finds_by_name(): void
    {
        Registration::factory()->create(['name' => 'Unique Test Name']);

        $response = $this->actingAs($this->scanner)
            ->getJson('/api/guest/search?q=Unique Test');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
```

- [ ] **Step 2: Add rate limiting to scan routes**

```php
// Update routes/api.php — wrap scanner routes with throttle
Route::middleware(['auth:sanctum', 'role:scanner,event_manager,super_admin', 'throttle:60,1'])->group(function () {
    Route::post('/scan', [ScanController::class, 'scan']);
    Route::post('/entry', [EntryController::class, 'record']);
    Route::post('/meal', [MealController::class, 'record']);
    Route::get('/guest/search', [GuestSearchController::class, 'search']);
});
```

- [ ] **Step 3: Run full test suite**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/IntegrationTest.php routes/api.php
git commit -m "feat: add integration test for full scan-to-meal flow and rate limiting"
```

---

### Task 16: CORS, Final Configuration & Smoke Test

**Files:**
- Modify: `config/cors.php`
- Modify: `.env.example`

- [ ] **Step 1: Configure CORS for PWA**

```php
// config/cors.php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['http://localhost:5173', env('FRONTEND_URL')],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
];
```

- [ ] **Step 2: Update .env.example with all config**

Add to `.env.example`:
```env
FRONTEND_URL=http://localhost:5173
SESSION_DOMAIN=localhost
SANCTUM_STATEFUL_DOMAINS=localhost:5173

# Horizon
HORIZON_DASHBOARD_ENABLED=true

# Logging
LOG_CHANNEL=daily
LOG_DAYS=14
```

- [ ] **Step 3: Run final smoke test — full test suite**

```bash
php artisan test --parallel
```

Expected: All tests pass.

- [ ] **Step 4: Create initial CLAUDE.md**

```bash
cat > CLAUDE.md << 'EOF'
# Event Management System

## Tech Stack
- Backend: Laravel 11, PHP 8.2+, PostgreSQL 15, Redis 7
- Admin: FilamentPHP 3
- Scanner: React 18 PWA (scanner-app/)
- Queue: Laravel Horizon

## Commands
- `php artisan test` — run all tests
- `php artisan horizon` — start queue worker
- `cd scanner-app && npm run dev` — start PWA dev server

## Architecture
- Models: User, Event, Registration, Communication
- API auth: Laravel Sanctum
- Roles: super_admin, event_manager, scanner, viewer
- QR codes: UUID v4 + HMAC-SHA256 signed

## Key Files
- `app/Services/QRCodeService.php` — QR generation & resolution
- `app/Imports/RegistrationsImport.php` — Excel import logic
- `app/Services/CommunicationService.php` — Email & SMS dispatch
- `config/horizon.php` — Queue configuration
EOF
```

- [ ] **Step 5: Final commit**

```bash
git add config/cors.php .env.example CLAUDE.md
git commit -m "chore: configure CORS, finalize env, add CLAUDE.md"
```

---

## Self-Review Checklist

### Spec Coverage (PRD Requirements)

| Requirement | Task |
|---|---|
| FR-01: Centralized PostgreSQL DB | Task 2 |
| FR-02: Search/filter by name, org, email | Task 8 (GuestSearchController) |
| FR-03: Full-text search | Task 8 (ILIKE queries, PostgreSQL indexed) |
| FR-04: Soft delete only | Task 3 (SoftDeletes trait on all models) |
| FR-05: Export to Excel/CSV | Task 11 (AttendanceExport, NoShowExport) |
| FR-06: Create event with fields | Task 5 (EventResource) |
| FR-07: Import Excel with auto-detect | Task 6 (RegistrationsImport) |
| FR-08: Validate email/phone | Task 6 (validation in import) |
| FR-09: Duplicate detection | Task 6 (skipDuplicates flag) |
| FR-10: Unique QR per registration | Task 3 (UUID + HMAC in booted()) |
| FR-11: Bulk email | Task 10 (SendBulkEmail job) |
| FR-12: QR embedded in email | Task 10 (invitation.blade.php) |
| FR-13: Bulk SMS | Task 10 (SendBulkSMS job) |
| FR-14: Communication log | Task 10 (Communication model) |
| FR-15: Resend failed | Task 10 (markFailed + status tracking) |
| FR-16: Scanner login | Task 4 (AuthController) + Task 12 (Login.jsx) |
| FR-17: Scan QR → guest details | Task 8 (ScanController) + Task 13 (QrScanner) |
| FR-18: Record entry (one-time) | Task 8 (EntryController, idempotent) |
| FR-19: Mark lunch/dinner | Task 8 (MealController) |
| FR-20: One-time use per meal | Task 3 (recordMeal idempotency) |
| FR-21: Manual search fallback | Task 8 (GuestSearchController) + Task 13 (SearchFallback) |
| FR-22: Real-time dashboard | Task 11 (EventDashboardController + Filament widget) |
| FR-23: Attendance report | Task 11 (AttendanceExport) |
| FR-24: Duplicate entry log | Covered by 409 response codes |
| FR-25: No-show list | Task 11 (NoShowExport) |
| NFR-01: <500ms scan response | Task 9 (Redis caching) |
| NFR-03: Token auth + signed QR | Task 4 (Sanctum) + Task 3 (HMAC) |
| NFR-05: Audit trail | Task 3 (Spatie activitylog on all models) |
| NFR-06: 10k registrations/event | Covered by PostgreSQL indexes + Redis cache |

### Placeholder Scan
No TBD, TODO, or placeholder patterns found.

### Type Consistency
All method signatures, property names, and return types are consistent across tasks.
