<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #059669; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; border: 1px solid #e5e7eb; border-top: none; }
        .success-badge { background: #ecfdf5; border: 2px solid #059669; border-radius: 8px; padding: 16px; text-align: center; margin: 16px 0; }
        .success-badge h3 { color: #059669; margin: 0 0 8px; }
        .details-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .details-table td { padding: 8px 12px; border-bottom: 1px solid #e5e7eb; }
        .details-table td:first-child { font-weight: 600; width: 40%; }
        .qr-code { text-align: center; margin: 20px 0; }
        .download-btn { display: inline-block; background: #059669; color: white; padding: 10px 24px; border-radius: 6px; text-decoration: none; font-weight: 600; margin: 16px 0; }
        .footer { font-size: 12px; color: #666; text-align: center; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if($logo = $event->logoUrl())
                <img src="{{ $logo }}" alt="{{ $event->name }}" style="max-height:48px;margin-bottom:10px;">
            @endif
            <h1>Payment Confirmed</h1>
            <p>{{ $event->name }}</p>
        </div>
        <div class="content">
            <p>Dear {{ $registration->displayName() }},</p>
            <p>Your payment for <strong>{{ $event->name }}</strong> has been successfully processed.</p>

            <div class="success-badge">
                <h3>Payment Successful</h3>
                @if($payment)
                <p style="margin:0;">Amount: NPR {{ number_format($payment->getAmountRupees(), 2) }}</p>
                <p style="margin:4px 0 0; font-size:12px; color:#666;">Ref: {{ $payment->transaction_id }}</p>
                @endif
            </div>

            <table class="details-table">
                <tr>
                    <td>Guest Number</td>
                    <td><strong>{{ $registration->guest_number }}</strong></td>
                </tr>
                <tr>
                    <td>Event</td>
                    <td>{{ $event->name }}</td>
                </tr>
                <tr>
                    <td>Date</td>
                    <td>{{ $event->start_datetime?->format('F j, Y') ?? $event->event_date?->format('F j, Y') }}</td>
                </tr>
                <tr>
                    <td>Venue</td>
                    <td>{{ $event->venue }}</td>
                </tr>
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
