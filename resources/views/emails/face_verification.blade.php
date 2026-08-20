<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $event->name }}</title>
<style>
    @media only screen and (max-width: 480px) {
        .event-logo { max-height: 56px !important; }
        .partner-logo { max-height: 44px !important; margin-left: 6px !important; }
        .footer-logo { max-height: 56px !important; }
        .header-cell { padding: 16px !important; }
    }
</style>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:ui-sans-serif,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding:32px 16px;">
<tr>
<td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e2e8f0;">

    {{-- Header: event logo + partner lockup --}}
    <tr>
        <td class="header-cell" style="padding:24px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="left" valign="middle">
                        @if($logo = $event->logoUrl())
                            <img src="{{ $logo }}" alt="{{ $event->name }}" class="event-logo" style="max-height:96px; display:block;">
                        @else
                            <span style="color:#121652; font-size:16px; font-weight:700;">{{ $event->name }}</span>
                        @endif
                    </td>
                    <td align="right" valign="middle">
                        @if(!empty($partnerLogos))
                            <table role="presentation" cellpadding="0" cellspacing="0" align="right">
                                <tr>
                                    <td align="center" style="padding-bottom:8px;">
                                        <span style="display:inline-block; padding:3px 10px; background-color:#f3e8ff; color:#7e22ce; border-radius:999px; font-size:10px; font-weight:700; letter-spacing:0.04em;">IN ASSOCIATION WITH</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center">
                                        @foreach($partnerLogos as $partnerLogo)
                                            <img src="{{ $partnerLogo }}" alt="Partner logo" class="partner-logo" style="max-height:64px; margin-left:10px; vertical-align:middle;">
                                        @endforeach
                                    </td>
                                </tr>
                            </table>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr><td style="height:1px; background-color:#e2e8f0;"></td></tr>

    {{-- Greeting + CTA --}}
    <tr>
        <td style="padding:32px 32px 8px;">
            <p style="margin:0 0 8px; color:#0f172b; font-size:16px;">Hi {{ $registration->name }},</p>
            <p style="margin:0 0 4px; color:#475569; font-size:15px; line-height:1.6;">
                You're invited to <strong style="color:#2e3192;">{{ $event->name }}</strong>.
            </p>
            <p style="margin:0; color:#475569; font-size:15px; line-height:1.6;">
                Complete a quick face verification to confirm your RSVP.
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding:20px 32px 8px;">
            @if(!empty($faceEnrollmentUrl))
                <a href="{{ $faceEnrollmentUrl }}"
                   style="display:inline-block; background-color:#2e3192; color:#ffffff; font-size:14px; font-weight:600; padding:12px 28px; border-radius:8px; text-decoration:none;">
                    Verify Now
                </a>
            @else
                <p style="margin:0; color:#b91c1c; font-size:13px;">We were unable to generate your verification link. Please contact us for assistance.</p>
            @endif
        </td>
    </tr>

    {{-- Event details --}}
    <tr>
        <td style="padding:24px 32px 24px;">
            <p style="margin:0; color:#0f172b; font-size:15px; font-weight:700;">{{ $event->name }}</p>
            <p style="margin:4px 0 0; color:#64748b; font-size:13px;">
                {{ $event->start_datetime?->format('F j, Y') ?? $event->event_date?->format('F j, Y') }}
                @if($event->venue) &middot; {{ $event->venue }} @endif
            </p>
        </td>
    </tr>

    {{-- Footer --}}
    <tr>
        <td style="background-color:#f8fafc; padding:24px 24px; border-top:1px solid #e2e8f0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td valign="middle" style="width:44px;">
                        @if($logo = $event->logoUrl())
                            <img src="{{ $logo }}" alt="{{ $event->name }}" class="footer-logo" style="max-height:64px; display:block;">
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
