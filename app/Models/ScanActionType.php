<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ScanActionType extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'event_id', 'action_name', 'action_code',
        'column_mapping', 'allow_multiple', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'allow_multiple' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('scan_action_type')
            ->setDescriptionForEvent(fn (string $eventName) => "Action type {$this->action_name} was {$eventName}");
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function scanLogs()
    {
        return $this->hasMany(ScanLog::class, 'action_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('action_name');
    }
}
