@php
    $statusFor = function ($event) {
        $ended = $event->end_datetime
            ? $event->end_datetime->isPast()
            : ($event->start_datetime?->copy()->endOfDay()->isPast() ?? false);

        if ($ended) {
            return ['label' => 'Ended', 'class' => 'muted', 'cta' => null];
        }
        if ($event->isAtCapacity() && $event->settingEnabled('enable_waitlist')) {
            return ['label' => 'At capacity', 'class' => 'warn', 'cta' => 'Join the waitlist'];
        }
        if ($event->isAtCapacity()) {
            return ['label' => 'At capacity', 'class' => 'muted', 'cta' => null];
        }
        if (! $event->isRegistrationOpen() || ! $event->settingEnabled('enable_self_registration')) {
            return ['label' => 'Registration closed', 'class' => 'muted', 'cta' => null];
        }

        return ['label' => 'Registration open', 'class' => 'open', 'cta' => 'Register'];
    };

    $openCount = $events->filter(fn ($e) => $e->isRegistrationOpen() && ! $e->isAtCapacity())->count();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Event Hub') }} — Events</title>
    <meta name="description" content="Register for events run by ICT Foundation Nepal. One invitation code covers the entrance, lunch and dinner.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue: #1a56db;
            --blue-dark: #1e40af;
            --ink: #111827;
            --body: #4b5563;
            --muted: #9ca3af;
            --line: #e5e7eb;
            --tint: #f8faff;
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Instrument Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #fff;
            color: var(--body);
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }

        .wrap { max-width: 820px; margin: 0 auto; padding: 0 20px; }

        /* header */
        header {
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            padding: 18px 0;
        }
        .brand { display: flex; align-items: center; gap: 9px; font-weight: 600; color: var(--ink); }
        .brand span.mark {
            width: 26px; height: 26px; border-radius: 7px; background: var(--blue);
            display: grid; place-items: center; color: #fff; font-size: 13px; font-weight: 700;
        }
        header a.signin { font-size: 14px; color: var(--body); text-decoration: none; }
        header a.signin:hover { color: var(--blue); }

        /* hero */
        .hero {
            background: linear-gradient(160deg, #f5f8ff 0%, #eef3ff 55%, #f7f9ff 100%);
            border: 1px solid #e4ebfb;
            border-radius: 14px;
            padding: 40px 36px;
            margin: 8px 0 44px;
        }
        .hero h1 {
            font-size: clamp(1.75rem, 4vw, 2.35rem); font-weight: 700; letter-spacing: -0.025em;
            color: var(--ink); line-height: 1.15;
        }
        .hero p { margin-top: 12px; max-width: 52ch; color: #52607a; }
        .hero .meta {
            margin-top: 20px; display: flex; align-items: center; gap: 8px;
            font-size: 14px; color: #52607a;
        }
        .hero .meta b { color: var(--ink); font-weight: 600; }
        .pip { width: 7px; height: 7px; border-radius: 50%; background: #10b981; }

        /* section */
        .section-title {
            display: flex; align-items: baseline; justify-content: space-between; gap: 12px;
            margin-bottom: 14px;
        }
        .section-title h2 { font-size: 15px; font-weight: 600; color: var(--ink); }
        .section-title span { font-size: 13px; color: var(--muted); }

        /* event cards */
        ul { list-style: none; display: grid; gap: 10px; }
        li.event {
            display: flex; align-items: center; gap: 20px;
            padding: 18px 20px;
            border: 1px solid var(--line); border-radius: 12px; background: #fff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        li.event:hover { border-color: #c7d7f7; box-shadow: 0 4px 14px -8px rgba(26, 86, 219, 0.35); }
        li.event.past { background: #fcfcfd; }

        .date {
            flex-shrink: 0; width: 62px; text-align: center;
            border-right: 1px solid var(--line); padding-right: 16px;
        }
        .date .d { display: block; font-size: 22px; font-weight: 700; color: var(--ink); line-height: 1.1; }
        .date .m { display: block; font-size: 12px; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--blue); }
        .date .y { display: block; font-size: 11px; color: var(--muted); margin-top: 1px; }
        li.event.past .date .m { color: var(--muted); }

        .info { flex: 1; min-width: 0; }
        .info h3 { font-size: 16.5px; font-weight: 600; color: var(--ink); line-height: 1.3; }
        .info .where { font-size: 13.5px; color: var(--body); margin-top: 3px; }
        li.event.past .info h3 { color: #6b7280; }

        .pill {
            display: inline-flex; align-items: center; gap: 6px; margin-top: 8px;
            font-size: 12px; font-weight: 500; padding: 3px 9px; border-radius: 999px;
        }
        .pill::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
        .pill.open { background: #ecfdf5; color: #047857; }
        .pill.warn { background: #fffbeb; color: #b45309; }
        .pill.muted { background: #f3f4f6; color: #6b7280; }

        .action { flex-shrink: 0; }
        .btn {
            display: inline-block; white-space: nowrap; text-decoration: none;
            padding: 9px 18px; border-radius: 8px; font-size: 14px; font-weight: 600;
            background: var(--blue); color: #fff;
        }
        .btn:hover { background: var(--blue-dark); }
        .btn.secondary { background: #fff; color: var(--blue); box-shadow: inset 0 0 0 1px #c7d7f7; }
        .btn.secondary:hover { background: var(--tint); }
        .action .note { font-size: 13px; color: var(--muted); white-space: nowrap; }

        /* empty + footer */
        .empty {
            padding: 48px 20px; text-align: center; border: 1px dashed var(--line); border-radius: 12px;
        }
        .empty strong { display: block; font-size: 16px; color: var(--ink); margin-bottom: 4px; }
        .empty span { color: var(--muted); font-size: 14px; }

        footer {
            margin-top: 48px; padding: 20px 0 40px; border-top: 1px solid var(--line);
            display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap;
            font-size: 13px; color: var(--muted);
        }
        footer a { color: var(--body); text-decoration: none; }
        footer a:hover { color: var(--blue); }

        a:focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }

        @media (max-width: 600px) {
            .hero { padding: 28px 22px; }
            li.event { flex-wrap: wrap; gap: 14px; }
            .date {
                width: auto; display: flex; align-items: baseline; gap: 6px;
                border-right: 0; border-bottom: 0; padding-right: 0;
            }
            .date .d, .date .m, .date .y { display: inline; font-size: 14px; }
            .date .d { font-size: 15px; }
            .info { flex: 1 1 100%; }
            .action { flex: 1 1 100%; }
            .btn { display: block; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <span class="brand">
                <span class="mark">{{ Str::substr(config('app.name', 'E'), 0, 1) }}</span>
                {{ config('app.name', 'Event Hub') }}
            </span>
            <a class="signin" href="{{ url('/admin') }}">Staff sign in</a>
        </header>

        <section class="hero">
            <h1>Register once.<br>Scan in at every door.</h1>
            <p>
                Pick an event below and you'll get an invitation code with a QR pass —
                that's your entrance, your lunch and your dinner, no paperwork at the desk.
            </p>
            @if ($openCount > 0)
                <div class="meta">
                    <span class="pip"></span>
                    <span><b>{{ $openCount }}</b> {{ Str::plural('event', $openCount) }} open for registration</span>
                </div>
            @endif
        </section>

        <div class="section-title">
            <h2>Schedule</h2>
            <span>{{ $events->count() }} {{ Str::plural('event', $events->count()) }}</span>
        </div>

        <ul>
            @forelse ($events as $event)
                @php $status = $statusFor($event); @endphp
                <li class="event {{ $status['label'] === 'Ended' ? 'past' : '' }}">
                    <div class="date">
                        <span class="d">{{ $event->start_datetime?->format('j') ?? '—' }}</span>
                        <span class="m">{{ $event->start_datetime?->format('M') ?? 'TBA' }}</span>
                        <span class="y">{{ $event->start_datetime?->format('Y') }}</span>
                    </div>

                    <div class="info">
                        <h3>{{ $event->name }}</h3>
                        <div class="where">
                            {{ $event->start_datetime?->format('H:i') }}@if($event->venue) · {{ $event->venue }}@endif
                        </div>
                        <span class="pill {{ $status['class'] }}">{{ $status['label'] }}</span>
                    </div>

                    <div class="action">
                        @if ($status['cta'])
                            <a class="btn {{ $status['class'] === 'warn' ? 'secondary' : '' }}"
                               href="{{ route('register.show', $event->slug) }}">{{ $status['cta'] }}</a>
                        @else
                            <span class="note">{{ $status['label'] }}</span>
                        @endif
                    </div>
                </li>
            @empty
                <li class="empty">
                    <strong>Nothing on the schedule yet</strong>
                    <span>New events appear here as soon as registration opens.</span>
                </li>
            @endforelse
        </ul>

        <footer>
            <span>{{ config('app.name', 'Event Hub') }} · ICT Foundation Nepal</span>
            <a href="{{ url('/admin') }}">Staff sign in</a>
        </footer>
    </div>
</body>
</html>
