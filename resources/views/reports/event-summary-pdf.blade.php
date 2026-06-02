<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; color: #1f2937; margin: 0; padding: 20mm; }
        h1 { font-size: 22px; color: #111827; margin-bottom: 4px; }
        h2 { font-size: 16px; color: #374151; margin: 20px 0 10px; border-bottom: 2px solid #1a56db; padding-bottom: 4px; }
        .subtitle { color: #6b7280; font-size: 13px; margin-bottom: 20px; }
        .stats-grid { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
        .stat-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px 16px; width: 30%; }
        .stat-card .value { font-size: 20px; font-weight: 700; color: #111827; }
        .stat-card .label { font-size: 10px; color: #6b7280; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { background: #1a56db; color: white; padding: 8px 12px; text-align: left; font-size: 11px; }
        td { padding: 6px 12px; border-bottom: 1px solid #e5e7eb; font-size: 11px; }
        tr:nth-child(even) td { background: #f9fafb; }
        .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $event->name }}</h1>
    <div class="subtitle">
        Event Summary Report &mdash;
        {{ $event->start_datetime?->format('F j, Y') ?? $event->event_date?->format('F j, Y') }}
        &bull; {{ $event->venue }}
    </div>

    <h2>Overview</h2>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="value">{{ $totalRegistrations }}</div>
            <div class="label">Total Registrations</div>
        </div>
        <div class="stat-card">
            <div class="value">{{ $stats['total_entries'] }}</div>
            <div class="label">Checked In</div>
        </div>
        <div class="stat-card">
            <div class="value">{{ $noShows }}</div>
            <div class="label">No-Shows</div>
        </div>
        <div class="stat-card">
            <div class="value">{{ $stats['lunch_used'] }}</div>
            <div class="label">Lunch Used</div>
        </div>
        <div class="stat-card">
            <div class="value">{{ $stats['dinner_used'] }}</div>
            <div class="label">Dinner Used</div>
        </div>
        <div class="stat-card">
            <div class="value">{{ $totalRegistrations > 0 ? round(($stats['total_entries'] / $totalRegistrations) * 100, 1) : 0 }}%</div>
            <div class="label">Attendance Rate</div>
        </div>
    </div>

    <h2>Category Breakdown</h2>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th>Type</th>
                <th>Price</th>
                <th>Registrations</th>
            </tr>
        </thead>
        <tbody>
            @foreach($categories as $category)
            <tr>
                <td>{{ $category->name }}</td>
                <td>{{ $category->is_paid ? 'Paid' : 'Free' }}</td>
                <td>{{ $category->is_paid ? 'NPR '.number_format($category->price, 0) : '-' }}</td>
                <td>{{ $category->registrations_count }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Payment Summary</h2>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="value">NPR {{ number_format($paymentStats['collected'], 0) }}</div>
            <div class="label">Total Collected</div>
        </div>
        <div class="stat-card">
            <div class="value">NPR {{ number_format($paymentStats['pending'], 0) }}</div>
            <div class="label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="value">{{ $paymentStats['failed'] }}</div>
            <div class="label">Failed Payments</div>
        </div>
    </div>

    @if($dailyBreakdown)
    <h2>Daily Attendance Breakdown</h2>
    <table>
        <thead>
            <tr>
                <th>Day</th>
                <th>Date</th>
                <th>Action</th>
                <th>Unique Scans</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dailyBreakdown as $dayData)
                @php
                    $dayActions = App\Models\ScanActionType::where('event_id', $event->id)
                        ->where('action_code', 'LIKE', 'DAY'.$dayData['day'].'_%')
                        ->active()
                        ->orderBy('action_code')
                        ->get();
                @endphp
                @foreach($dayActions as $action)
                <tr>
                    <td>Day {{ $dayData['day'] }}</td>
                    <td>{{ $dayData['date'] }}</td>
                    <td>{{ $action->action_name }}</td>
                    <td>{{ $dayData['stats'][$action->id] ?? 0 }}</td>
                </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        Generated {{ now()->format('Y-m-d H:i') }} &bull; ICT Foundation Nepal Event Management System
    </div>
</body>
</html>
