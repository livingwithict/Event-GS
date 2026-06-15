<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4; margin: {{ $template->margin_top ?? 8 }}mm {{ $template->margin_right ?? 8 }}mm {{ $template->margin_bottom ?? 8 }}mm {{ $template->margin_left ?? 8 }}mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        .sheet { display: flex; flex-wrap: wrap; gap: 2mm; }
        .label {
            width: {{ $template->width }}mm;
            height: {{ $template->height }}mm;
            border: 0.5px solid #d1d5db;
            border-radius: 2px;
            display: flex;
            overflow: hidden;
            page-break-inside: avoid;
            position: relative;
        }
        .color-strip {
            width: 5mm;
            flex-shrink: 0;
        }
        .label-body {
            flex: 1;
            padding: 3mm 4mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow: hidden;
        }
        .label-name {
            font-size: {{ $template->font_size_name }}px;
            font-weight: 700;
            color: #111827;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .label-detail {
            font-size: 9px;
            color: #6b7280;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 1px;
        }
        .label-qr {
            width: 18mm;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2mm;
        }
        .label-qr img {
            width: 16mm;
            height: 16mm;
        }
        .label-guest {
            font-size: 7px;
            font-family: 'Courier New', monospace;
            color: #9ca3af;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <div class="sheet">
        @foreach($labels as $label)
        <div class="label">
            @if($label['category_color'])
            <div class="color-strip" style="background: {{ $label['category_color'] }};"></div>
            @endif

            <div class="label-body">
                <div class="label-name">{{ $label['name'] }}</div>
                @if($label['designation'])
                <div class="label-detail">{{ $label['designation'] }}</div>
                @endif
                @if($label['organization'])
                <div class="label-detail">{{ $label['organization'] }}</div>
                @endif
                @if($label['category_name'])
                <div class="label-detail" style="color: {{ $label['category_color'] ?? '#6b7280' }}; font-weight:600;">{{ $label['category_name'] }}</div>
                @endif
                <div class="label-guest">{{ $label['guest_number'] }}</div>
            </div>

            @if($label['qr_code'])
            <div class="label-qr">
                <img src="data:image/png;base64,{{ $label['qr_code'] }}" alt="QR">
            </div>
            @endif
        </div>
        @endforeach
    </div>
</body>
</html>
