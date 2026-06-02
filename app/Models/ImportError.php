<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportError extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'import_batch_id', 'row_number', 'raw_data', 'error_message',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'created_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
