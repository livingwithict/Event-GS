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
            <div class="header">
                @if($event->logo_path)
                    <img src="{{ Storage::url($event->logo_path) }}" alt="{{ $event->name }}" style="max-height:40px;margin-bottom:8px;">
                @endif
                <h1>{{ $event->name }}</h1>
                <p>{{ $event->start_datetime?->format('M j, Y H:i') ?? '' }} &middot; {{ $event->venue }}</p>
            </div>

            <div class="body">
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

                    @if($categories->count() > 0)
                        <label>Select Category</label>
                        <div class="categories">
                            @foreach($categories as $cat)
                                <label class="cat-card" onclick="this.querySelector('input').click()">
                                    <div>
                                        <input type="radio" name="category_id" value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'checked' : '' }}>
                                        <span class="cat-name">{{ $cat->name }}</span>
                                        @if($cat->description)
                                            <div class="hint">{{ Str::limit($cat->description, 60) }}</div>
                                        @endif
                                    </div>
                                    <div>
                                        @if($cat->is_paid && $cat->price)
                                            <span class="cat-price">NPR {{ number_format($cat->price, 0) }}</span>
                                        @else
                                            <span class="cat-free">Free</span>
                                        @endif
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @endif

                    <div class="field">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}">
                        </div>
                        <div class="field">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="+97798XXXXXXXX">
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

                    <div class="row">
                        <div class="field">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">--</option>
                                <option value="male" {{ old('gender') === 'male' ? 'selected' : '' }}>Male</option>
                                <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>Female</option>
                                <option value="other" {{ old('gender') === 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="meal_preference">Meal Preference</label>
                            <select id="meal_preference" name="meal_preference">
                                <option value="">--</option>
                                <option value="veg" {{ old('meal_preference') === 'veg' ? 'selected' : '' }}>Vegetarian</option>
                                <option value="non-veg" {{ old('meal_preference') === 'non-veg' ? 'selected' : '' }}>Non-Vegetarian</option>
                                <option value="vegan" {{ old('meal_preference') === 'vegan' ? 'selected' : '' }}>Vegan</option>
                                <option value="halal" {{ old('meal_preference') === 'halal' ? 'selected' : '' }}>Halal</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label for="pan_vat">PAN / VAT Number</label>
                            <input type="text" id="pan_vat" name="pan_vat" value="{{ old('pan_vat') }}">
                        </div>
                        <div class="field">
                            <label for="photo">Photo</label>
                            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png">
                            <div class="hint">JPG or PNG, max 2MB</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="2">{{ old('notes') }}</textarea>
                        </div>
                        <div class="field">
                            <label for="special_assistance">Special Assistance</label>
                            <input type="text" id="special_assistance" name="special_assistance" value="{{ old('special_assistance') }}" placeholder="e.g. wheelchair access">
                        </div>
                    </div>

                    <div class="consent-row">
                        <input type="checkbox" id="consent" name="consent" value="1" required>
                        <label for="consent">I agree to the registration terms and consent to receive event-related communications.</label>
                    </div>

                    <button type="submit" class="submit-btn">Register</button>
                </form>
            </div>
        </div>
        <div class="footer">
            {{ $event->contact_info ?? config('app.name') }}
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
