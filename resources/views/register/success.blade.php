<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registration Confirmed — {{ $event->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; color: #111; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container { max-width: 480px; margin: 0 auto; padding: 16px; text-align: center; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 32px 24px; }
        .icon { font-size: 48px; margin-bottom: 16px; }
        h1 { font-size: 22px; margin-bottom: 8px; color: #166534; }
        .guest-number { font-family: monospace; font-size: 24px; font-weight: 700; color: #2563eb; background: #eff6ff; padding: 12px; border-radius: 8px; margin: 16px 0; letter-spacing: 1px; }
         .event-info { color: #6b7280; font-size: 14px; margin-bottom: 8px; }
         .qr { margin: 20px auto 8px; width: 220px; }
         .qr svg { width: 100%; height: auto; }
        .message { color: #374151; font-size: 14px; margin-top: 16px; line-height: 1.5; }
        .btn { display: inline-block; margin-top: 20px; padding: 12px 28px; background: #1a56db; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; }
        .btn:hover { background: #1e40af; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="icon">&#10003;</div>
            <h1>Registration Confirmed!</h1>
            <div class="guest-number">{{ $guestNumber }}</div>
            <div class="event-info">
                {{ $event->name }}<br>
                {{ $event->start_datetime?->format('M j, Y H:i') ?? '' }}<br>
                {{ $event->venue }}
            </div>
            @if($qrSvg)
            <div class="qr">{!! $qrSvg !!}</div>
            <div class="message">{{ $registration?->name }}<br><strong>{{ $guestNumber }}</strong></div>
            <a href="{{ route('ticket.qr-print', $qrHash) }}" class="btn">Download Printable QR (6 × 8)</a>
            @endif
            @if($qrHash)
            <a href="{{ route('ticket.download', $qrHash) }}" class="btn">Download Your Ticket</a>
            @endif
            <p class="message">
                A confirmation has been sent to your email/phone.<br>
                Please keep your guest number for check-in.
            </p>
        </div>
    </div>
</body>
</html>
