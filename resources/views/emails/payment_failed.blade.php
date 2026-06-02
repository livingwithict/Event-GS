<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #dc2626; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; border: 1px solid #e5e7eb; border-top: none; }
        .alert { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; margin: 16px 0; }
        .retry-btn { display: inline-block; background: #1a56db; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600; margin: 16px 0; }
        .footer { font-size: 12px; color: #666; text-align: center; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Payment Failed</h1>
            <p>{{ $event->name }}</p>
        </div>
        <div class="content">
            <p>Dear {{ $registration->name }},</p>
            <p>We were unable to process your payment for <strong>{{ $event->name }}</strong>.</p>

            <div class="alert">
                <p style="margin:0;"><strong>What happened:</strong> The payment transaction could not be completed. This may be due to insufficient funds, a network issue, or the transaction being cancelled.</p>
            </div>

            <p><strong>What you can do:</strong></p>
            <ul>
                <li>Try the payment again using the retry link</li>
                <li>Ensure your bank account or wallet has sufficient balance</li>
                <li>Contact your bank if the issue persists</li>
            </ul>

            <p>If you need assistance, please contact us at {{ $event->contact_info ?? 'events@ictfoundation.org.np' }}.</p>
        </div>
        <div class="footer">
            <p>ICT Foundation Nepal</p>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
