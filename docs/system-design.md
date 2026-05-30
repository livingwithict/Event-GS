

## 📁 File 3: `System_Design.md` (Detailed Database & API)

```markdown
# System Design – Event Management System

## 1. Database Schema (PostgreSQL)

```sql
-- Events table
CREATE TABLE events (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    event_date DATE NOT NULL,
    venue TEXT,
    meal_types JSONB DEFAULT '["lunch", "dinner"]', -- e.g., ["lunch"], ["dinner"], ["lunch","dinner"]
    max_capacity INT,
    settings JSONB, -- for future expansion
    created_by BIGINT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL -- soft delete
);

-- Registrations table (guests)
CREATE TABLE registrations (
    id BIGSERIAL PRIMARY KEY,
    event_id BIGINT NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    unique_code UUID DEFAULT gen_random_uuid() UNIQUE NOT NULL,
    qr_hash VARCHAR(255) UNIQUE NOT NULL, -- HMAC of unique_code
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(20),
    organization VARCHAR(255),
    designation VARCHAR(255),
    address TEXT,
    website VARCHAR(255),
    entry_time TIMESTAMP NULL,
    lunch_used_at TIMESTAMP NULL,
    dinner_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    INDEX idx_event_id (event_id),
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_organization (organization),
    INDEX idx_qr_hash (qr_hash),
    INDEX idx_entry_time (entry_time)
);

-- Communications log
CREATE TABLE communications (
    id BIGSERIAL PRIMARY KEY,
    registration_id BIGINT NOT NULL REFERENCES registrations(id) ON DELETE CASCADE,
    type VARCHAR(20) NOT NULL CHECK (type IN ('email', 'sms')),
    subject VARCHAR(255),
    content TEXT,
    sent_at TIMESTAMP DEFAULT NOW(),
    status VARCHAR(50), -- pending, sent, failed, bounced
    provider_message_id VARCHAR(255),
    metadata JSONB,
    INDEX idx_registration (registration_id),
    INDEX idx_sent_at (sent_at)
);

-- Audit logs
CREATE TABLE audit_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT,
    action VARCHAR(100), -- created, updated, deleted, imported, exported
    table_name VARCHAR(100),
    record_id BIGINT,
    old_values JSONB,
    new_values JSONB,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    INDEX idx_record (table_name, record_id),
    INDEX idx_created_at (created_at)
);

-- Users table (for admin & scanner roles)
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    role VARCHAR(50) DEFAULT 'scanner', -- super_admin, event_manager, scanner, viewer
    password VARCHAR(255),
    remember_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP NULL
);
```

## 2. API Endpoints (REST)

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/api/login` | Obtain Sanctum token | No |
| POST | `/api/scan` | Validate QR code, return guest data | Yes (scanner token) |
| POST | `/api/entry` | Record entry time for guest | Yes |
| POST | `/api/meal` | Record lunch/dinner usage | Yes |
| GET | `/api/guest/search?q=` | Manual search by name/email | Yes |
| GET | `/api/event/{event}/dashboard` | Real-time stats | Yes (manager+) |
| POST | `/api/event/{event}/import` | Upload Excel | Yes (manager+) |
| POST | `/api/event/{event}/send-invites` | Trigger bulk email/SMS | Yes (manager+) |
| GET | `/api/reports/attendance/{event}` | Export attendance report | Yes (manager+) |

## 3. Redis Cache Keys Design

```
# QR status cache (expires after event end)
qr:{qr_hash}:entry          → "2025-06-15 10:30:00"
qr:{qr_hash}:lunch          → "2025-06-15 12:45:00"
qr:{qr_hash}:dinner         → "2025-06-15 19:20:00"

# Event stats (aggregated)
event:{event_id}:total_regs   → 5000
event:{event_id}:entries       → 3200
event:{event_id}:lunch_used    → 2800

# Rate limiting (per scanner token)
rate_limit:{token}:scan        → 60 (count, reset every minute)
```

## 4. Queue Jobs

| Job | Queue | Description |
|-----|-------|-------------|
| `GenerateQRCodes` | low | Bulk QR generation after import |
| `SendBulkEmail` | high | Send 10k emails via Mailgun |
| `SendBulkSMS` | high | Send SMS via local gateway |
| `UpdateRegistrationFromScan` | default | Async DB write after Redis cache |

## 5. Excel Import Format (Expected Columns)

| Column | Required | Validation |
|--------|----------|-------------|
| name | Yes | not empty |
| email | Yes* | valid email format |
| phone | Yes* | +977XXXXXXXXX or 98XXXXXXXX |
| organization | No | - |
| designation | No | - |
| address | No | - |
| website | No | URL format |

*At least email or phone must be present.
```
