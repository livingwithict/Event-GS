<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'event_date', 'venue',
        'meal_types', 'max_capacity', 'settings', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'meal_types' => 'array',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (empty($event->slug)) {
                $event->slug = Str::slug($event->name);
            }
        });
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
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
}
