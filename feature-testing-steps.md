# Feature Testing Steps

This document provides a complete testing checklist for the Laravel 13 + Filament v5 event management system.

## 1. Prepare The Environment

Use PostgreSQL for manual testing and SQLite for automated tests.

```bash
cp .env.example .env
php artisan key:generate
php artisan config:clear
php artisan migrate
php artisan db:seed --class=RoleSeeder
npm install
npm run build
```

Start the application:

```bash
php -d display_errors=Off artisan serve --port=8000
php artisan queue:work --tries=3
npm run dev
```

Verify the environment:

```bash
php artisan dev:check
```

Expected result: `Clean HTML ✓`.

Use `http://localhost:8000`, not `127.0.0.1`, because the local session domain is configured for `localhost`.

## 2. Run Automated Tests

Run the complete test suite:

```bash
composer test
```

Run tests in parallel:

```bash
php artisan test --parallel
```

Run targeted suites:

```bash
php artisan test tests/Feature/AuthTest.php
php artisan test tests/Feature/RegistrationImportTest.php
php artisan test tests/Feature/PaymentFlowTest.php
php artisan test tests/Feature/IntegrationTest.php
php artisan test tests/Feature/ReportTest.php
php artisan test tests/Feature/OnsitePaymentTest.php
php artisan test tests/Unit
```

Check code style:

```bash
./vendor/bin/pint --test
```

Fix formatting when required:

```bash
./vendor/bin/pint
```

## 3. Test Authentication And Roles

Use the seeded account:

```text
Email: admin@ictfoundation.org.np
Password: password
```

Verify:

- Admin can log in and log out.
- Invalid credentials are rejected.
- Unauthenticated users cannot access `/admin`.
- `super_admin` can manage all resources and users.
- `admin` can manage events and managers.
- `manager` only sees assigned events.
- `finance` can access payments and reports but not unrelated administration.
- `scanner` cannot access the Filament admin panel.
- `scanner` can access scan, entry, meal, and guest-search APIs.
- `viewer` cannot access restricted endpoints.
- API login returns a Sanctum token.
- API logout invalidates the token.
- Authentication failures appear in `storage/logs/auth-YYYY-MM-DD.log`.

## 4. Test Event Configuration

In the Filament admin panel:

- Create draft, published, closed, and archived events.
- Configure event name, slug, description, logo, banner, venue, capacity, and contact details.
- Configure single-day and multi-day dates.
- Configure registration opening and closing dates.
- Enable and disable self-registration, payments, notifications, waitlist, and other event settings.
- Confirm published events appear on the public landing page.
- Confirm draft, closed, and archived events are not publicly registerable.
- Confirm event images upload and display correctly.
- Switch between multiple events using the sidebar event switcher.
- Confirm event-scoped resources only show records belonging to the active event.

## 5. Test Participant Categories

Create categories such as:

- Free attendee
- Paid attendee
- VIP
- Speaker
- Volunteer

Verify:

- Categories are event-specific.
- Inactive categories cannot be selected publicly.
- Prices and currencies display correctly.
- Category sorting works.
- Category badge colors appear in tables and tickets.
- QR access permissions are enforced.
- Approval-required categories create registrations with the correct approval status.
- Early-bird pricing applies before the configured deadline.
- Normal pricing applies after the deadline.

## 6. Test Public Registration

Open:

```text
/event/{slug}/register
```

Test:

- Valid free registration.
- Valid paid registration.
- Missing name.
- Missing both email and phone.
- Invalid email.
- Invalid gender.
- Invalid meal preference.
- Missing consent.
- Missing or invalid required category.
- Duplicate email.
- Duplicate phone.
- Inactive category.
- Registration outside the registration window.
- Registration when capacity is full.
- Waitlist enabled at capacity.
- Waitlist disabled at capacity.
- Photo upload.
- Meal preference.
- Special assistance.
- Designation, organization, address, notes, and PAN/VAT.
- Companion or group registration.
- Valid promo code.
- Invalid, expired, and category-incompatible promo codes.

Confirm successful registrations receive:

- Guest number.
- QR hash/token.
- Correct payment status.
- Confirmation email record.
- SMS communication record.
- Ticket where applicable.

## 7. Test Onsite Registration

Use:

```text
/admin/onsite-register/{event}
```

