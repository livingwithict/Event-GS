<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $event->name }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:ui-sans-serif,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding:32px 16px;">
<tr>
<td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e2e8f0;">

    <tr>
        <td style="text-align:center;">
            @if($logo = $partnerLogo ?? $event->logoUrl())
                <img src="{{ $logo }}" alt="{{ $event->name }}" style="width:100%; display:block;">
            @else
                <div style="padding:36px 32px 30px;"><span style="color:#0f172b; font-size:16px; font-weight:700;">{{ $event->name }}</span></div>
            @endif
        </td>
    </tr>

    <tr><td style="height:1px; background-color:#e2e8f0;"></td></tr>

    <tr>
        <td style="padding:32px;">
            <h1 style="margin:0 0 16px; color:#0f172b; font-size:20px;">{{ $copy[0] }}</h1>
            <p style="margin:0 0 8px; color:#0f172b; font-size:16px;">Dear {{ $registration->displayName() }},</p>
            <p style="margin:0; color:#475569; font-size:15px; line-height:1.6;">{{ str_replace(':event', $event->name, $copy[1]) }}</p>
        </td>
    </tr>

    @if(!empty($faceEnrollmentUrl))
    <tr>
        <td style="padding:0 32px 24px;">
            <a href="{{ $faceEnrollmentUrl }}" style="display:inline-block; background-color:#1a56db; color:#ffffff; font-size:14px; font-weight:600; padding:12px 28px; border-radius:8px; text-decoration:none;">Verify Now</a>
        </td>
    </tr>
    @endif

    @if(!empty($showQr) && $registration->qr_hash)
    <tr>
        <td style="padding:0 32px 24px; text-align:center;">
            <img src="{{ route('ticket.qr-image', $registration->qr_hash) }}" alt="Entry QR code" width="180" height="180" style="display:block; margin:0 auto; width:180px; height:180px;">
            <div style="margin-top:12px; color:#0f172b; font-size:14px; font-weight:700;">{{ $registration->guest_number }}</div>
        </td>
    </tr>
    @endif

    <tr>
        <td style="padding:0 32px 32px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e2e8f0;">
                <tr>
                    <td style="padding:10px 0 4px; color:#64748b; font-size:13px; width:90px;">Date</td>
                    <td style="padding:10px 0 4px; color:#0f172b; font-size:13px; font-weight:600;">{{ $event->start_datetime?->format('l, F j, Y') ?? $event->event_date?->format('l, F j, Y') }}</td>
                </tr>
                @if($event->venue)
                <tr>
                    <td style="padding:4px 0; color:#64748b; font-size:13px;">Venue</td>
                    <td style="padding:4px 0; color:#0f172b; font-size:13px; font-weight:600;">{{ $event->venue }}</td>
                </tr>
                @endif
            </table>
        </td>
    </tr>

    <tr>
        <td style="background-color:#f8fafc; padding:24px; border-top:1px solid #e2e8f0;">
            <p style="margin:0; color:#64748b; font-size:12px;">Warm Regards,<br>{{ $event->name }} Team</p>
        </td>
    </tr>

</table>
</td>
</tr>
</table>
</body>
</html>
