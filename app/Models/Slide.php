<?php

namespace App\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Highlight — Slide Show หน้าแรก
 */
class Slide extends Model
{
    use HasBilingualFields, SoftDeletes, TracksUser;

    public const LINK_NONE = 'none';

    public const LINK_NEWS = 'news';

    public const LINK_URL = 'url';

    protected $fillable = [
        'title',
        'image_th',
        'image_en',
        'image_mobile',
        'alt_th',
        'alt_en',
        'link_type',
        'news_id',
        'link_url',
        'open_new_tab',
        'start_date',
        'end_date',
        'is_active',
        'sort_order',
    ];

    protected $attributes = [
        'link_type' => self::LINK_NONE,
        'open_new_tab' => true,
        'is_active' => true,
        'sort_order' => 0,
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

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }

    /** สไลด์ที่ต้องแสดงวันนี้ เรียงตามลำดับ */
    public function scopeVisible(Builder $query): Builder
    {
        $today = today()->toDateString();

        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today))
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
