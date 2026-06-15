# Two-Factor Authentication (2FA) Plan

**Status:** Not yet implemented — planned for future phase.

## Goal
Add optional 2FA for admin panel users (Filament) to protect sensitive event and participant data.

## Recommended Approach

### Option A: Laravel Fortify + Filament 2FA
- Install `laravel/fortify`
- Enable `two-factor-authentication` feature
- Filament has built-in support for Fortify's 2FA via `->login()->twoFactorAuthentication()`
- Users get QR code to scan with authenticator app (Google Authenticator, Authy, etc.)
- Recovery codes for backup access

### Option B: Custom TOTP
- Use `pragmarx/google2fa-laravel` or a custom TOTP implementation
- Add `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` to `users` table
- Add 2FA challenge step in login flow

### Recommendation: Option A (Laravel Fortify)
- Best integration with Filament
- Battle-tested by Laravel ecosystem
- Minimal custom code needed
- Provides challenge view out of the box

## Implementation Steps (Est. 4-6h)
1. `composer require laravel/fortify`
2. `php artisan fortify:install`
3. Enable 2FA in `config/fortify.php`
4. Add migration: `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`
5. Configure Filament `->login()->twoFactorAuthentication()`
6. Add 2FA management page to user profile
7. Test full login + 2FA flow

## Environment Variables Needed
```
TWO_FACTOR_ENABLED=true
```

## Rollout Plan
1. **Phase 1:** Enable for super_admin and event_manager roles
2. **Phase 2:** Optional for all admin roles
3. **Phase 3:** Mandatory for super_admin

## Recovery
- Generate 8 recovery codes per user
- Admin can reset 2FA for any user (via Filament user management)
