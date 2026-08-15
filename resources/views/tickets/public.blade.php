<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket — {{ $event->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; color: #111; display: flex; flex-direction: column; align-items: center; min-height: 100vh; padding: 16px; }
        .actions { margin-bottom: 16px; display: flex; gap: 12px; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 14px; text-decoration: none; }
        .btn-primary { background: #1a56db; color: white; }
        .btn-secondary { background: white; color: #374151; border: 1px solid #d1d5db; }
        .ticket-wrapper { background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden; max-width: 600px; width: 100%; }
    </style>
</head>
<body>
    <div class="actions">
        <a href="{{ route('ticket.download', $registration->qr_hash) }}" class="btn btn-primary" target="_blank" rel="noopener">Open PDF</a>
    </div>
    <div class="ticket-wrapper">
        {!! $html !!}
    </div>
</body>
</html>
