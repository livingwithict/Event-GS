<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* The page is the artwork, at its own pixel size. Dompdf maps 1px to
           0.75pt, so everything below is stated in the artwork's own pixels. */
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { width: {{ $layout['width'] }}px; height: {{ $layout['height'] }}px; position: relative; }

        .card {
            position: absolute; top: 0; left: 0;
            width: {{ $layout['width'] }}px; height: {{ $layout['height'] }}px;
        }

        /* Positioned by baseline, not by box: $nameTop is computed so the text
           sits on the bottom edge of the placeholder, flush to its left edge. */
        .name {
            position: absolute;
            left: {{ $layout['name_x'] }}px;
            top: {{ $nameTop }}px;
            width: {{ $layout['name_w'] }}px;
            height: {{ $nameFontSize }}px;
            line-height: {{ $nameFontSize }}px;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: {{ $nameFontSize }}px;
            font-weight: 700;
            color: {{ $layout['name_color'] }};
            text-align: {{ $layout['name_align'] }};
            white-space: nowrap;
        }

        .qr {
            position: absolute;
            left: {{ $layout['qr_x'] }}px;
            top: {{ $layout['qr_y'] }}px;
            width: {{ $layout['qr_size'] }}px;
            height: {{ $layout['qr_size'] }}px;
        }
        .qr img { display: block; width: {{ $layout['qr_size'] }}px; height: {{ $layout['qr_size'] }}px; }
    </style>
</head>
<body>
    <img class="card" src="{{ $cardDataUri }}" alt="">

    <div class="name">{{ $name }}</div>

    <div class="qr"><img src="data:image/png;base64,{{ $qrPng }}" alt="{{ $registration->guest_number }}"></div>
</body>
</html>
