<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $event->name }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:ui-sans-serif,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding:32px 16px;">
<tr>
<td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e2e8f0;">

    {{-- Header --}}
    <tr>
        <td style="background-color:#ffffff; padding:36px 32px 30px; text-align:center;">
            @if($logo = $event->logoUrl())
                <img src="{{ $logo }}" alt="{{ $event->name }}" style="max-height:64px; margin-bottom:18px;">
            @endif
            <div style="color:#121652; font-size:22px; font-weight:700; line-height:1.3;">{{ $event->name }}</div>
            <div style="display:inline-block; margin-top:12px; padding:4px 14px; background-color:#eb0000; border-radius:999px; color:#ffffff; font-size:12px; font-weight:600; letter-spacing:0.03em;">
                {{ $event->start_datetime?->format('F j, Y') ?? $event->event_date?->format('F j, Y') }}
            </div>
        </td>
    </tr>

    {{-- Accent bar --}}
    <tr><td style="height:2px; background-color:#2e3192;"></td></tr>

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
        <td style="background-color:#f8fafc; padding:20px 32px; text-align:center; border-top:1px solid #e2e8f0;">
            <p style="margin:0; color:#94a3b8; font-size:12px;">ICT Foundation Nepal</p>
        </td>
    </tr>

</table>
</td>
</tr>
</table>
</body>
</html>
