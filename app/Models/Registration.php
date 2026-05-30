<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Registration extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'event_id', 'unique_code', 'qr_hash', 'name', 'email', 'phone',
        'organization', 'designation', 'address', 'website',
        'entry_time', 'lunch_used_at', 'dinner_used_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_time' => 'datetime',
            'lunch_used_at' => 'datetime',
            'dinner_used_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'entry_time', 'lunch_used_at', 'dinner_used_at', 'event_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('registration')
            ->setDescriptionForEvent(fn (string $eventName) => "Registration {$this->name} ({$this->unique_code}) was {$eventName}");
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
        });
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function communications()
    {
        return $this->hasMany(Communication::class);
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
