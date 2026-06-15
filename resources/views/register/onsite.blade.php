<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Onsite Registration — {{ $event->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; color: #111; }
        .container { max-width: 560px; margin: 0 auto; padding: 16px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: #059669; color: #fff; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 18px; font-weight: 700; }
        .header .event-name { font-size: 12px; opacity: 0.9; }
        .body { padding: 24px; }
        .success-box { background: #d1fae5; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; font-weight: 600; }
        .error-box { background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 4px; }
        .field { margin-bottom: 14px; }
        input, select { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none; }
        input:focus, select:focus { border-color: #059669; box-shadow: 0 0 0 3px rgba(5,150,105,0.1); }
        .field-error { color: #dc2626; font-size: 12px; margin-top: 2px; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .submit-btn { width: 100%; padding: 14px; font-size: 16px; font-weight: 600; background: #059669; color: #fff; border: none; border-radius: 8px; cursor: pointer; }
        .submit-btn:hover { background: #047857; }
        .toggle-row { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
        .toggle-row input[type="checkbox"] { width: auto; }
        .toggle-row label { font-size: 13px; font-weight: 400; margin: 0; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-green { background: #d1fae5; color: #065f46; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div>
                    <h1>Onsite Registration</h1>
                    <div class="event-name">{{ $event->name }}</div>
                </div>
                <span class="badge badge-green">Walk-in</span>
            </div>

            <div class="body">
                @if(session('success'))
                    <div class="success-box">{{ session('success') }}</div>
                @endif

                @if($errors->any())
                    <div class="error-box">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('onsite.register.store', $event->id) }}">
                    @csrf

                    <div class="field">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}">
                        </div>
                        <div class="field">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="+97798XXXXXXXX">
                        </div>
                    </div>

                    <div class="field">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id">
                            <option value="">— Select —</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label for="organization">Organization</label>
                            <input type="text" id="organization" name="organization" value="{{ old('organization') }}">
                        </div>
                        <div class="field">
                            <label for="designation">Designation</label>
                            <input type="text" id="designation" name="designation" value="{{ old('designation') }}">
                        </div>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label for="meal_preference">Meal Preference</label>
                            <select id="meal_preference" name="meal_preference">
                                <option value="">—</option>
                                <option value="veg" {{ old('meal_preference') === 'veg' ? 'selected' : '' }}>Veg</option>
                                <option value="non-veg" {{ old('meal_preference') === 'non-veg' ? 'selected' : '' }}>Non-Veg</option>
                                <option value="vegan" {{ old('meal_preference') === 'vegan' ? 'selected' : '' }}>Vegan</option>
                                <option value="halal" {{ old('meal_preference') === 'halal' ? 'selected' : '' }}>Halal</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="special_assistance">Special Assistance</label>
                            <input type="text" id="special_assistance" name="special_assistance" value="{{ old('special_assistance') }}" placeholder="e.g. wheelchair">
                        </div>
                    </div>

                    <div class="toggle-row">
                        <input type="checkbox" id="send_notifications" name="send_notifications" value="1" {{ old('send_notifications') ? 'checked' : '' }}>
                        <label for="send_notifications">Send confirmation email/SMS</label>
                    </div>

                    <button type="submit" class="submit-btn">Register Attendee</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
