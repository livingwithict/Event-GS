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
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:680px; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e2e8f0;">

    {{-- Header --}}
    <tr>
        <td style="background-color:#ffffff; text-align:center;">
            <div style="padding:36px 32px 30px;"><span style="color:#121652; font-size:16px; font-weight:700;">{{ $event->name }}</span></div>
        </td>
    </tr>

    <tr><td style="height:1px; background-color:#e2e8f0;"></td></tr>

    {{-- Body --}}
    <tr>
        <td style="padding:32px 32px 8px;">
            <p style="margin:0 0 16px; color:#0f172b; font-size:16px;">Greetings!</p>

            <p style="margin:0 0 16px; color:#475569; font-size:15px; line-height:1.6;">
                We are deeply saddened by the loss of lives and damage to property caused by the recent flooding of the Bhotekoshi and Trishuli rivers.
            </p>

            <p style="margin:0 0 16px; color:#475569; font-size:15px; line-height:1.6;">
                In view of the prevailing circumstances, we regret to inform you that the
                <strong style="color:#2e3192;">Digital Nepal Conclave 2026</strong>, scheduled for
                <strong style="color:#0f172b;">27 August 2026 (11 Bhadra 2083)</strong>, has been
                <strong style="color:#dc2626;">postponed</strong> until further notice.
            </p>

            <p style="margin:0 0 16px; color:#475569; font-size:15px; line-height:1.6;">
                Following a review of the prevailing situation, we are tentatively looking at the
                <strong style="color:#0f172b;">third week of September 2026</strong> for the Conclave. The exact
                date and further details will be confirmed and updated in the coming days.
            </p>

            <p style="margin:0 0 16px; color:#475569; font-size:15px; line-height:1.6;">
                Please note that all tickets already purchased, as well as tickets purchased during this period,
                <strong style="color:#0f172b;">will remain valid</strong> for the rescheduled date of the Conclave.
                No additional action will be required from ticket holders in this regard. For details, please visit
                us at <a href="https://digitalconclave.org/" style="color:#2e3192;">digitalconclave.org</a>.
            </p>

            <p style="margin:0 0 16px; color:#475569; font-size:15px; line-height:1.6;">
                We sincerely apologize for the inconvenience caused to our participants, resource persons, partners,
                collaborators and well-wishers.
            </p>

            <p style="margin:0; color:#475569; font-size:15px; line-height:1.6;">
                Should you have any queries or require further assistance, please feel free to contact us at +977 9801263604.
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
                        <p style="margin:0 12px; color:#0f172b; font-size:13px;">
                            Regards,<br>
                            Digital Nepal Conclave 2026 Organizing Team
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
