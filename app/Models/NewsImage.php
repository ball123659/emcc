<?php

namespace App\Models;

use App\Models\Concerns\HasBilingualFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * แกลเลอรีรูปของข่าว
 */
class NewsImage extends Model
{
    use HasBilingualFields;

    protected $fillable = [
        'news_id',
        'image_path',
        'thumb_path',
        'caption_th',
        'caption_en',
        'width',
        'height',
        'file_size',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'file_size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }
}
