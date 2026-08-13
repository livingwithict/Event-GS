<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #d97706; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; border: 1px solid #e5e7eb; border-top: none; }
        .reminder-box { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 16px; margin: 16px 0; text-align: center; }
        .details-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .details-table td { padding: 8px 12px; border-bottom: 1px solid #e5e7eb; }
        .details-table td:first-child { font-weight: 600; width: 40%; }
        .qr-code { text-align: center; margin: 20px 0; }
        .footer { font-size: 12px; color: #666; text-align: center; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if($logo = $event->logoUrl())
                <img src="{{ $logo }}" alt="{{ $event->name }}" style="max-height:48px;margin-bottom:10px;">
            @endif
            <h1>Event Reminder</h1>
            <p>{{ $event->name }}</p>
        </div>
        <div class="content">
            <p>Dear {{ $registration->displayName() }},</p>
            <p>This is a friendly reminder that <strong>{{ $event->name }}</strong> is happening soon!</p>

            <div class="reminder-box">
                <p style="margin:0; font-size:18px; font-weight:600;">{{ $event->start_datetime?->format('l, F j, Y') ?? $event->event_date?->format('l, F j, Y') }}</p>
                <p style="margin:4px 0 0;">{{ $event->start_datetime?->format('g:i A') ?? '' }} at {{ $event->venue }}</p>
            </div>

            <table class="details-table">
                <tr>
                    <td>Your Guest Number</td>
                    <td><strong>{{ $registration->guest_number }}</strong></td>
                </tr>
                <tr>
                    <td>Venue</td>
                    <td>{{ $event->venue }}</td>
                </tr>
                @if($registration->category)
                <tr>
                    <td>Category</td>
                    <td>{{ $registration->category->name }}</td>
                </tr>
                @endif
            </table>

            <p>Please keep your QR code handy for quick entry:</p>
            <div class="qr-code">
                {!! $qrCodeSvg !!}
            </div>

            <p>We look forward to seeing you there!</p>
        </div>
        <div class="footer">
            <p>ICT Foundation Nepal</p>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
