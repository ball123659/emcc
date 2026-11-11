<?php

namespace App\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Models\Concerns\HasDisplayWindow;
use App\Models\Concerns\HasImageFiles;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Highlight — Popup หน้าเว็บ (แสดงต่อกันทีละอันตาม sort_order)
 */
class Popup extends Model
{
    use HasBilingualFields, HasDisplayWindow, HasImageFiles, LogsActivity, SoftDeletes, TracksUser;

    protected static array $imageFields = ['image_th', 'image_en'];

    protected $fillable = [
        'title',
        'image_th',
        'image_en',
        'alt_th',
        'alt_en',
        'link_url',
        'open_new_tab',
        'start_date',
        'end_date',
        'is_active',
        'sort_order',
    ];

    protected $attributes = [
        'open_new_tab' => true,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'open_new_tab' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** popup ที่ต้องแสดงวันนี้ เรียงตามลำดับ */
    public function scopeVisible(Builder $query): Builder
    {
        $today = today()->toDateString();

        return $query->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
