> **Note (2026-06-01):** Redis and Laravel Horizon references in this document are not currently implemented. The project uses database driver for queue and cache. Scanning does direct PostgreSQL lookups instead of Redis caching. See `docs/deployment.md` for current setup.

## 📁 File 2: `Architectural_Design.md`

```markdown
# Architectural Design – Event Management System

## 1. High-Level Architecture (C4 Context Level)

```
[Admin Browser] --> [Laravel + Filament] --> [PostgreSQL]
                           |
[Scanner Phone] --> [React PWA] --> [Laravel API] --> [Redis]
                                                      --> [Queue Worker]
```

## 2. Component Breakdown

### 2.1 Backend (Laravel)
- **API Layer**: RESTful endpoints for scanning, meal validation, reporting.
- **Admin Layer**: FilamentPHP panels for event managers.
- **Queue Workers**: Process bulk emails, SMS, and QR generation.
- **Event Listeners**: Log communications, update audit trails.

### 2.2 Frontend Admin Panel (FilamentPHP)
- Built on Laravel Livewire.
- Provides CRUD for events, registrations, users.
- Excel import/export, reporting dashboards.

### 2.3 Scanning Interface (React PWA)
- Separate single-page application.
- Uses `html5-qrcode` for camera access.
- Makes API calls to Laravel backend.
- Can be installed on mobile devices.

### 2.4 Database (PostgreSQL)
- Primary data store.
- JSON fields for flexible event metadata.
- Full-text search indexes.

### 2.5 Caching & Queue (Redis)
- Cache for QR code validation (key: `qr:{code}:status`).
- Queue driver for Laravel Horizon.
- Session storage (optional).

## 3. Data Flow Diagrams

### Pre-Event Flow
```
Excel Upload → Validation → Batch Insert → QR Generation (background) → Redis cache preload
```
### On-Site Scanning Flow
```
Scan QR → API /scan?code=XYZ → Redis lookup → if miss, DB lookup → Update usage (queue) → Return guest data
```

### Meal Validation Flow
```
Scan QR for Lunch → API /meal → Check Redis `meal:lunch:{qr}` → if not exists → write to Redis + queue DB update → return success
```

## 4. Deployment Architecture (Docker Compose)

```yaml
version: '3.8'
services:
  app:
    build: .
    ports:
      - "8000:8000"
    environment:
      - DB_HOST=postgres
      - REDIS_HOST=redis
    depends_on:
      - postgres
      - redis

  queue:
    build: .
    command: php artisan horizon
    depends_on:
      - redis

  postgres:
    image: postgres:15
    volumes:
      - pgdata:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine
    command: redis-server --appendonly yes

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - ./nginx.conf:/etc/nginx/conf.d/default.conf

volumes:
  pgdata:
```

## 5. Security Architecture
- **API Authentication**: Laravel Sanctum (token-based).
- **QR Code Signing**: HMAC-SHA256 with server secret.
- **Rate Limiting**: 60 scans per minute per scanner token.
- **Audit Logging**: All changes logged with `spatie/laravel-activitylog`.

## 6. Scalability Considerations
- **Horizontal scaling**: Stateless app servers behind load balancer.
- **Database read replicas** for reporting.
- **Redis Cluster** for high-availability caching.
- **Queue workers** can be scaled independently.
```
