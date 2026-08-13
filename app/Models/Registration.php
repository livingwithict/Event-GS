<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Registration extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'event_id', 'category_id', 'promo_code_id', 'registration_source', 'unique_code', 'guest_number', 'qr_hash', 'salutation', 'name', 'email', 'phone',
        'organization', 'designation', 'address', 'website',
        'photo_path', 'meal_preference', 'special_assistance', 'notes', 'pan_vat', 'gender', 'consented_at',
        'payment_status', 'paid_at',
        'entry_time', 'lunch_used_at', 'dinner_used_at',
        'label_printed', 'label_printed_at', 'label_printed_by', 'label_collected_at',
        'badge_status', 'approval_status',
        'group_id', 'companion_count',
    ];

    protected function casts(): array
    {
        return [
            'entry_time' => 'datetime',
            'lunch_used_at' => 'datetime',
            'dinner_used_at' => 'datetime',
            'consented_at' => 'datetime',
            'paid_at' => 'datetime',
            'label_printed' => 'boolean',
            'label_printed_at' => 'datetime',
            'label_collected_at' => 'datetime',
            'badge_status' => 'string',
            'companion_count' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'entry_time', 'lunch_used_at', 'dinner_used_at', 'event_id', 'category_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('registration')
            ->setDescriptionForEvent(fn (string $eventName) => "Registration {$this->name} ({$this->unique_code}) was {$eventName}");
    }

    /** Titles offered in the registration forms. Add to this list, not to each form. */
    public const SALUTATIONS = ['Mr.', 'Mrs.', 'Ms.', 'Dr.', 'Er.', 'Prof.', 'Prof. Dr.', 'Adv.', 'Hon.', 'CA.'];

    /** Name as it should be printed and read out: "Dr. Sita Rai". */
    public function displayName(): string
    {
        return trim(($this->salutation ? $this->salutation.' ' : '').$this->name);
    }

    protected static function booted(): void
    {
        static::creating(function (Registration $reg) {
            if (empty($reg->unique_code)) {
                $reg->unique_code = (string) Str::uuid();
            }
            if (empty($reg->qr_hash)) {
                $reg->qr_hash = hash_hmac('sha256', $reg->unique_code, config('app.key'));
            }
            if (empty($reg->guest_number) && $reg->event_id) {
                $reg->guest_number = self::generateGuestNumber($reg->event_id);
            }
        });
    }

    public static function generateGuestNumber(int $eventId): string
    {
        $event = Event::find($eventId);

        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $event?->invitation_code_format));

        if ($prefix === '') {
            // No format set on the event: fall back to the event slug.
            $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper(Str::slug($event?->slug ?? 'EVT', ''))));
            $prefix = str_pad(substr($prefix, 0, 6), 3, 'EVT');
        }

        $chars = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        do {
            $random = substr(str_shuffle($chars), 0, 5);
            $guestNumber = "{$prefix}-{$random}";
        } while (self::where('event_id', $eventId)->where('guest_number', $guestNumber)->exists());

        return $guestNumber;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ParticipantCategory::class, 'category_id');
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class);
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class, 'participant_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function isPaymentRequired(): bool
    {
        return $this->category?->is_paid && $this->event->settingEnabled('enable_payment');
    }

    public function isCertificateEligible(int $minAttendanceHours = 2): bool
    {
        if (! $this->entry_time) {
            return false;
        }

        $duration = $this->entry_time->diffInHours(now());

        return $duration >= $minAttendanceHours;
    }

    public function hasAction(ScanActionType $actionType): bool
    {
        return ScanLog::where('participant_id', $this->id)
            ->where('action_type_id', $actionType->id)
            ->exists();
    }

    public function canPerformAction(string $actionCode): bool
    {
        $category = $this->category;

        if (! $category) {
            return true;
        }

        $permissions = $category->qr_access_permissions;

        if (empty($permissions)) {
            return true;
        }

        return in_array($actionCode, $permissions);
    }

    public function recordAction(ScanActionType $actionType, ?int $scannedBy = null): bool
    {
        if (! $actionType->allow_multiple && $this->hasAction($actionType)) {
            return false;
        }

        if ($actionType->column_mapping) {
            $column = $actionType->column_mapping;
            if (! $actionType->allow_multiple && $this->$column !== null) {
                return false;
            }
            $this->update([$column => now()]);
        }

        if ($actionType->action_code === 'BADGE_COLLECT') {
            $this->update([
                'badge_status' => 'collected',
                'label_collected_at' => now(),
            ]);
        }

        ScanLog::create([
            'event_id' => $this->event_id,
            'participant_id' => $this->id,
            'action_type_id' => $actionType->id,
            'scanned_by' => $scannedBy,
            'scanned_at' => now(),
            'scan_date' => now()->toDateString(),
        ]);

        return true;
    }

    public function getActionStatuses(): array
    {
        $actionTypes = $this->event->scanActionTypes()->active()->get();
        $scanLogs = $this->scanLogs()->with('actionType')->get()->keyBy('action_type_id');

        return $actionTypes->map(function ($type) use ($scanLogs) {
            $log = $scanLogs->get($type->id);

            return [
                'action_type_id' => $type->id,
                'action_code' => $type->action_code,
                'action_name' => $type->action_name,
                'completed' => $log !== null,
                'scanned_at' => $log?->scanned_at?->toIso8601String(),
            ];
        })->toArray();
    }

    public function hasEntered(): bool
    {
        return $this->entry_time !== null;
    }

    public function hasUsedMeal(string $mealType): bool
    {
        return match ($mealType) {
            'lunch' => $this->lunch_used_at !== null,
            'dinner' => $this->dinner_used_at !== null,
            default => false,
        };
    }

    public function recordEntry(): bool
    {
        if ($this->hasEntered()) {
            return false;
        }
        $this->update(['entry_time' => now()]);

        return true;
    }

    public function recordMeal(string $mealType): bool
    {
        if ($this->hasUsedMeal($mealType)) {
            return false;
        }
        $column = match ($mealType) {
            'lunch' => 'lunch_used_at',
            'dinner' => 'dinner_used_at',
            default => null,
        };
        if ($column === null) {
            return false;
        }
        $this->update([$column => now()]);

        return true;
    }
}
