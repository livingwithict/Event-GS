<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a56db; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; border: 1px solid #e5e7eb; border-top: none; }
        .qr-code { text-align: center; margin: 20px 0; }
        .footer { font-size: 12px; color: #666; text-align: center; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $event->name }}</h1>
            <p>{{ $event->start_datetime?->format('F j, Y') ?? $event->event_date?->format('F j, Y') }} | {{ $event->venue }}</p>
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
