<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\CausesActivity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use CausesActivity, HasApiTokens, HasFactory, LogsActivity, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
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

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isScanner(): bool
    {
        return $this->role === 'scanner';
    }

    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }

    public function isFinance(): bool
    {
        return $this->role === 'finance';
    }

    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    public function assignedEvents()
    {
        return $this->belongsToMany(Event::class, 'event_user')
            ->withTimestamps()
            ->withPivot('assigned_by');
    }
}
