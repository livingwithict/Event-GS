<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isEventManager(): bool
    {
        return $this->role === 'event_manager';
    }

    public function isScanner(): bool
    {
        return $this->role === 'scanner';
    }

    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }

    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'created_by');
    }
}