Test with authorized and unauthorized users:

- Free onsite registration.
- Gateway payment.
- Cash payment.
- No payment method for a paid category.
- Confirmation notification enabled.
- Confirmation notification disabled.
- Duplicate participant details.
- Invalid event.
- Unauthorized role access.

Confirm the registration source is `admin_manual`.

## 8. Test CSV/XLSX Import

Use the Filament **Import Guests** page and the API equivalent.

Test files containing:

- Valid rows.
- Missing names.
- Missing email and phone.
- Invalid email.
- Invalid gender.
- Invalid meal preference.
- Duplicate rows.
- Existing participants.
- Phone numbers beginning with `+977`.
- CSV and XLSX formats.
- Empty rows.
- Extra columns.
- Large imports.

Verify:

- Valid rows enter staging with `pending` status.
- Invalid rows create `import_errors`.
- Error row numbers and messages are correct.
- Import batch totals are accurate.
- Rows can be registered individually.
- Rows can be skipped.
- Selected rows can be registered in bulk.
- Created registrations use `registration_source=csv`.
- Confirmation notifications are queued.
- Nepal phone numbers retain the correct `+977` prefix.

## 9. Test Payments

Run the mocked end-to-end payment test:

```bash
php artisan connectips:test-flow
```

For UAT testing, only when credentials and valid keys are configured:

```bash
php artisan connectips:test-flow --live
```

Test:

- Correct price calculation.
- Early-bird price.
- Promo discount.
- Tax calculation.
- Payment creation.
- Auto-submitting ConnectIPS form.
- Successful callback.
- Failed callback.
- Pending callback.
- Cancelled transaction.
- Amount mismatch.
- Invalid transaction.
- Merchant credit statuses `000`, `999`, and `DEFER`.
- Unsupported merchant credit status.
- Retry after failed payment.
- Retry after pending payment.
- Payment expiry.
- Manual payment verification.
- Refund status marking.

Verify:

- Payment status changes correctly.
- Reconciliation fields are persisted.
- QR and ticket issuance occurs only after successful payment.
- Payment confirmation email and SMS are logged.
- Failed payments do not issue valid passes.

Test expiry manually:

```bash
php artisan payment:expire
```

## 10. Test QR, Tickets, And Labels

For a successful registration:

- Open `/checkin/t/{token}`.
- Open `/ticket/{token}`.
- Download `/ticket/{token}/download`.
- Confirm participant and event details.
- Confirm the QR payload resolves correctly.
- Try an invalid token.
- Try a QR from Event A against Event B.
- Try guest number, UUID, QR hash, and full check-in URL resolution.

In Filament:

- Print one label.
- Bulk print labels.
- Filter by category.
- Filter by printed/unprinted status.
- Create and edit label templates.
- Verify printed status, timestamp, and printing user.
- Verify badge collection status when enabled.
- Confirm generated PDFs open and contain expected QR codes and participant data.

## 11. Test Scanner APIs

Authenticate:

```http
POST /api/login
```

Then test:

```http
POST /api/scan
POST /api/entry
POST /api/meal
POST /api/scan-action
GET  /api/guest/search
GET  /api/event/{eventId}/actions
GET  /api/event/{eventId}/info
```

Verify:

- Valid QR returns guest data.
- Invalid QR returns `404`.
- Cross-event QR usage returns `403`.
- First entry succeeds.
- Duplicate entry is rejected or treated idempotently.
- First lunch/dinner scan succeeds.
- Duplicate meal scan is rejected.
- Invalid meal type returns `422`.
- Configured custom actions work.
- Disabled actions cannot be used.
- Category QR permissions are enforced.
- Actions with `allow_multiple` can be repeated.
- Actions with `allow_multiple=false` cannot be repeated.
- Scan logs record scanner, device, location, event, and timestamp.
- `CARD_DELIVERY` and `BADGE_COLLECT` update badge state.
- Requests without Sanctum tokens fail.
- Scanner roles cannot access manager reports.
- Rate limits are enforced.
- Repeated identical requests do not create duplicate records.

## 12. Test Reports And Exports

Test both web routes and authenticated API routes.

Web reports:

```text
/reports/{event}/pdf-summary
/reports/{event}/payments
/reports/{event}/scanner-activity
/reports/{event}/category-summary
/reports/{event}/card-delivery
```

