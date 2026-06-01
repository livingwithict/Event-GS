> **Note (2026-06-01):** Redis and Horizon items in this roadmap are deferred. The project currently uses database driver for queue and cache. These items will be picked up when migrating to a VPS.

## 📁 File 5: `Implementation_Plan.md` (Roadmap)

```markdown
# Implementation Roadmap – 6 Weeks to MVP

## Week 1: Foundation
- [ ] Set up Laravel 11 + PostgreSQL + Redis locally
- [ ] Install FilamentPHP admin panel
- [ ] Create database migrations for `events`, `registrations`, `users`
- [ ] Implement authentication (Laravel Breeze or Jetstream)
- [ ] Create user roles & seeders (Super Admin, Event Manager, Scanner)

**Deliverable:** Running Laravel app with login and empty admin panel.

## Week 2: Event & Registration Management
- [ ] Build Event CRUD in Filament
- [ ] Build Registration CRUD with relation to Event
- [ ] Implement Excel import using `maatwebsite/excel`
  - Column mapping interface
  - Validation (email, phone)
  - Duplicate detection
- [ ] Generate unique QR codes (UUID + HMAC) for each registration
- [ ] Add soft deletes & audit logging

**Deliverable:** Admin can create event, import Excel, see registration list.

## Week 3: Communication Module
- [ ] Integrate Mailgun/SendGrid for email
- [ ] Build email template system
- [ ] Attach QR code as inline image to email
- [ ] Integrate local SMS gateway (e.g., Sparrow SMS)
- [ ] Implement queue jobs for bulk sending
- [ ] Add communication log table and UI

**Deliverable:** Admin can send bulk emails and SMS to selected registrations.

## Week 4: Scanning Interface & API
- [ ] Build React PWA with `html5-qrcode`
- [ ] Create Laravel API endpoints:
  - `/api/scan` – validate QR, return guest data
  - `/api/entry` – record entry time
  - `/api/meal` – record lunch/dinner usage
- [ ] Implement Redis caching for QR status
- [ ] Add manual search fallback (name/email)

**Deliverable:** Scanner operator can scan QR, record entry & meal usage.

## Week 5: Real-time Dashboard & Testing
- [ ] Build real-time dashboard in Filament (stats from Redis)
- [ ] Implement export reports (Excel/CSV)
- [ ] Write integration tests for scanning & meal validation
- [ ] Load test with 10k registrations (K6 or Artillery)
- [ ] Fix concurrency issues (Redis locks, idempotency)

**Deliverable:** Admin sees live attendance stats; system passes 100 concurrent scans test.

## Week 6: Deployment & Training
- [ ] Dockerize application with docker-compose
- [ ] Set up production server (DigitalOcean)
- [ ] Configure SSL, backups, monitoring
- [ ] Write user manual (admin + scanner)
- [ ] Train ICT Foundation staff
- [ ] Migrate one past event's Excel data as pilot
- [ ] Go live for next event

**Deliverable:** Production-ready system with documentation.

## Post-MVP (Week 7+)
- [ ] Offline mode for scanning app (IndexedDB)
- [ ] Self-service registration portal
- [ ] WhatsApp notification integration
- [ ] Advanced reporting charts

## Risk Mitigation
| Risk | Mitigation |
|------|-------------|
| Email delivery failures | Use transactional provider with webhooks; retry logic |
| Internet outage at venue | Offline mode for scanning (stretch) – or local WiFi hotspot |
| QR forgery | HMAC signing + rate limiting |
| Database slow with 10k scans | Indexes + Redis caching + partition by event_id |
```

