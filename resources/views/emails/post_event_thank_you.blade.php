<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a56db; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; border: 1px solid #e5e7eb; border-top: none; }
        .thank-you { text-align: center; margin: 20px 0; }
        .thank-you h2 { color: #1a56db; }
        .footer { font-size: 12px; color: #666; text-align: center; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Thank You!</h1>
            <p>{{ $event->name }}</p>
        </div>
        <div class="content">
            <p>Dear {{ $registration->displayName() }},</p>
            <p>Thank you for attending <strong>{{ $event->name }}</strong>!</p>

            <div class="thank-you">
                <h2>We hope you had a great experience.</h2>
                <p>Your participation made the event a success. We look forward to seeing you at future events organized by ICT Foundation Nepal.</p>
            </div>

            <p>If you have any feedback or suggestions, please don't hesitate to reach out to us at {{ $event->contact_info ?? 'events@ictfoundation.org.np' }}.</p>

            <p>Stay connected for upcoming events!</p>
        </div>
        <div class="footer">
            <p>ICT Foundation Nepal</p>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
