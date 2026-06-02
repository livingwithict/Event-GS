<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #dc2626; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; border: 1px solid #e5e7eb; border-top: none; }
        .alert { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; margin: 16px 0; }
        .footer { font-size: 12px; color: #666; text-align: center; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Urgent Update</h1>
            <p>{{ $event->name }}</p>
        </div>
        <div class="content">
            <p>Dear {{ $registration->name }},</p>
            <p>We have an important update regarding <strong>{{ $event->name }}</strong>.</p>

            <p>Please stay tuned for further details. If you have questions, contact us at {{ $event->contact_info ?? 'events@ictfoundation.org.np' }}.</p>
        </div>
        <div class="footer">
            <p>ICT Foundation Nepal</p>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
