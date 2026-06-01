> **Note (2026-06-01):** Redis and Laravel Horizon have been temporarily removed from the project for shared hosting deployment. Queue and cache now use the database driver. The Redis sections below represent the intended architecture and will be re-implemented when migrating to a VPS. See `docs/deployment.md` for current setup details.

## 📁 File 6: `Tech_Stack_Details.md`

```markdown
# Technology Stack Rationale & Configuration

## Backend
| Component | Choice | Why |
|-----------|--------|-----|
| Framework | Laravel 11 | Built-in queues, events, ORM, FilamentPHP ecosystem |
| Language | PHP 8.2+ | Wide hosting support, large talent pool in Nepal |
| Database | PostgreSQL 15 | JSON support, full-text search, ACID compliance |
| Cache/Queue | Redis 7 | Fast in-memory, persistence option, Laravel Horizon support |

## Frontend
| Component | Choice | Why |
|-----------|--------|-----|
| Admin Panel | FilamentPHP | Generates CRUD UI in minutes, built on Livewire |
| Scanning UI | React 18 + Vite | PWA capability, camera access, offline support |
| QR Scanning | html5-qrcode | Pure JS, works on any mobile browser |

## Communication
| Service | Purpose | Alternatives |
|---------|---------|--------------|
| Mailgun | Bulk email | SendGrid, Amazon SES, Postmark |
| Sparrow SMS | Local SMS | Twilio (global), NCell Connect |

## Deployment
| Tool | Purpose |
|------|---------|
| Docker | Containerization |
| GitHub Actions | CI/CD |
| DigitalOcean | Hosting ($20/mo droplet) |
| Laravel Forge (optional) | Server management |

## Configuration Examples

### Laravel Octane for High Concurrency
```bash
composer require laravel/octane
php artisan octane:install --server=swoole
```

Then in `config/octane.php`:
```php
'swoole' => [
    'workers' => 4,
    'max_requests' => 1000,
],
```

### Redis Cache for QR Validation
```php
// In Registration model
protected static function booted()
{
    static::created(function ($registration) {
        Redis::setex("qr:{$registration->qr_hash}:entry", 86400, null);
        Redis::setex("qr:{$registration->qr_hash}:lunch", 86400, null);
    });
}
```

### Queue Horizon Configuration
```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['high', 'default', 'low'],
            'balance' => 'auto',
            'processes' => 10,
            'tries' => 3,
        ],
    ],
],
```

## Cost Estimation (Monthly)
| Service | Cost (USD) |
|---------|-------------|
| DigitalOcean 4GB/2CPU | $24 |
| Mailgun (10k emails) | $15 |
| Sparrow SMS (10k SMS) | ~$100 (depends on volume) |
| Backup storage | $5 |
| **Total** | ~$150 |

## Development Environment Setup (Local)
```bash
# Using Laravel Sail (Docker)
curl -s https://laravel.build/event-system | bash
cd event-system
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
```

## Production Deployment Checklist
- [ ] Set `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Configure HTTPS (LetsEncrypt)
- [ ] Set up database backups (cron job)
- [ ] Monitor queue workers (Horizon dashboard)
- [ ] Configure Redis maxmemory policy (allkeys-lru)
- [ ] Set up failed job handling (failed_jobs table)
```