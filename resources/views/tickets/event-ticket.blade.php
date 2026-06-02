<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: 148mm 105mm; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; }
        .ticket { width: 148mm; height: 105mm; display: flex; overflow: hidden; border: 1px solid #d1d5db; }
        .color-strip { width: 8px; flex-shrink: 0; background: {{ $category?->badge_color ?? '#1a56db' }}; }
        .main { flex: 1; display: flex; flex-direction: column; padding: 12mm 10mm 8mm 10mm; }
        .header { display: flex; align-items: center; margin-bottom: 8mm; }
        .logo { width: 18mm; height: 18mm; margin-right: 5mm; border-radius: 3px; object-fit: contain; }
        .logo-placeholder { width: 18mm; height: 18mm; margin-right: 5mm; background: #f3f4f6; border-radius: 3px; display: flex; align-items: center; justify-content: center; font-size: 8px; color: #9ca3af; }
        .header-text h1 { font-size: 16px; font-weight: 700; color: #111827; margin-bottom: 2px; }
        .header-text p { font-size: 9px; color: #6b7280; }
        .body-section { flex: 1; display: flex; gap: 8mm; }
        .info { flex: 1; }
        .info .name { font-size: 18px; font-weight: 700; color: #111827; margin-bottom: 3px; }
        .info .subtitle { font-size: 11px; color: #6b7280; margin-bottom: 6px; }
        .guest-number { display: inline-block; font-family: 'Courier New', monospace; font-size: 14px; font-weight: 700; color: #1a56db; border: 2px solid #1a56db; border-radius: 4px; padding: 3px 10px; margin-bottom: 6px; letter-spacing: 1.5px; }
        .category-badge { display: inline-block; font-size: 9px; font-weight: 600; padding: 2px 8px; border-radius: 10px; margin-left: 6px; background: {{ $category?->badge_color ?? '#1a56db' }}20; color: {{ $category?->badge_color ?? '#1a56db' }}; }
        .event-details { margin-top: auto; font-size: 9px; color: #6b7280; }
        .event-details span { margin-right: 12px; }
        .qr-section { width: 28mm; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .qr-section img { width: 26mm; height: 26mm; }
        .footer { display: flex; justify-content: space-between; align-items: center; padding-top: 4mm; border-top: 1px solid #e5e7eb; margin-top: 4mm; font-size: 8px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="color-strip"></div>
        <div class="main">
            <div class="header">
                @if($event->logo_path && file_exists(public_path('storage/'.$event->logo_path)))
                <img class="logo" src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('storage/'.$event->logo_path))) }}" alt="Logo">
                @else
                <div class="logo-placeholder">LOGO</div>
                @endif
                <div class="header-text">
                    <h1>{{ $event->name }}</h1>
                    <p>Official Event Ticket</p>
                </div>
            </div>

            <div class="body-section">
                <div class="info">
                    <div class="name">{{ $registration->name }}</div>
                    @if($registration->designation || $registration->organization)
                    <div class="subtitle">{{ implode(' · ', array_filter([$registration->designation, $registration->organization])) }}</div>
                    @endif
                    <div>
                        <span class="guest-number">{{ $registration->guest_number }}</span>
                        @if($category)
                        <span class="category-badge">{{ $category->name }}</span>
                        @endif
                    </div>

                    <div class="event-details">
                        @if($event->start_datetime)
                        <span>{{ $event->start_datetime->format('M j, Y') }}</span>
                        <span>{{ $event->start_datetime->format('g:i A') }}</span>
                        @endif
                        @if($event->venue)
                        <span>{{ $event->venue }}</span>
                        @endif
                    </div>
                </div>

                <div class="qr-section">
                    <img src="data:image/png;base64,{{ $qrCodePng }}" alt="QR Code">
                </div>
            </div>

            <div class="footer">
                <span>ICT Foundation Nepal</span>
                <span>{{ $ticketUrl }}</span>
            </div>
        </div>
    </div>
</body>
</html>
