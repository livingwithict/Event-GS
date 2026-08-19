<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $event->name }}</title>
</head>
<body style="margin:0; padding:0; background-color:#eef1f6; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef1f6; padding:32px 16px;">
<tr>
<td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 1px 3px rgba(16,24,40,0.08);">

    {{-- Header --}}
    <tr>
        <td style="background-color:#1a56db; padding:32px 32px 28px; text-align:center;">
            @if($logo = $event->logoUrl())
                <img src="{{ $logo }}" alt="{{ $event->name }}" style="max-height:48px; margin-bottom:16px;">
            @endif
            <div style="color:#ffffff; font-size:22px; font-weight:700; line-height:1.3;">{{ $event->name }}</div>
            <div style="color:#c7d7fb; font-size:14px; margin-top:6px;">
                {{ $event->start_datetime?->format('F j, Y') ?? $event->event_date?->format('F j, Y') }}
                @if($event->venue) &middot; {{ $event->venue }} @endif
            </div>
        </td>
    </tr>

    {{-- Greeting --}}
    <tr>
        <td style="padding:32px 32px 8px;">
            <p style="margin:0 0 8px; color:#101828; font-size:16px;">Dear {{ $registration->displayName() }},</p>
            <p style="margin:0; color:#475467; font-size:15px; line-height:1.6;">
                You're invited to <strong style="color:#101828;">{{ $event->name }}</strong>. Your ticket is below —
                save it or bring this email, and present the QR code at the entrance.
            </p>
        </td>
    </tr>

    {{-- Ticket --}}
    <tr>
        <td style="padding:24px 32px;">
            @if(!empty($ticketJpeg))
                <div style="text-align:center; border-radius:12px; overflow:hidden; border:1px solid #e4e7ec;">
                    <img src="{{ $message->embedData($ticketJpeg, 'ticket.jpg', 'image/jpeg') }}"
                         alt="Your ticket for {{ $event->name }}"
                         width="100%" style="display:block; max-width:100%; height:auto;">
                </div>
            @else
                <div style="text-align:center; padding:24px; border:1px solid #e4e7ec; border-radius:12px;">
                    {!! $qrCodeSvg !!}
                    <p style="margin:12px 0 0; color:#667085; font-size:13px;">Guest #{{ $registration->guest_number }}</p>
                </div>
            @endif
        </td>
    </tr>

    {{-- Details --}}
    <tr>
        <td style="padding:8px 32px 32px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #eaecf0; padding-top:20px;">
                <tr>
                    <td style="padding:6px 0; color:#667085; font-size:13px; width:90px;">Date</td>
                    <td style="padding:6px 0; color:#101828; font-size:13px; font-weight:600;">
                        {{ $event->start_datetime?->format('l, F j, Y') ?? $event->event_date?->format('l, F j, Y') }}
                    </td>
                </tr>
                @if($event->venue)
                <tr>
                    <td style="padding:6px 0; color:#667085; font-size:13px;">Venue</td>
                    <td style="padding:6px 0; color:#101828; font-size:13px; font-weight:600;">{{ $event->venue }}</td>
                </tr>
                @endif
                <tr>
                    <td style="padding:6px 0; color:#667085; font-size:13px;">Guest ID</td>
                    <td style="padding:6px 0; color:#101828; font-size:13px; font-weight:600;">{{ $registration->guest_number }}</td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- Footer --}}
    <tr>
        <td style="background-color:#f9fafb; padding:20px 32px; text-align:center; border-top:1px solid #eaecf0;">
            <p style="margin:0; color:#98a2b3; font-size:12px;">ICT Foundation Nepal</p>
        </td>
    </tr>

</table>
</td>
</tr>
</table>
</body>
</html>
