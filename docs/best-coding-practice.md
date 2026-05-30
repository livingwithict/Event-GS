
## 📁 File 4: `Best_Coding_Practices.md`

```markdown
# Best Coding Practices for Event Management System

## 1. Modular Architecture

Create Laravel modules using `nwidart/laravel-modules` or manual folder structure:

```
app/Modules/
├── Event/
│   ├── Http/Controllers/
│   ├── Models/Event.php
│   ├── Services/EventService.php
│   └── Events/EventCreated.php
├── Registration/
├── Communication/
├── Scanning/
└── Reporting/
```

## 2. Repository Pattern

```php
interface RegistrationRepositoryInterface {
    public function findByEventAndQr(int $eventId, string $qrHash);
    public function updateMealUsage(int $id, string $mealType);
    public function bulkInsert(array $registrations);
}

class RegistrationRepository implements RegistrationRepositoryInterface {
    public function findByEventAndQr(int $eventId, string $qrHash) {
        return Registration::where('event_id', $eventId)
            ->where('qr_hash', $qrHash)
            ->firstOrFail();
    }
}
```

## 3. DTOs (Data Transfer Objects)

```php
class ScanResponseDTO {
    public function __construct(
        public readonly string $name,
        public readonly string $organization,
        public readonly ?string $entryTime,
        public readonly bool $lunchUsed,
        public readonly bool $dinnerUsed,
    ) {}
    
    public static function fromModel(Registration $reg): self {
        return new self(...);
    }
}
```

## 4. Event-Driven Design

```php
// Fire event when meal is used
event(new MealUsed($registration, 'lunch'));

// Listener updates Redis and logs
class LogMealUsage {
    public function handle(MealUsed $event) {
        Redis::set("qr:{$event->qrHash}:lunch", now());
        ActivityLog::add("Meal used", $event->registration);
    }
}
```

## 5. Idempotency for Scanning

Use a unique transaction ID (scan session ID) to prevent double processing:

```php
// Client sends X-Request-Id header
$lockKey = "scan:{$requestId}";
if (Redis::setnx($lockKey, 1)) {
    Redis::expire($lockKey, 5); // 5 seconds
    // Process scan
} else {
    return response('Duplicate request', 429);
}
```

## 6. Soft Deletes & Audit Logging

```php
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Registration extends Model {
    use SoftDeletes, LogsActivity;
    
    protected static $logAttributes = ['name', 'email', 'organization'];
    protected static $logOnlyDirty = true;
}
```

## 7. Validation Layers

### Frontend (React):
- Basic format validation (email, phone)
- Disable submit buttons during async

### Backend (Laravel FormRequest):
```php
class ImportRequest extends FormRequest {
    public function rules() {
        return [
            'file' => 'required|mimes:xlsx,csv|max:10240',
            'event_id' => 'exists:events,id'
        ];
    }
}
```

### Database:
- Unique constraints on `qr_hash`
- Foreign key constraints
- Check constraints (e.g., `entry_time` cannot be before event start)

## 8. Environment-Based Configuration

```env
# .env.production
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=...
SMS_DRIVER=sparrow
REDIS_CLIENT=predis
QUEUE_CONNECTION=redis
```

## 9. Testing Strategy

```bash
# Run all tests
php artisan test

# Feature test for scanning endpoint
public function test_scanning_valid_qr_records_entry()
{
    $registration = Registration::factory()->create();
    $response = $this->postJson('/api/scan', [
        'code' => $registration->qr_hash
    ]);
    $response->assertStatus(200);
    $this->assertNotNull($registration->fresh()->entry_time);
}
```

## 10. Code Quality Tools

| Tool | Purpose |
|------|---------|
| Laravel Pint | Code style fixing |
| PHPStan (level 8) | Static analysis |
| Larastan | Laravel-specific rules |
| PHPUnit | Unit/Feature tests |
| Laravel Debugbar | Development profiling |

## 11. Git Workflow

- **main** – production
- **staging** – pre-production testing
- **feature/*** – individual features
- Commit messages: `feat: add excel import`, `fix: prevent double meal scan`, `docs: update api spec`
```

