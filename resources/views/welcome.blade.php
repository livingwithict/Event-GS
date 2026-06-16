<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Event Hub') }}</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; color: #111; }
        .container { max-width: 720px; margin: 0 auto; padding: 24px 16px; }
        .hero { background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff; border-radius: 12px; padding: 40px 32px; margin-bottom: 24px; text-align: center; }
        .hero h1 { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        .hero p { font-size: 14px; opacity: 0.9; }
        .events { display: grid; gap: 12px; }
        .event-card { background: #fff; border-radius: 10px; padding: 20px 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .event-info { flex: 1; min-width: 0; }
        .event-name { font-size: 16px; font-weight: 600; color: #111827; margin-bottom: 4px; }
        .event-meta { font-size: 13px; color: #6b7280; }
        .event-status { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .status-open { background: #d1fae5; color: #065f46; }
        .status-closed { background: #fee2e2; color: #991b1b; }
        .status-upcoming { background: #dbeafe; color: #1e40af; }
        .register-btn { display: inline-block; padding: 10px 20px; background: #2563eb; color: #fff; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; white-space: nowrap; transition: background 0.2s; }
        .register-btn:hover { background: #1d4ed8; }
        .register-btn.disabled { background: #9ca3af; pointer-events: none; }
        .empty { text-align: center; padding: 40px; color: #9ca3af; }
        .empty h2 { font-size: 18px; margin-bottom: 4px; color: #6b7280; }
        .admin-link { text-align: center; margin-top: 24px; }
        .admin-link a { color: #2563eb; font-size: 13px; text-decoration: none; }
        .admin-link a:hover { text-decoration: underline; }
    </style>
    @endif
</head>
<body>
    <div class="container">
        <div class="hero">
            <h1>{{ config('app.name', 'Event Hub') }}</h1>
            <p>Register for upcoming events and manage your attendance</p>
        </div>

        <div class="events">
            @forelse($events ?? [] as $event)
                <div class="event-card">
                    <div class="event-info">
                        <div class="event-name">{{ $event->name }}</div>
                        <div class="event-meta">
                            {{ $event->start_datetime?->format('M j, Y') ?? $event->event_date?->format('M j, Y') ?? 'TBA' }}
                            @if($event->venue)
                                &middot; {{ $event->venue }}
                            @endif
                        </div>
                    </div>
                    <div>
                        @if($event->isRegistrationOpen() && $event->settingEnabled('enable_self_registration') && !$event->isAtCapacity())
                            <a href="{{ route('register.show', $event->slug) }}" class="register-btn">Register</a>
                        @elseif($event->isAtCapacity() && $event->settingEnabled('enable_waitlist'))
                            <a href="{{ route('register.show', $event->slug) }}" class="register-btn">Join Waitlist</a>
                        @else
                            <span class="register-btn disabled">
                                {{ !$event->isRegistrationOpen() ? 'Closed' : 'Full' }}
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty">
                    <h2>No events available</h2>
                    <p>Check back later for upcoming events.</p>
                </div>
            @endforelse
        </div>

        <div class="admin-link">
            <a href="{{ url('/admin') }}">Admin Panel</a>
        </div>
    </div>
</body>
</html>
