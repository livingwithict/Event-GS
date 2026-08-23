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
            padding: {{ round($pad * 0.4, 1) }}mm;
            overflow: hidden;
            page-break-after: always;
        }
        .label:last-child { page-break-after: auto; }

        .header {
            font-size: {{ max(9, (int) round($template->height * 0.16)) }}px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 1px solid #000;
            padding-bottom: 1mm;
            margin-bottom: 1.5mm;
        }
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
        .tracking {
            font-size: {{ max(9, (int) round($template->height * 0.16)) }}px;
            font-weight: 700;
            letter-spacing: 0.3px;
            margin-top: 1.5mm;
        }
    </style>
</head>
<body>
    @foreach($labels as $label)
    <div class="label">
        <div class="header">Address Label</div>
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
        @if($label['tracking_number'])
        <div class="tracking">{{ $label['tracking_number'] }}</div>
        @endif
    </div>
    @endforeach
</body>
</html>
