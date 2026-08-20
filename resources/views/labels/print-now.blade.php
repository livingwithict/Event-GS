<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Printing {{ $count }} label{{ $count === 1 ? '' : 's' }}</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #f3f4f6; }
        .bar {
            padding: 12px 16px; background: #fff; border-bottom: 1px solid #e5e7eb;
            display: flex; gap: 12px; align-items: center; font-size: 14px; color: #374151;
        }
        button {
            padding: 6px 14px; border: 0; border-radius: 6px; background: #1a56db;
            color: #fff; font-size: 14px; cursor: pointer;
        }
        .hint { color: #6b7280; font-size: 13px; }
        iframe { width: 100%; height: calc(100vh - 50px); border: 0; background: #fff; }
    </style>
</head>
<body>
    <div class="bar">
        <strong>{{ $count }} label{{ $count === 1 ? '' : 's' }}</strong>
        <button onclick="printLabels()">Print again</button>
        <span class="hint">Print at 100% / Actual size — not "Fit to page" — or the QR will not scan.</span>
    </div>

    <iframe id="sheet" src="{{ $sheetUrl }}"></iframe>

    <script>
        function printLabels() {
            var frame = document.getElementById('sheet');
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch (e) {
                // Cross-origin or the browser refused: fall back to printing this page.
                window.print();
            }
        }

        document.getElementById('sheet').addEventListener('load', function () {
            setTimeout(printLabels, 300);
        });
    </script>
</body>
</html>
