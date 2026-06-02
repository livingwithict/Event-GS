# Feature Roadmap: PDF Spec Gaps

Source: `docs/Event-Management-System-System-Goal.pdf`
Last updated: 2026-06-01

---

## Phase 1: Event Configuration & Database Schema -- DONE

- [x] **1.1** Add event fields: `description`, `logo_path`, `banner_path`, `start_datetime`, `end_datetime`, `registration_open_at`, `registration_close_at`, `contact_info`, `status` (draft/published/closed/archived)
- [x] **1.2** Admin-configurable toggle settings per event (9 toggles stored in `settings` JSON column)
- [x] **1.3** EventResource form with 6 sections: Details, Images, Schedule, Venue & Capacity, Toggle Settings, Status
- [x] **1.4** Event status workflow with Filament badge colors (draft=gray, published=success, closed=warning, archived=danger)
- [x] **1.5** Multi-day support via start_datetime/end_datetime (event_date kept for backward compat)
- [x] **1.6** File storage for logos/banners using Laravel public disk (`storage/app/public/events/`)

## Phase 2: Participant Category System -- DONE

- [x] **2.1** Create `participant_categories` table: id, event_id, name, description, is_paid, price, currency, badge_color, sort_order, is_active, qr_access_permissions, timestamps
- [x] **2.2** Create CategoryResource in Filament with CRUD operations
- [x] **2.3** Add `category_id` foreign key to registrations table
- [x] **2.4** Update RegistrationResource form with category select (scoped to event)
- [x] **2.5** Add category-wise participant counts to dashboard
- [x] **2.6** Seed default categories: General Attendee, VIP, Chief Guest, Organizer, Volunteer, Sponsor, Media, Speaker, Exhibitor

## Phase 3: Guest Number & QR Enhancement -- DONE

- [x] **3.1** Generate public-facing guest number format: `{EVENT_CODE}-G-{RANDOM}` (e.g., DNC2026-G-8F4K29)
- [x] **3.2** Add `guest_number` column to registrations table (unique per event)
- [x] **3.3** Auto-generate guest_number on registration creation
- [x] **3.4** Update QR payload to encode secure token + event ID + guest code (instead of plain UUID)
- [x] **3.5** Create public check-in/verify page at `/checkin/t/{secure_token}` -- shows participant and event info when QR is scanned as URL
- [x] **3.6** Validate QR is event-specific -- participant from Event A must not be valid at Event B scanner
- [x] **3.7** Display guest_number in Filament registration table and scanner UI

## Phase 4: Custom Scan Actions System -- DONE

- [x] **4.1** Create `scan_action_types` table: id, event_id, action_name, action_code, allow_multiple, is_active, sort_order
- [x] **4.2** Create `scan_logs` table: id, event_id, participant_id, action_type_id, scanned_by, scan_device, scan_location, remarks, scanned_at
- [x] **4.3** Create ScanActionTypeResource in Filament for managing actions per event
- [x] **4.4** Seed default actions: CHECKIN, LUNCH, DINNER, CARD_DELIVERY
- [x] **4.5** Refactor scan/entry/meal controllers to use scan_logs with configurable action types
- [x] **4.6** Add duplicate scan prevention rules per action type (allow_multiple flag)
- [x] **4.7** Update scanner PWA to show all configurable actions dynamically (instead of hardcoded Entry/Lunch/Dinner buttons)
- [x] **4.8** Add scanned_by admin user tracking to every scan

## Phase 5: Self-Registration Flow (Public Pages) -- DONE

- [x] **5.1** Create public registration page (Blade/Livewire) accessible at `/event/{slug}/register`
- [x] **5.2** Category selection with price display on public form
- [x] **5.3** Registration form fields: full_name, email, phone, designation, organization, address, gender (optional), notes, PAN/VAT (optional), consent checkbox
- [x] **5.4** Optional fields: photo upload, meal preference, special assistance requirement
- [x] **5.5** Add `registration_source` column to registrations (self/csv/admin_manual)
- [x] **5.6** Close registration automatically based on registration_close_at date
- [x] **5.7** Enforce max_capacity limit on public registration
- [x] **5.8** Auto-send confirmation email + SMS after successful registration
- [x] **5.9** Block imported/free users from entering paid flow

