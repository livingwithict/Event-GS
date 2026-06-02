<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registration — {{ $event->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; color: #111; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container { max-width: 480px; margin: 0 auto; padding: 16px; text-align: center; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 32px 24px; }
        .icon { font-size: 48px; margin-bottom: 16px; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        p { color: #6b7280; font-size: 14px; margin-top: 8px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="icon">&#128274;</div>
            <h1>{{ $event->name }}</h1>
            <p>{{ $reason }}</p>
            @if($event->contact_info)
                <p style="margin-top: 16px;">Contact: {{ $event->contact_info }}</p>
            @endif
        </div>
    </div>
</body>
</html>
