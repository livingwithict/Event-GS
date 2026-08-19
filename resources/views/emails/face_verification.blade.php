<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a56db; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; border: 1px solid #e5e7eb; border-top: none; }
        .footer { font-size: 12px; color: #666; text-align: center; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if($logo = $event->logoUrl())
                <img src="{{ $logo }}" alt="{{ $event->name }}" style="max-height:48px;margin-bottom:10px;">
            @endif
            <h1>{{ $event->name }}</h1>
            <p>{{ $event->start_datetime?->format('F j, Y') ?? $event->event_date?->format('F j, Y') }} | {{ $event->venue }}</p>
        </div>
        <div class="content">
            <p>Dear {{ $registration->displayName() }},</p>
            <p>You are invited to <strong>{{ $event->name }}</strong>. Before we send your ticket, please complete a quick face verification so we can check you in faster at the entrance:</p>
            @if(!empty($faceEnrollmentUrl))
                <p style="text-align:center;">
                    <a href="{{ $faceEnrollmentUrl }}" style="display:inline-block;background:#1a56db;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;">Complete face verification</a>
                </p>
                <p>Your ticket will be emailed to you automatically once verification is complete.</p>
            @else
                <p>We were unable to generate your verification link. Please contact us for assistance.</p>
            @endif
        </div>
        <div class="footer">
            <p>ICT Foundation Nepal</p>
        </div>
    </div>
</body>
</html>
