<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Event extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'invitation_code_format', 'description',
        'logo_path', 'banner_path', 'ticket_path',
        'event_date', 'start_datetime', 'end_datetime',
        'registration_open_at', 'registration_close_at',
        'venue', 'contact_info',
        'meal_types', 'max_capacity',
        'settings', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'registration_open_at' => 'datetime',
            'registration_close_at' => 'datetime',
            'meal_types' => 'array',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Event $event) {
            if (empty($event->slug)) {
                $event->slug = Str::slug($event->name);
            }

            if (empty($event->start_datetime) && $event->event_date) {
                $event->start_datetime = $event->event_date->startOfDay();
            }

            if (empty($event->event_date) && $event->start_datetime) {
                $event->event_date = $event->start_datetime->toDateString();
            }
        });
    }

    /** Absolute URL for the logo — emails and public pages need the full host. */
    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return url(Storage::disk('public')->url($this->logo_path));
    }

    /**
     * Logo as a data URI for PDFs. Dompdf cannot fetch remote files, and reading
     * through the disk works whether or not public/storage is linked.
     */
    public function logoDataUri(): ?string
    {
        return $this->imageDataUri($this->logo_path);
    }

    /** Where the name and QR sit on the invitation card, in pixels of the artwork. */
    public const TICKET_LAYOUT_DEFAULTS = [
        'width' => 1492,
        'height' => 1054,
        'name_x' => 200,
        'name_y' => 500,
        // Name must not run past x=725, so 200 + 525.
        'name_w' => 525,
        'name_h' => 45,
        'name_align' => 'left',
        'name_color' => '#111827',
        'qr_x' => 1110,
        'qr_y' => 500,
        'qr_size' => 234,
    ];

    public function ticketLayout(): array
    {
        return array_merge(self::TICKET_LAYOUT_DEFAULTS, array_filter(
            $this->settings['ticket_layout'] ?? [],
            fn ($v) => $v !== null && $v !== '',
        ));
    }

    /** Invitation card artwork, as a data URI for the ticket PDF. */
    public function ticketDataUri(): ?string
    {
        return $this->imageDataUri($this->ticket_path);
    }

    private function imageDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($path));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('event')
            ->setDescriptionForEvent(fn (string $eventName) => "Event {$this->name} was {$eventName}");
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ParticipantCategory::class)->ordered();
    }

    public function scanActionTypes(): HasMany
    {
        return $this->hasMany(ScanActionType::class)->ordered();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user')
            ->withTimestamps()
            ->withPivot('assigned_by');
    }

    public function getStats(): array
    {
        return [
            'total_registrations' => $this->registrations()->count(),
            'total_entries' => $this->registrations()->whereNotNull('entry_time')->count(),
            'lunch_used' => $this->registrations()->whereNotNull('lunch_used_at')->count(),
            'dinner_used' => $this->registrations()->whereNotNull('dinner_used_at')->count(),
        ];
    }

    public function getCategoryStats(): array
    {
        return $this->categories()
            ->withCount('registrations')
            ->get()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'badge_color' => $cat->badge_color,
                'is_paid' => $cat->is_paid,
                'price' => $cat->price,
                'registrations_count' => $cat->registrations_count,
            ])
            ->toArray();
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function isMultiDay(): bool
    {
        return $this->start_datetime && $this->end_datetime
            && $this->start_datetime->startOfDay()->lt($this->end_datetime->startOfDay());
    }

    public function getEventDays(): Collection
    {
        if (! $this->start_datetime || ! $this->end_datetime) {
            return collect([$this->start_datetime ? $this->start_datetime->startOfDay() : now()->startOfDay()]);
        }

        $days = collect();
        $current = $this->start_datetime->startOfDay()->copy();
        $end = $this->end_datetime->startOfDay();

        while ($current->lte($end)) {
            $days->push($current->copy());
            $current->addDay();
        }

        return $days;
    }

    public function getTotalDays(): int
    {
        return $this->getEventDays()->count();
    }

    public function getCurrentDay(): ?int
    {
        if (! $this->start_datetime || ! $this->end_datetime) {
            return null;
        }

        $today = now()->startOfDay();

        foreach ($this->getEventDays() as $index => $day) {
            if ($day->eq($today)) {
                return $index + 1;
            }
        }

        return null;
    }

    public function getDayDate(int $dayNumber): ?Carbon
    {
        $days = $this->getEventDays();

        return $days[$dayNumber - 1] ?? null;
    }

    public function getStatsForDay(int $dayNumber): array
    {
        $dayDate = $this->getDayDate($dayNumber);

        if (! $dayDate) {
            return [];
        }

        $dayStart = $dayDate->copy()->startOfDay();
        $dayEnd = $dayDate->copy()->endOfDay();

        $dayActionIds = ScanActionType::where('event_id', $this->id)
            ->where('action_code', 'LIKE', 'DAY'.$dayNumber.'_%')
            ->pluck('id');

        return ScanLog::where('event_id', $this->id)
            ->whereIn('action_type_id', $dayActionIds)
            ->whereBetween('scanned_at', [$dayStart, $dayEnd])
            ->selectRaw('action_type_id, COUNT(DISTINCT participant_id) as unique_scans')
            ->groupBy('action_type_id')
            ->pluck('unique_scans', 'action_type_id')
            ->toArray();
    }

    public function isRegistrationOpen(): bool
    {
        if (! $this->isPublished()) {
            return false;
        }

        $now = now();

        if ($this->registration_open_at && $now->lt($this->registration_open_at)) {
            return false;
        }

        if ($this->registration_close_at && $now->gt($this->registration_close_at)) {
            return false;
        }

        return true;
    }

    public function isAtCapacity(): bool
    {
        if (! $this->max_capacity) {
            return false;
        }

        return $this->registrations()->count() >= $this->max_capacity;
    }

    public function settingEnabled(string $key): bool
    {
        return $this->settings[$key] ?? true;
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_datetime', '>=', now())->orderBy('start_datetime');
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
