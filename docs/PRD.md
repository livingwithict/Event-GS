## 📁 File 1: `PRD.md` (Product Requirements Document)

```markdown
# Event Management System – Product Requirements Document (PRD)
**Version 1.0** | **ICT Foundation Nepal**

## 1. Executive Summary
ICT Foundation Nepal needs an in-house, centralized event management system to replace manual Excel workflows and third-party dependency. The system will manage pre-event registrations, on-site QR code verification, meal coupon validation, and maintain a searchable historical archive of all events, attendees, and communications.

## 2. User Roles & Permissions
| Role | Permissions |
|------|--------------|
| **Super Admin** | Full system control, user management, system settings. |
| **Event Manager** | Create/edit events, import Excel, send email/SMS, view reports. |
| **Scanner Operator** | QR scanning, manual name search, meal validation (real-time). No access to historical data or edits. |
| **Viewer** | Read-only access to reports and archives. |

## 3. Functional Requirements

### 3.1 Centralized Database & Archive
| ID | Requirement |
|----|-------------|
| FR-01 | Store all events, registrations, attendees, communications in a single PostgreSQL database. |
| FR-02 | Search/filter by event name, year (range), organization name, individual name, email. |
| FR-03 | Support full-text search on participant name, organization, email. |
| FR-04 | Soft delete only – no permanent deletion. |
| FR-05 | Export any filtered list to Excel/CSV. |

### 3.2 Event & Registration Management
| ID | Requirement |
|----|-------------|
| FR-06 | Create event with: name, date, venue, meal types (lunch/dinner/both), max capacity (optional). |
| FR-07 | Import Excel (any column mapping) – auto-detect email, phone, name columns. |
| FR-08 | Validate email format, phone number (Nepali +977). Show error report for invalid rows. |
| FR-09 | Detect duplicate registrations within same event (same email or phone). Flag but allow override. |
| FR-10 | Generate unique, non-guessable QR code per registration (UUID v4 + HMAC signature). |

### 3.3 Communication (Email & SMS)
| ID | Requirement |
|----|-------------|
| FR-11 | Send bulk email to selected registrations (by event, tag, individual). |
| FR-12 | Email must embed QR code as inline image or attachment (configurable). |
| FR-13 | Send bulk SMS (short text + event link) using local gateway. |
| FR-14 | Log every email/SMS: recipient, timestamp, status (sent/failed/bounced). |
| FR-15 | Resend failed communications individually or in batch. |

### 3.4 On-site QR Scanning & Meal Validation
| ID | Requirement |
|----|-------------|
| FR-16 | Scanner operator logs into mobile/tablet web app. |
| FR-17 | Scan QR → display guest details (name, org, meal entitlements). |
| FR-18 | Button to "Record Entry" – writes `entry_time` (cannot be overwritten). |
| FR-19 | Separate buttons for "Mark Lunch Used" and "Mark Dinner Used". |
| FR-20 | Enforce one-time use per meal type. Show red alert if already used. |
| FR-21 | Manual fallback: search by name/email if QR damaged. Same meal rules apply. |

### 3.5 Reporting
| ID | Requirement |
|----|-------------|
| FR-22 | Real-time dashboard: total registrations, entries, lunch used, dinner used, percentage. |
| FR-23 | Export "Full Attendance Report" (name, org, entry_time, lunch_time, dinner_time). |
| FR-24 | Export "Duplicate Entry Log" (if same QR scanned twice – rare). |
| FR-25 | Export "No-Show List" (registered but no entry_time). |

## 4. Non-Functional Requirements
| ID | Requirement |
|----|-------------|
| NFR-01 | **Performance:** QR verification + entry log < 500ms under 100 concurrent scans. |
| NFR-02 | **Offline Capability:** Scanning app caches recent registrations (last 500) and syncs later. (Stretch goal) |
| NFR-03 | **Security:** All API endpoints require token. QR codes must be signed to prevent forgery. |
| NFR-04 | **Backup:** Automated daily database backup, 90-day retention. |
| NFR-05 | **Audit Trail:** Log all data modifications (who, when, old value, new value). |
| NFR-06 | **Scalability:** Support up to 10,000 registrations per event, 50 events per year. |

## 5. Success Metrics
| Metric | Target |
|--------|--------|
| Import 5000 registrations | < 2 minutes |
| QR scan to entry verification | < 500 ms (p95) |
| No double-meal usage | 100% enforced |
| Find any past event (3+ years old) | < 3 seconds |
| Zero dependency on external team | Achieved |

## 6. Assumptions & Constraints
- Internet connectivity available at venue (WiFi/4G). Offline mode is v2.0.
- Email/SMS costs borne by ICT Foundation Nepal.
- Initial data migration from existing Excel files is out of v1.0 scope but must be possible via import tool.

## 7. Future Enhancements (v2.0)
- Offline-first scanning app (PWA with IndexedDB sync)
- Self-service registration portal (guest registers online, gets QR via email)
- Payment gateway integration for paid events
- WhatsApp notifications
```