## Phase 6: Payment Integration (Connect IPS) -- DONE

- [x] **6.1** Create `payments` table with registration_id, event_id, category_id, amount_paisa, currency, transaction_id, gateway_txn_id, payment_status, paid_at, gateway_response, verified_by, verified_at
- [x] **6.2** Add payment statuses: pending, initiated, success, failed, cancelled, expired, refunded
- [x] **6.3** Create ConnectIPSService with token generation (SHA256 + RSA sign + base64), payment initiation, and validation API
- [x] **6.4** Payment flow: fetch category price → create Payment → auto-submit form to Connect IPS → callback → validate → mark success/fail
- [x] **6.5** Support retry payment for pending/failed transactions
- [x] **6.6** Admin-side manual payment verification (Mark as Verified action in PaymentResource)
- [x] **6.7** Payment list in Filament with status/event/date filters, payment_status badge on registrations
- [x] **6.8** QR pass only issued after successful payment (payment_status check in registration flow)

## Phase 7: Enhanced Email & SMS Notifications -- DONE

- [x] **7.1** Email types: registration_confirmation, payment_success, payment_failed, event_reminder, post_event_thank_you, urgent_update
- [x] **7.2** Create email templates for each type (Blade views) — 6 new templates
- [x] **7.3** Add `email_type` column to communications table with index
- [x] **7.4** Event reminder: scheduled job (`event:send-reminders`) to send reminder 1 day before event, runs daily at 09:00
- [x] **7.5** Post-event thank you email (`event:send-thankyou`), runs daily at 10:00
- [x] **7.6** Payment confirmation SMS with amount and guest number
- [x] **7.7** SMS for urgent event updates via `sendUrgentUpdate()` method

## Phase 8: PDF/HTML Ticket Generation -- DONE

- [x] **8.1** Create ticket Blade template (A6 landscape): event logo, name, participant info, category color strip, guest number, QR code (base64 PNG), date/time/venue
- [x] **8.2** Generate downloadable PDF ticket using dompdf/dompdf via TicketService
- [x] **8.3** Attach PDF ticket to registration confirmation and payment success emails
- [x] **8.4** Add "Ticket" download action in Filament RegistrationResource per row
- [x] **8.5** Public ticket page at `/ticket/{token}` (HTML view) and `/ticket/{token}/download` (PDF download)

## Phase 9: Label/Sticker Printing Module -- DONE

- [x] **9.1** Create `label_templates` table with event_id, template_name, width, height, show_qr, show_designation, show_organization, show_category_color, font_size_name, config_json
- [x] **9.2** Create LabelTemplateResource in Filament for managing templates with full CRUD
- [x] **9.3** Generate printable label PDF via LabelService with QR code, name, designation, organization, category color strip on A4 grid layout
- [x] **9.4** Add `label_printed`, `label_printed_at`, `label_printed_by` columns to registrations
- [x] **9.5** Bulk print labels with category/unprinted filters via Filament bulk action and API endpoint
- [x] **9.6** Single label print via Filament record action (Print Label)
- [x] **9.7** Label printed status column + TernaryFilter in Filament for printed/unprinted tracking

## Phase 10: Enhanced Dashboard -- DONE

- [x] **10.1** Enhanced EventStatsOverview widget: events, registrations (with source breakdown), revenue, attendance rate, pending payments, labels printed
- [x] **10.2** Category badges in RecentRegistrationsTable with category colors
- [x] **10.3** RegistrationTrendChart — line chart showing daily registrations for last 30 days
- [x] **10.4** PaymentStatsOverview widget: total collected, pending amount, failed payment count
- [x] **10.5** Attendance rate stat (entries/registrations percentage with color coding)
- [x] **10.6** RecentRegistrationsTable (10 most recent) and RecentScansTable (10 most recent scans with action types)

## Phase 11: Enhanced Reports & Exports -- DONE

