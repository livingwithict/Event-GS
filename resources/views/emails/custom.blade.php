<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>{{ $event->name }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:ui-sans-serif,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding:32px 16px;">
<tr>
<td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e2e8f0;">

    <tr>
        <td style="background-color:#ffffff; text-align:center;">
            @if($logo = $partnerLogo ?? $event->logoUrl())
                <img src="{{ $logo }}" alt="{{ $event->name }}" style="width:100%; display:block;">
            @else
                <div style="padding:36px 32px 30px;"><span style="color:#121652; font-size:16px; font-weight:700;">{{ $event->name }}</span></div>
            @endif
        </td>
    </tr>

    <tr><td style="height:1px; background-color:#e2e8f0;"></td></tr>

    {{-- Body written in the dashboard (already sanitised by RichContentRenderer) --}}
    <tr>
        <td style="padding:32px; color:#475569; font-size:15px; line-height:1.6;">
            {!! $body !!}
        </td>
    </tr>

    @if(!empty($faceEnrollmentUrl))
    <tr>
        <td style="padding:0 32px 24px;">
            <a href="{{ $faceEnrollmentUrl }}"
               style="display:inline-block; background-color:#2e3192; color:#ffffff; font-size:14px; font-weight:600; padding:12px 28px; border-radius:8px; text-decoration:none;">
                Verify Now
            </a>
        </td>
    </tr>
    @endif

    @if(!empty($showQr))
    <tr>
        <td style="padding:0 32px 32px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;">
                <tr>
                    <td style="padding:28px; text-align:center;">
                        <img src="{{ route('ticket.qr-image', $registration->qr_hash) }}"
                             alt="Entry QR code" width="180" height="180"
                             style="display:block; margin:0 auto; width:180px; height:180px;">
                        <div style="margin-top:14px; color:#2e3192; font-size:14px; font-weight:700; letter-spacing:0.02em;">
                            {{ $registration->guest_number }}
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    @endif

    <tr>
        <td style="background-color:#f8fafc; padding:24px; border-top:1px solid #e2e8f0;">
            <p style="margin:0; color:#64748b; font-size:12px;">
                Warm Regards,<br>
                ICT Foundation Nepal
            </p>
        </td>
    </tr>

</table>
</td>
</tr>
</table>
</body>
</html>
