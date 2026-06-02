<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ScanLog extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'event_id', 'participant_id', 'action_type_id',
        'scanned_by', 'scan_device', 'scan_location',
        'remarks', 'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['event_id', 'participant_id', 'action_type_id', 'scanned_by', 'scanned_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('scan_log')
            ->setDescriptionForEvent(fn (string $eventName) => "Scan log was {$eventName}");
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function participant()
    {
        return $this->belongsTo(Registration::class, 'participant_id');
    }

    public function actionType()
    {
        return $this->belongsTo(ScanActionType::class, 'action_type_id');
    }

    public function scanner()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
