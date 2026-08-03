<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportStaging extends Model
{
    protected $table = 'import_staging';

    protected $fillable = [
        'event_id', 'import_batch_id', 'row_number', 'raw_data',
        'name', 'email', 'phone', 'organization', 'designation', 'category_name',
        'status', 'registration_id', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRegistered($query)
    {
        return $query->where('status', 'registered');
    }

    public function scopeErrored($query)
    {
        return $query->where('status', 'error');
    }
}
