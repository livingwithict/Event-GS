<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* Same sticker size as the ID label template (same printer). */
        @page { size: {{ $template->width }}mm {{ $template->height }}mm; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }

        .label {
            position: relative;
            width: {{ $template->width }}mm;
            height: {{ $template->height }}mm;
            padding: {{ $padY }}mm {{ $padX }}mm;
            overflow: hidden;
            page-break-after: always;
        }
        .label:last-child { page-break-after: auto; }

        .name {
            font-size: {{ max(12, (int) round($template->height * 0.24)) }}px;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 1mm;
        }
        .meta {
            font-size: {{ max(10, (int) round($template->height * 0.17)) }}px;
            color: #333;
            margin-bottom: 0.5mm;
        }
        .phone {
            font-size: {{ max(10, (int) round($template->height * 0.2)) }}px;
            margin-top: 2mm;
            margin-bottom: 1mm;
        }
        .address {
            font-size: {{ max(10, (int) round($template->height * 0.18)) }}px;
            line-height: 1.3;
        }
        .order-qr {
            position: absolute;
            right: {{ $padX }}mm;
            bottom: {{ $padY + 1.5 }}mm;
            width: {{ round($template->height * 0.34, 1) }}mm;
            height: {{ round($template->height * 0.34, 1) }}mm;
        }
        .qr-label {
            position: absolute;
            right: {{ $padX }}mm;
            bottom: {{ $padY + 1.5 + round($template->height * 0.34, 1) }}mm;
            font-size: {{ max(8, (int) round($template->height * 0.12)) }}px;
            font-weight: 700;
            white-space: nowrap;
            text-align: right;
        }
    </style>
</head>
<body>
    @foreach($labels as $label)
    <div class="label">
        {{-- Reserve the QR's own column on the right so address/organization wrap instead of running under it. --}}
        <div @if($label['order_qr']) style="padding-right: {{ round($template->height * 0.34, 1) + 2 }}mm;" @endif>
            <div class="name">{{ $label['name'] }}</div>
            @if($label['designation'])
            <div class="meta">{{ $label['designation'] }}</div>
            @endif
            @if($label['organization'])
            <div class="meta">{{ $label['organization'] }}</div>
            @endif
            @if($label['phone'])
            <div class="phone">Phone - {{ $label['phone'] }}</div>
            @endif
            @if($label['address'])
            <div class="address">{{ $label['address'] }}</div>
            @endif
        </div>
        @if($label['order_qr'])
        <div class="qr-label">{{ $qrLabel }}</div>
        <img class="order-qr" src="data:image/png;base64,{{ $label['order_qr'] }}" alt="Order QR">
        @endif
    </div>
    @endforeach
</body>
</html>
