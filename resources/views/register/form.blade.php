<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register — {{ $event->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; color: #111; }
        .container { max-width: 560px; margin: 0 auto; padding: 16px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: #2563eb; color: #fff; padding: 24px; text-align: center; }
        .header h1 { font-size: 20px; font-weight: 700; }
        .header p { font-size: 13px; opacity: 0.9; margin-top: 4px; }
        .body { padding: 24px; }
        .error-box { background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 4px; }
        .field { margin-bottom: 16px; }
        input, select, textarea { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none; transition: border-color 0.2s; }
        input:focus, select:focus, textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .hint { font-size: 12px; color: #6b7280; margin-top: 2px; }
        .field-error { color: #dc2626; font-size: 12px; margin-top: 2px; }
        .categories { display: grid; gap: 8px; margin-bottom: 16px; }
        .cat-card { border: 2px solid #e5e7eb; border-radius: 8px; padding: 12px 16px; cursor: pointer; transition: all 0.2s; display: flex; justify-content: space-between; align-items: center; }
        .cat-card:hover { border-color: #2563eb; }
        .cat-card.selected { border-color: #2563eb; background: #eff6ff; }
        .cat-card input[type="radio"] { display: none; }
        .cat-name { font-weight: 600; font-size: 14px; }
        .cat-price { font-size: 13px; color: #059669; font-weight: 600; }
        .cat-free { font-size: 13px; color: #6b7280; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .consent-row { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 20px; }
        .consent-row input[type="checkbox"] { width: auto; margin-top: 2px; }
        .consent-row label { font-size: 13px; font-weight: 400; color: #374151; }
        .submit-btn { width: 100%; padding: 14px; font-size: 16px; font-weight: 600; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer; }
        .submit-btn:hover { background: #1d4ed8; }
        .submit-btn:disabled { background: #9ca3af; cursor: not-allowed; }
        .footer { text-align: center; padding: 16px; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            @if($banner = $event->bannerUrl())
                <img src="{{ $banner }}" alt="{{ $event->name }}" style="display:block;width:100%;height:auto;">
            @endif

            <div class="body">
                <div style="text-align:center;margin-bottom:20px;">
                    @if($logo = $event->logoUrl())
                        <img src="{{ $logo }}" alt="{{ $event->name }}" style="max-height:40px;margin-bottom:8px;">
                    @endif
                    <h1 style="font-size:20px;font-weight:700;">{{ $event->name }}</h1>
                    <p style="font-size:13px;color:#6b7280;margin-top:4px;">{{ $event->start_datetime?->format('M j, Y H:i') ?? '' }} &middot; {{ $event->venue }}</p>
                </div>

                @if(session('error'))
                    <div class="error-box">{{ session('error') }}</div>
                @endif

                @if($errors->any())
                    <div class="error-box">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('register.store', $event->slug) }}" enctype="multipart/form-data">
                    @csrf

                    <div class="field">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" required> 
                        </div>
                        <div class="field">
                            <label for="phone">Phone *</label>
                            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="+97798XXXXXXXX" required>
                            <div class="hint">Nepali phone number</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label for="designation">Designation</label>
                            <input type="text" id="designation" name="designation" value="{{ old('designation') }}">
                        </div>
                        <div class="field">
                            <label for="organization">Organization</label>
                            <input type="text" id="organization" name="organization" value="{{ old('organization') }}">
                        </div>
                    </div>

                    <div class="field">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="2">{{ old('address') }}</textarea>
                    </div>

                    <div class="consent-row">
                        <input type="checkbox" id="consent" name="consent" value="1" required>
                        <label for="consent">I agree to the registration terms and consent to receive event-related communications.</label>
                    </div>

                    <button type="submit" class="submit-btn">Register</button>
                </form>
            </div>
        </div>
        @foreach($event->miscImageUrls() as $img)
            <img src="{{ $img }}" alt="" style="display:block;width:100%;height:auto;margin-top:16px;border-radius:12px;">
        @endforeach
        <div class="footer">
            Global Spark | EventGS
        </div>
    </div>

    <script>
        document.querySelectorAll('.cat-card input').forEach(input => {
            input.addEventListener('change', () => {
                document.querySelectorAll('.cat-card').forEach(c => c.classList.remove('selected'));
                input.closest('.cat-card').classList.add('selected');
            });
            if (input.checked) input.closest('.cat-card').classList.add('selected');
        });
    </script>
</body>
</html>
