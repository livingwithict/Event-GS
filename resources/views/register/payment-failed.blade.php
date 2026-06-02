<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Failed — {{ $event->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; color: #111; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container { max-width: 480px; margin: 0 auto; padding: 16px; text-align: center; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 32px 24px; }
        .icon { font-size: 48px; margin-bottom: 16px; }
        h1 { font-size: 20px; margin-bottom: 8px; color: #dc2626; }
        p { color: #6b7280; font-size: 14px; margin-top: 8px; line-height: 1.5; }
        .txn { font-family: monospace; font-size: 12px; color: #9ca3af; margin-top: 16px; }
        .retry-btn { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; }
        .retry-btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="icon">&#10060;</div>
            <h1>Payment Failed</h1>
            <p>{{ $reason }}</p>
            <div class="txn">Transaction: {{ $payment->transaction_id }}</div>
            <form method="POST" action="{{ route('payment.retry', ['slug' => $event->slug, 'txnId' => $payment->transaction_id]) }}">
                @csrf
                <button type="submit" class="retry-btn">Try Again</button>
            </form>
        </div>
    </div>
</body>
</html>