API reports include:

```text
/api/event/{event}/dashboard
/api/reports/attendance/{event}
/api/reports/noshow/{event}
/api/reports/duplicate-scans/{event}
/api/reports/communications/{event}
/api/reports/meal-usage/{event}
/api/reports/event-summary/{event}
/api/reports/payments/{event}
/api/reports/scanner-activity/{event}
/api/reports/category-summary/{event}
/api/reports/card-delivery/{event}
```

For each report verify:

- Correct event filtering.
- Correct role restrictions.
- Correct totals.
- Correct paid, unpaid, failed, and refunded data.
- Correct attendance and no-show calculations.
- Correct meal usage.
- Correct scanner activity.
- Correct card delivery state.
- PDF downloads open successfully.
- XLSX downloads open successfully using `?format=xlsx`.
- Empty datasets render gracefully.

## 13. Test Notifications And Queues

Use `SMS_DRIVER=log` during local testing.

Verify:

- Registration confirmation email.
- Payment success email.
- Payment failure email.
- Event reminder email.
- Post-event thank-you email.
- Urgent event update.
- SMS logging.
- Missing email or phone handling.
- Disabled event notifications.
- Failed communication records.
- Resending failed communication.
- Queue retry behavior.
- Communication records contain the correct type and status.

Run scheduled commands manually:

```bash
php artisan event:send-reminders
php artisan event:send-thankyou
php artisan payment:expire
```

## 14. Test Dashboard And Admin UI

Verify:

- Registration totals.
- Revenue totals.
- Pending payment totals.
- Attendance rate.
- Category breakdown.
- Registration source breakdown.
- Registration trend chart.
- Recent registrations.
- Recent scans.
- Payment statistics.
- Labels printed.
- Empty states.
- Search, filtering, sorting, pagination, and bulk actions.
- Responsive layout on desktop, tablet, and mobile.
- No Livewire JSON or broken-pipe errors in browser developer tools.

## 15. Test Security And Validation

Check:

- CSRF protection on web forms.
- Authentication on admin and report routes.
- Sanctum authentication on APIs.
- Role enforcement.
- Event ownership and event scoping.
- Cross-event data access.
- Mass-assignment protection.
- File upload type and size validation.
- Public ticket token unpredictability.
- Duplicate submission handling.
- Throttle limits on login and registration.
- Idempotency of scan operations.
- No secrets in responses, logs, or generated files.

## 16. Final Verification

Run:

```bash
composer test
./vendor/bin/pint --test
php artisan dev:check
php artisan deploy:check
```

Perform one complete acceptance flow:

1. Create and publish an event.
2. Create free and paid categories.
3. Configure scan actions.
4. Register a free participant.
5. Register a paid participant.
6. Complete or mock payment.
7. Confirm email, SMS, ticket, and QR output.
8. Scan the QR.
9. Record entry, meal, and card delivery.
10. Generate labels.
11. Generate PDF and XLSX reports.
12. Verify dashboard totals.
13. Run reminder, thank-you, and expiry commands.
14. Confirm all activity appears in logs and communication records.

## 17. Code Quality And Security Checks

Run the static analysis and dependency checks before merging changes:

```bash
composer validate --strict
composer audit
npm audit --omit=dev
composer analyse
./vendor/bin/pint --test
```

Verify:

- No environment backup files or credentials are tracked by Git.
- Production ConnectIPS configuration uses TLS verification.
- Production ConnectIPS certificates and private keys exist only in the deployment secret store.
- `APP_DEBUG=false` in production.
- Sanctum tokens have a configured expiration period.
- API responses expose only required fields.
- Event-scoped authorization tests cover assigned and unassigned events.
- Payment amount-mismatch and callback-tampering tests pass.
- CSV exports safely quote fields and neutralize spreadsheet formulas.
- Uploaded participant photos are stored on a private disk.

The repository CI workflow runs dependency audits, formatting, static analysis, PHPUnit, and the frontend build automatically for pushes and pull requests.

Run browser tests locally with the application running:

```bash
npm run test:browser
```

The browser suite covers public rendering on desktop/mobile, invalid ticket and check-in responses, anonymous access protection, admin login, admin resource pages, onsite registration, event reports, and report downloads.
