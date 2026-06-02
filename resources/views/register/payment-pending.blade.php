<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifying Payment — {{ $event->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; color: #111; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container { max-width: 480px; margin: 0 auto; padding: 16px; text-align: center; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 32px 24px; }
        .spinner { width: 40px; height: 40px; border: 4px solid #e5e7eb; border-top: 4px solid #2563eb; border-radius: 50%; margin: 0 auto 16px; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        h1 { font-size: 20px; margin-bottom: 8px; color: #2563eb; }
        p { color: #6b7280; font-size: 14px; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="spinner"></div>
            <h1>Verifying Payment...</h1>
            <p>We are confirming your payment with Connect IPS.<br>Please wait and do not close this page.</p>
        </div>
    </div>
</body>
</html>
