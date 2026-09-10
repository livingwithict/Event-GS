# Google Sheets registration sync

## Problem

Staff want to hand off the registration list to someone outside the app who
works in a Google Sheet — coloring rows, adding remarks, etc. That sheet must
never be overwritten. The app needs a manual "Update Spreadsheet" action that
appends newly-added registrants to the bottom of the sheet, leaving every
existing row (and any edits made to it) untouched.

## Scope

One event's registration list → one Google Sheet, triggered by a button,
one-way (app → sheet), append-only. No scheduling, no pulling data back into
the app, no per-column mapping UI.

## Auth: service account, zero new Composer dependency

`google/apiclient` pulls in `google/apiclient-services`, which vendors proto
definitions for every Google API and is enormous for what we need. Instead:

- A Google Cloud service account (JSON key) is granted Editor access on each
  target spreadsheet by the person setting it up (share the sheet with the
  service account's `...@...iam.gserviceaccount.com` email, same as sharing
  with any other Google account).
- The app builds a self-signed JWT from the service account's private key
  (PHP's built-in `openssl_sign`, RS256 — no library needed) and exchanges it
  at `https://oauth2.googleapis.com/token` for an access token via Laravel's
  `Http` facade. This is the standard OAuth2 JWT-bearer flow; the official
  client library does the same thing internally.
- Access tokens last 1 hour; cached via `Cache::remember` for 55 minutes so
  every sync doesn't re-mint one.

Credentials follow the existing `config/services.php` + `.env` convention
(see `pickndrop`, `thirdfactor`):

```php
'google_sheets' => [
    'client_email' => env('GOOGLE_SHEETS_CLIENT_EMAIL'),
    'private_key' => env('GOOGLE_SHEETS_PRIVATE_KEY'), // PEM, \n escaped in .env
],
```

## Data model

- `events.google_sheet_id` (nullable string) — parsed from the sheet URL the
  admin pastes into a new field on the Event form. Accepts a full URL or a
  bare ID; the app extracts the ID (`/spreadsheets/d/{id}/`) if a URL was
  pasted.
- `registrations.sheet_synced_at` (nullable timestamp) — set once a row has
  been appended to the sheet. `whereNull('sheet_synced_at')` is the entire
  "what's new" query; no separate cursor/counter needed.

## Service: `App\Services\GoogleSheetsService`

Mirrors the shape of `PickAndDropService`: a thin client wrapping the REST
calls, config-driven credentials, `logger()->error` on failure.

- `syncEvent(Event $event): int` — returns count of rows appended.
  1. Throw a friendly exception if `google_sheet_id` is blank.
  2. `$registrations = $event->registrations()->whereNull('sheet_synced_at')->orderBy('id')->get();`
     — return 0 immediately if empty.
  3. `GET` the sheet's first row (`values/Sheet1!A1:J1`). If blank, `PUT` the
     header row: Title, Name, Guest #, Category, Email, Phone, Address,
     Designation, Organization, Sector.
  4. Build value rows from `$registrations` (Sector = comma-joined
     `sectors->pluck('name')`), `POST` to
     `values/Sheet1!A:A:append?valueInputOption=RAW&insertDataOption=INSERT_ROWS`
     — Sheets auto-detects the next blank row, so concurrent edits elsewhere
     in the sheet are never touched.
  5. On success, `Registration::whereIn('id', $registrations->pluck('id'))->update(['sheet_synced_at' => now()])`.
  6. On any HTTP failure, catch, log, re-throw as a `RuntimeException` with a
     readable message (esp. 403 → "Share the sheet with
     {service account email} as an Editor."). Nothing gets marked synced, so
     the next click retries the same rows — failure is self-healing, no
     manual cleanup.

## UI

- **Event form** (`EventResource`): new `TextInput::make('google_sheet_url')`
  (not stored directly — mutated to `google_sheet_id` on save/fill), with
  helper text showing the service account email to share the sheet with.
- **Registrations list** (`RegistrationResource\Pages\ListRegistrations`):
  new header action "Update Spreadsheet", scoped to `session('active_event_id')`
  (same scoping the table query already uses). Calls
  `GoogleSheetsService::syncEvent()`, shows a `Notification` with the
  appended count, or the caught error message.

## Error handling

Two realistic failure modes, both surfaced as a Filament notification instead
of a stack trace:
- Sheet not shared with the service account → Google 403.
- No sheet configured for the active event → caught before any HTTP call.

## Testing

One feature test: `GoogleSheetsServiceTest` (or a Pest/PHPUnit test hitting
`syncEvent` with `Http::fake()`) verifying: unsynced registrations are sent,
already-synced ones are excluded, and `sheet_synced_at` is set only on
success. HTTP calls are faked — no real Google API in tests.

## Explicitly out of scope

Auto-sync/scheduling, two-way sync, per-column mapping UI, multi-tab support,
retry queue/job (the button click is fast enough to run synchronously for
realistic guest-list sizes).
