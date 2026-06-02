<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $fillable = [
        'event_id', 'imported_by', 'file_name',
        'total_rows', 'success_rows', 'failed_rows', 'status',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function errors()
    {
        return $this->hasMany(ImportError::class, 'import_batch_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function markProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markCompleted(int $total, int $success, int $failed): void
    {
        $this->update([
            'status' => 'completed',
            'total_rows' => $total,
            'success_rows' => $success,
            'failed_rows' => $failed,
        ]);
    }

    public function markFailed(): void
    {
        $this->update(['status' => 'failed']);
    }
}