- [x] **11.1** Add PDF summary export via EventSummaryPdfExport (dompdf) with stats, category breakdown, payment summary
- [x] **11.2** Add Excel XLSX format support — all report endpoints accept `?format=xlsx` query parameter
- [x] **11.3** PaymentExport supports filters by event_id and payment_status
- [x] **11.4** Payment report via `/api/reports/payments/{event}` with Transaction ID, guest info, amount, status, paid_at, verified_by
- [x] **11.5** Category-wise summary report via `/api/reports/category-summary/{event}` with per-category registration, payment, and attendance counts
- [x] **11.6** Scanner activity report via `/api/reports/scanner-activity/{event}` with scan logs per scanner, action type, device, location
- [x] **11.7** Card delivery tracked via scan_logs (CARD_DELIVERY action type already seeded in Phase 4)
- [x] **11.8** Label printing tracked via label_printed column on registrations with TernaryFilter in Filament (Phase 9)

## Phase 12: Import Tracking Enhancement -- DONE

- [x] **12.1** Create `import_batches` table: id, event_id, imported_by, file_name, total_rows, success_rows, failed_rows, status, timestamps
- [x] **12.2** Create `import_errors` table: id, import_batch_id, row_number, raw_data (json), error_message
- [x] **12.3** Persist CSV import results — RegistrationsImport creates ImportError rows per failed row, updates ImportBatch counts on completion
- [x] **12.4** ImportBatchResource in Filament with list view (status badge, counts), view page with batch details. Import CSV action added to EventResource.

## Phase 13: Roles & Permissions Enhancement -- DONE

- [x] **13.1** Add roles: Event Admin, Registration Staff, Finance/Admin Accounts
- [x] **13.2** Permission-based access: scanner staff can only scan, registration staff can manage registrations, finance can view payments
- [x] **13.3** Update EnsureRole middleware for new roles
- [x] **13.4** Filament navigation visibility based on role

## Phase 14: Multi-Day Event Tracking -- DONE

- [x] **14.1** Support start_datetime/end_datetime on events (replaces single event_date)
- [x] **14.2** Per-day scan tracking: check-in for Day 1, Day 2 separately
- [x] **14.3** Daily attendance report per event day
- [x] **14.4** Scanner UI shows current event day context

## Phase 15: Card Delivery Tracking -- DONE

- [x] **15.1** Add card delivery as a scan action type
- [x] **15.2** Track card delivery status per participant
- [x] **15.3** Card delivery report (delivered vs pending)
- [x] **15.4** Show card delivery status in scanner UI

---

## Phase 16: Future Enhancements (from PDF "Suggested Improvements")

These are explicitly marked as future items in the PDF spec. Implement after core phases are stable.

- [ ] **16.1** Promo codes / discount codes for selected categories
- [ ] **16.2** Approval-based registration (admin approves before ticket issuance)
- [ ] **16.3** Companion / group booking (one user registers multiple guests)
- [ ] **16.4** Onsite manual registration desk (admin/staff register walk-in participants on event day)
- [ ] **16.5** Badge status track: not printed → printed → collected
- [ ] **16.6** Attendance analytics (heatmap of arrival time, category-wise attendance rate)
- [ ] **16.7** Certificate eligibility (reuse check-in logs for certificate generation)
- [ ] **16.8** Multi-language support (English/Nepali in forms and badges)
- [ ] **16.9** WhatsApp/Viber notification channel (in addition to email/SMS)
- [ ] **16.10** Offline-friendly scan mode (Service Worker cache for scanner PWA)
- [ ] **16.11** Early bird pricing for events
- [ ] **16.12** Invoice/receipt generation for payments
- [ ] **16.13** Payment expiry timer (link expires after X minutes)

---

## Implementation Priority

| Priority | Phase | Reason |
|----------|-------|--------|
| **P1 (Next)** | Phase 1, 2, 3 | Foundation -- event config, categories, guest numbers. Required before payment and public registration. |
| **P2** | Phase 4, 5 | Core new functionality -- custom scan actions and self-registration |
| **P3** | Phase 6, 8 | Payment and ticketing -- required for paid events |
| **P4** | Phase 7, 9 | Communication and label enhancements |
| **P5** | Phase 10, 11, 12, 13, 14, 15 | Dashboard, reports, multi-day, roles -- polish and completeness |
| **P6 (Later)** | Phase 16 | Future enhancements from PDF spec -- promo codes, group booking, offline mode, etc. |
