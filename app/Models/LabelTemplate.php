<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabelTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'template_name', 'width', 'height', 'orientation',
        'show_qr', 'show_designation', 'show_organization',
        'show_category_color', 'font_size_name',
        'margin_top', 'margin_right', 'margin_bottom', 'margin_left',
        'config_json',
    ];

    protected function casts(): array
    {
        return [
            'show_qr' => 'boolean',
            'show_designation' => 'boolean',
            'show_organization' => 'boolean',
            'show_category_color' => 'boolean',
            'config_json' => 'array',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
