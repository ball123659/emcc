<?php

namespace App\Models;

use App\Models\Concerns\HasBilingualFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ไฟล์แนบของข่าว — แสดงได้เฉพาะ scan_status = clean
 */
class NewsAttachment extends Model
{
    use HasBilingualFields;

    protected $fillable = [
        'news_id',
        'title_th',
        'title_en',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'scan_status',
        'sort_order',
    ];

    protected $attributes = [
        'scan_status' => Download::SCAN_PENDING,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }

    public function scopeClean(Builder $query): Builder
    {
        return $query->where('scan_status', Download::SCAN_CLEAN);
    }
}
