<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoiceNumber }} — {{ $event->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; padding: 40px; color: #1a1a1a; }
        .header { border-bottom: 3px solid #2563eb; padding-bottom: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-start; }
        .header h1 { font-size: 28px; color: #2563eb; }
        .header .invoice-title { font-size: 14px; color: #6b7280; text-align: right; }
        .header .invoice-number { font-size: 18px; font-weight: 700; color: #1a1a1a; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 30px; }
        .info-box h3 { font-size: 12px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; margin-bottom: 8px; }
        .info-box p { font-size: 14px; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th { background: #f3f4f6; padding: 10px 12px; text-align: left; font-size: 12px; text-transform: uppercase; color: #6b7280; }
        td { padding: 12px; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
        .total { text-align: right; margin-top: 20px; }
        .total .amount { font-size: 24px; font-weight: 700; color: #2563eb; }
        .footer { margin-top: 60px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af; text-align: center; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .status-success { background: #d1fae5; color: #065f46; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>{{ config('app.name') }}</h1>
            <p style="font-size:14px;color:#6b7280;margin-top:4px;">{{ $event->name }}</p>
        </div>
        <div class="invoice-title">
            <div class="invoice-number">INVOICE</div>
            <div style="font-size:16px;font-weight:600;margin-top:4px;">{{ $invoiceNumber }}</div>
            <div style="font-size:12px;color:#6b7280;margin-top:4px;">Issued: {{ $payment->paid_at?->format('M j, Y') ?? now()->format('M j, Y') }}</div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <h3>Bill To</h3>
            <p>
                <strong>{{ $registration->name }}</strong><br>
                @if($registration->organization){{ $registration->organization }}<br>@endif
                @if($registration->email){{ $registration->email }}<br>@endif
                @if($registration->phone){{ $registration->phone }}<br>@endif
                @if($registration->address){{ $registration->address }}@endif
            </p>
        </div>
        <div class="info-box">
            <h3>Event Details</h3>
            <p>
                <strong>{{ $event->name }}</strong><br>
                @if($event->venue){{ $event->venue }}<br>@endif
                @if($event->start_datetime){{ $event->start_datetime->format('M j, Y H:i') }}@endif
            </p>
            @if($registration->guest_number)
            <p style="margin-top:8px;">
                <strong>Guest #:</strong> {{ $registration->guest_number }}
            </p>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Category</th>
                <th style="text-align:right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Event Registration — {{ $event->name }}</td>
                <td>{{ $registration->category?->name ?? 'N/A' }}</td>
                <td style="text-align:right;">{{ number_format($amount, 2) }} {{ $payment->currency }}</td>
            </tr>
        </tbody>
    </table>

    <div class="total">
        <div style="font-size:14px;color:#6b7280;">Total Amount</div>
        <div class="amount">{{ number_format($amount, 2) }} {{ $payment->currency }}</div>
        <div style="margin-top:12px;">
            <span class="status-badge status-success">PAID</span>
        </div>
    </div>

    <div class="footer">
        <p>{{ config('app.name') }} &bull; {{ config('app.url') }}</p>
        <p>This is a computer-generated invoice.</p>
    </div>
</body>
</html>
