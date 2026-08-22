<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $event?->name ?? 'Verification Complete' }}</title>
<style>
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation-duration: 0.001ms !important; animation-iteration-count: 1 !important; transition-duration: 0.001ms !important; }
    }

    :root {
        --ink: #17162a;
        --muted: #6b7280;
        --line: #e7e5ef;
        --indigo: #241c57;
        --paper: #ffffff;
        --bg: #f4f4f8;
    }

    * { box-sizing: border-box; }

    html, body {
        margin: 0;
        background: var(--bg);
        font-family: ui-sans-serif, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        color: var(--ink);
    }

    body {
        display: flex;
        justify-content: center;
        min-height: 100vh;
    }

    .screen {
        width: 100%;
        max-width: 430px;
        background: var(--paper);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    /* Header */
    .header {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 28px 20px 0;
    }

    .logo-wrap {
        text-align: center;
        padding: 20px 20px 16px;
    }

    .logo-wrap img {
        height: 108px;
        max-width: 80%;
        display: inline-block;
    }

    .contact-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 999px;
        background: #f1f1f6;
        color: var(--ink);
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
    }

    .contact-btn svg { width: 14px; height: 14px; }

    /* Intro */
    .intro { padding: 10px 24px 0; }

    .eyebrow {
        font-size: 14px;
        color: var(--muted);
        margin: 0 0 8px;
        opacity: 0;
        animation: fadeUp 0.5s ease-out 0.05s forwards;
    }

    h1 {
        margin: 0;
        font-size: 30px;
        line-height: 1.12;
        font-weight: 800;
        letter-spacing: -0.01em;
        color: var(--indigo);
        opacity: 0;
        animation: fadeUp 0.5s ease-out 0.12s forwards;
    }

    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .desc {
        margin: 22px 0 0;
        font-size: 18px;
        line-height: 1.55;
        color: #3f3d4c;
        opacity: 0;
        animation: fadeUp 0.5s ease-out 0.24s forwards;
    }

    /* Accordion */
    .accordion {
        margin: 30px 24px 0;
        border: 1px solid var(--line);
        border-radius: 12px;
        overflow: hidden;
        opacity: 0;
        animation: fadeUp 0.5s ease-out 0.3s forwards;
    }

    .accordion summary {
        list-style: none;
        cursor: pointer;
        padding: 18px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 14px;
        font-weight: 600;
        color: var(--ink);
    }

    .accordion summary::-webkit-details-marker { display: none; }

    .accordion summary .chev {
        width: 16px;
        height: 16px;
        stroke: var(--muted);
        stroke-width: 2;
        fill: none;
        transition: transform 0.2s ease;
        flex-shrink: 0;
    }

    .accordion[open] summary .chev { transform: rotate(180deg); }

    .accordion .panel {
        padding: 0 16px 20px 32px;
        margin: 0;
        font-size: 13.5px;
        color: var(--muted);
        line-height: 1.8;
    }

    .accordion .panel li { margin-bottom: 8px; }

    /* Confirmation stub */
    .stub {
        position: relative;
        margin: 36px 20px 28px;
        background: var(--paper);
        border-radius: 14px;
        box-shadow: 0 12px 32px -12px rgba(23,22,42,0.22);
        padding: 20px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        opacity: 0;
        animation: fadeUp 0.5s ease-out 0.55s forwards;
    }

    .stub-left { display: flex; align-items: center; gap: 12px; }

    .check-circle {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #eef2ff;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .check-circle svg {
        width: 18px;
        height: 18px;
        stroke: var(--indigo);
        stroke-width: 3;
        fill: none;
        stroke-linecap: round;
        stroke-linejoin: round;
        stroke-dasharray: 24;
        stroke-dashoffset: 24;
        animation: draw 0.4s ease-out 0.75s forwards;
    }

    @keyframes draw { to { stroke-dashoffset: 0; } }

    .stub-title { font-size: 14px; font-weight: 700; margin: 0; }
    .stub-sub { font-size: 12px; color: var(--muted); margin: 1px 0 0; }

    .stub-right { text-align: right; flex-shrink: 0; }
    .stub-date { font-size: 13px; font-weight: 700; margin: 0; }
    .stub-venue { font-size: 12px; color: var(--muted); margin: 1px 0 0; }

    /* Footer */
    .footer {
        text-align: center;
        padding: 10px 20px 36px;
        font-size: 11.5px;
        color: var(--muted);
    }

    .footer .brand {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-left: 5px;
        font-weight: 700;
        color: var(--ink);
    }

    .footer .brand span.dot {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: var(--indigo);
        display: inline-block;
    }
</style>
</head>
<body>
    <div class="screen">
        <div class="header">
            @php $contact = $event?->contact_info; @endphp
            @if($contact)
                @if(preg_match('/^[\d\s+()-]{6,}$/', $contact))
                    <a class="contact-btn" href="tel:{{ preg_replace('/[^\d+]/', '', $contact) }}">
                        Contact us
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </a>
                @else
                    <span class="contact-btn" style="cursor:default;">{{ $contact }}</span>
                @endif
            @endif
        </div>

        <div class="logo-wrap">
            <img src="{{ asset('dnc2026.png') }}" alt="{{ $event?->name ?? 'Digital Nepal Conclave' }}">
        </div>

        <div class="intro">
            <p class="eyebrow">See you at</p>
            <h1>{{ $event?->name ?? 'Your Event' }}</h1>

            <p class="desc">
                @if($registration)
                    Your identity has been verified, {{ $registration->displayName() }}. Your ticket is on its way to your inbox &mdash; see you there!
                @else
                    Your identity has been verified. Your ticket is on its way to your inbox &mdash; see you there!
                @endif
            </p>
        </div>

        <details class="accordion" open>
            <summary>
                What should you do before you arrive?
                <svg class="chev" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
            </summary>
            <ul class="panel">
                <li>Face verification is required at the entrance for smooth check-in.</li>
                <li>Present the QR code sent to your email for quick entry.</li>
            </ul>
        </details>

        <div class="stub">
            <div class="stub-left">
                <div class="check-circle">
                    <svg viewBox="0 0 24 24"><path d="M4 12.5 9.5 18 20 6"/></svg>
                </div>
                <div>
                    <p class="stub-title">You're In!</p>
                    <p class="stub-sub">See you!</p>
                </div>
            </div>
            <div class="stub-right">
                <p class="stub-date">{{ $event?->start_datetime?->format('d M Y') ?? $event?->event_date?->format('d M Y') ?? '' }}</p>
                <p class="stub-venue">{{ $event?->venue ?? '' }}</p>
            </div>
        </div>

        <!-- <div class="footer">
            Trusted with
            <span class="brand"><span class="dot"></span>thirdfactor.ai</span>
        </div> -->
    </div>
</body>
</html>
