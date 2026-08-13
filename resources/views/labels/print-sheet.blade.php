<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* One sticker per page. Everything is absolutely positioned in mm so
           dompdf lays it out exactly and nothing can push past the edge. */
        @page { size: {{ $template->width }}mm {{ $template->height }}mm; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }

        .label {
            position: relative;
            width: {{ $template->width }}mm;
            height: {{ $template->height }}mm;
            overflow: hidden;
            page-break-after: always;
        }
        .label:last-child { page-break-after: auto; }

        .title {
            position: absolute;
            top: {{ $geo['pad'] }}mm;
            left: {{ $geo['pad'] }}mm;
            width: {{ $template->width - 2 * $geo['pad'] }}mm;
            height: {{ $geo['titleH'] }}mm;
            line-height: {{ $geo['titleH'] }}mm;
            font-size: {{ max(7, (int) round($geo['titleH'] * 2.4)) }}px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #ffffff;
            background: #1a56db;
            text-align: center;
            overflow: hidden;
            white-space: nowrap;
        }

        .info {
            position: absolute;
            top: {{ $geo['bodyTop'] }}mm;
            left: {{ $geo['pad'] }}mm;
            width: {{ $geo['infoW'] }}mm;
            height: {{ $geo['bodyH'] }}mm;
            overflow: hidden;
        }
        .name {
            font-size: {{ $geo['nameFont'] }}px;
            font-weight: 700;
            color: #000000;
            line-height: 1.1;
        }
        .org {
            font-size: {{ $geo['orgFont'] }}px;
            color: #374151;
            line-height: 1.2;
            margin-top: 0.8mm;
        }

        .qr {
            position: absolute;
            top: {{ $geo['qrTop'] }}mm;
            right: {{ $geo['pad'] }}mm;
            width: {{ $geo['qr'] }}mm;
            text-align: center;
        }
        .qr img { width: {{ $geo['qr'] }}mm; height: {{ $geo['qr'] }}mm; display: block; }
        .code {
            width: {{ $geo['qr'] }}mm;
            height: {{ $geo['codeH'] }}mm;
            line-height: {{ $geo['codeH'] }}mm;
            font-size: {{ $geo['codeFont'] }}px;
            font-weight: 700;
            letter-spacing: 0.2px;
            color: #000000;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
        }
    </style>
</head>
<body>
    @foreach($labels as $label)
    <div class="label">
        @if($geo['titleH'] > 0 && $label['category_name'])
        <div class="title" @if($label['category_color']) style="background: {{ $label['category_color'] }};" @endif>{{ $label['category_name'] }}</div>
        @endif

        <div class="info">
            <div class="name">{{ $label['name'] }}</div>
            @if($label['organization'])
            <div class="org">{{ $label['organization'] }}</div>
            @endif
            @if($label['designation'])
            <div class="org">{{ $label['designation'] }}</div>
            @endif
        </div>

        @if($label['qr_code'])
        <div class="qr">
            <img src="data:image/png;base64,{{ $label['qr_code'] }}" alt="QR">
            <div class="code">{{ $label['guest_number'] }}</div>
        </div>
        @endif
    </div>
    @endforeach
</body>
</html>
