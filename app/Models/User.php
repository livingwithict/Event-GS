<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\CausesActivity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use CausesActivity, HasApiTokens, HasFactory, LogsActivity, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'column_preferences'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'column_preferences' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'role'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('user')
            ->setDescriptionForEvent(fn (string $eventName) => "User {$this->name} was {$eventName}");
    }

    /**
     * Filament only lets a user in if the model answers this. Without it access
     * is granted in local and refused everywhere else — including production.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->roleEnum() !== null;
    }

    public function roleEnum(): ?Role
    {
        return Role::tryFrom((string) $this->role);
    }

    public function roleLabel(): string
    {
        return $this->roleEnum()?->label() ?? (string) $this->role;
    }

    /** Ability check that does not need the Gate — handy inside queries and jobs. */
    public function hasAbility(string $ability): bool
    {
        return $this->roleEnum()?->can($ability) ?? false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->roleEnum() === Role::SuperAdmin;
    }

    public function isEventAdmin(): bool
    {
        return $this->roleEnum() === Role::EventAdmin;
    }

    public function isRegistrationStaff(): bool
    {
        return $this->roleEnum() === Role::RegistrationStaff;
    }

    public function isScanner(): bool
    {
        return $this->roleEnum() === Role::ScannerStaff;
    }

    public function isViewer(): bool
    {
        return $this->roleEnum() === Role::Viewer;
    }

    public function isFinance(): bool
    {
        return $this->roleEnum() === Role::Finance;
    }

    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    public function assignedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_user')
            ->withTimestamps()
            ->withPivot('assigned_by');
    }

    public function canAccessEvent(Event|int $event): bool
    {
        $role = $this->roleEnum();

        if (! $role) {
            return false;
        }

        if (! $role->isEventScoped()) {
            return true;
        }

        $eventId = $event instanceof Event ? $event->getKey() : $event;

        return $this->assignedEvents()->whereKey($eventId)->exists();
    }

    /** Events this user may work with. Super Admin gets everything. */
    public function accessibleEvents()
    {
        return $this->roleEnum()?->isEventScoped()
            ? $this->assignedEvents()
            : Event::query();
    }
}
