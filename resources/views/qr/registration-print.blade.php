<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: 6in 8in; margin: 0; }
        * { box-sizing: border-box; }
        body { width: 6in; height: 8in; margin: 0; padding: 0.35in; overflow: hidden; font-family: Helvetica, Arial, sans-serif; color: #111827; text-align: center; }
        .event { font-size: 20px; font-weight: 700; margin-bottom: 0.1in; }
        .details { color: #4b5563; font-size: 12px; margin-bottom: 0.18in; }
        .qr { width: 3.9in; height: 3.9in; margin: 0 auto 0.18in; }
        .qr img { width: 100%; height: 100%; }
        .name { font-size: 22px; font-weight: 700; margin-bottom: 0.08in; }
        .guest-number { display: inline-block; border: 2px solid #1d4ed8; border-radius: 6px; color: #1d4ed8; font-family: 'Courier New', monospace; font-size: 18px; font-weight: 700; letter-spacing: 2px; padding: 0.08in 0.18in; }
        .category { color: #6b7280; font-size: 13px; margin-top: 0.08in; }
        .hint { color: #6b7280; font-size: 10px; margin-top: 0.16in; }
    </style>
</head>
<body>
    <div class="event">{{ $event->name }}</div>
    <div class="details">
        {{ $event->start_datetime?->format('M j, Y H:i') ?? '' }}
        @if($event->venue) &middot; {{ $event->venue }} @endif
    </div>
    <div class="qr">
        <img src="data:image/svg+xml;base64,{{ base64_encode($qrSvg) }}" alt="QR code">
    </div>
    <div class="name">{{ $registration->name }}</div>
    <div class="guest-number">{{ $registration->guest_number }}</div>
    @if($category)
        <div class="category">{{ $category->name }}</div>
    @endif
    <div class="hint">Scan this QR code at the event entrance.</div>
</body>
</html>
