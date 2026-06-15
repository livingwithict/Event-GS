<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ParticipantCategory extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'event_id', 'label_template_id', 'name', 'description',
        'is_paid', 'price', 'early_bird_price', 'early_bird_until', 'currency',
        'badge_color', 'sort_order', 'is_active',
        'qr_access_permissions',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
            'price' => 'decimal:2',
            'early_bird_price' => 'decimal:2',
            'early_bird_until' => 'datetime',
            'sort_order' => 'integer',
            'qr_access_permissions' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('participant_category')
            ->setDescriptionForEvent(fn (string $eventName) => "Category {$this->name} was {$eventName}");
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class, 'category_id');
    }

    public function labelTemplate()
    {
        return $this->belongsTo(LabelTemplate::class, 'label_template_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
