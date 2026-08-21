<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>{{ $event->name }}</title>
<!-- <style>
    @media only screen and (max-width: 480px) {
        .event-logo { max-height: 100px !important; }
        .partner-logo { max-height: 150px !important; margin-left: 6px !important; }
        .footer-logo { max-height: 56px !important; }
        .header-cell { padding: 16px !important; }
    }
</style> -->
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

    <tr>
        <td style="padding:12px 32px 8px;">
            <p style="margin:0; color:#475569; font-size:15px; line-height:1.5;">
                Your verified identity will be securely linked to your invitation and used for seamless entry at the event. Face verification will be required at the entrance to confirm your attendance.
            </p>
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
