<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>{{ $event->name }}</title>
<style>
    [data-ogsc] body, [data-ogsc] table, [data-ogsc] td { background-color:#ffffff !important; }
    [data-ogsc] p, [data-ogsc] span, [data-ogsc] div, [data-ogsc] a { color:inherit !important; }
</style>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:ui-sans-serif,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding:32px 16px;">
<tr>
<td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e2e8f0;">

    {{-- Header --}}
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

    {{-- Greeting --}}
    <tr>
        <td style="padding:32px 32px 8px;">
            <p style="margin:0 0 8px; color:#0f172b; font-size:16px;">Dear {{ $registration->displayName() }},</p>
            <p style="margin:0; color:#475569; font-size:15px; line-height:1.6;">
                You're invited to <strong style="color:#2e3192;">{{ $event->name }}</strong>. Present the QR code
                below at the entrance for check-in. Your full ticket is also attached to this email.
            </p>
        </td>
    </tr>

    {{-- QR --}}
    <tr>
        <td style="padding:24px 32px;">
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

    {{-- Details --}}
    <tr>
        <td style="padding:8px 32px 32px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e2e8f0; padding-top:20px;">
                <tr>
                    <td style="padding:6px 0; color:#64748b; font-size:13px; width:90px;">Date</td>
                    <td style="padding:6px 0; color:#0f172b; font-size:13px; font-weight:600;">
                        {{ $event->start_datetime?->format('l, F j, Y') ?? $event->event_date?->format('l, F j, Y') }}
                    </td>
                </tr>
                @if($event->venue)
                <tr>
                    <td style="padding:6px 0; color:#64748b; font-size:13px;">Venue</td>
                    <td style="padding:6px 0; color:#0f172b; font-size:13px; font-weight:600;">{{ $event->venue }}</td>
                </tr>
                @endif
            </table>
        </td>
    </tr>

    {{-- Footer --}}
    <tr>
        <td style="background-color:#f8fafc; padding:24px 24px; border-top:1px solid #e2e8f0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td valign="middle" style="width:44px;">
                        @if($logo = $event->logoUrl())
                            <img src="{{ $logo }}" alt="{{ $event->name }}" style="max-height:64px; display:block;">
                        @endif
                    </td>
                    <td valign="middle">
                        <p style="margin:0 12px 6px; color:#0f172b; font-size:13px;">We look forward to welcoming you! &#128591;</p>
                        <p style="margin:0 12px; color:#64748b; font-size:12px;">
                            Warm Regards,<br>
                            ICT Foundation Nepal
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

</table>
</td>
</tr>
</table>
</body>
</html>
