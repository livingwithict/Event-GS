<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a56db; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; border: 1px solid #e5e7eb; border-top: none; }
        .guest-number { background: #f0f5ff; border: 2px solid #1a56db; border-radius: 8px; padding: 12px; text-align: center; margin: 16px 0; }
        .guest-number code { font-size: 24px; font-weight: bold; color: #1a56db; }
        .details-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .details-table td { padding: 8px 12px; border-bottom: 1px solid #e5e7eb; }
        .details-table td:first-child { font-weight: 600; width: 40%; }
        .qr-code { text-align: center; margin: 20px 0; }
        .download-btn { display: inline-block; background: #1a56db; color: white; padding: 10px 24px; border-radius: 6px; text-decoration: none; font-weight: 600; margin: 16px 0; }
        .footer { font-size: 12px; color: #666; text-align: center; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Registration Confirmed</h1>
            <p>{{ $event->name }}</p>
        </div>
        <div class="content">
            <p>Dear {{ $registration->name }},</p>
            <p>Your registration for <strong>{{ $event->name }}</strong> has been confirmed. We're excited to have you!</p>

            <div class="guest-number">
                <p style="margin:0 0 4px;">Your Guest Number</p>
                <code>{{ $registration->guest_number }}</code>
            </div>

            <table class="details-table">
                <tr>
                    <td>Event</td>
                    <td>{{ $event->name }}</td>
                </tr>
                <tr>
                    <td>Date</td>
                    <td>{{ $event->start_datetime?->format('F j, Y') ?? $event->event_date?->format('F j, Y') }}</td>
                </tr>
                @if($event->start_datetime)
                <tr>
                    <td>Time</td>
                    <td>{{ $event->start_datetime->format('g:i A') }}</td>
                </tr>
                @endif
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

            <p>Please present the QR code below at the entrance, or download your ticket PDF (attached to this email):</p>
            <div class="qr-code">
                {!! $qrCodeSvg !!}
            </div>

            @if($registration->qr_hash)
            <div style="text-align:center;">
                <a href="{{ config('app.url') }}/ticket/{{ $registration->qr_hash }}/download" class="download-btn">Download Your Ticket</a>
            </div>
            @endif
        </div>
        <div class="footer">
            <p>ICT Foundation Nepal</p>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
