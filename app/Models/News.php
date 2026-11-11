<?php

namespace App\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ข่าวประชาสัมพันธ์
 */
class News extends Model
{
    use HasBilingualFields, SoftDeletes, TracksUser;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_HIDDEN = 'hidden';

    protected $table = 'news';

    protected $fillable = [
        'category_id',
        'slug',
        'title_th',
        'title_en',
        'excerpt_th',
        'excerpt_en',
        'content_th',
        'content_en',
        'cover_image',
        'cover_thumb',
        'cover_alt_th',
        'cover_alt_en',
        'status',
        'published_at',
        'is_pinned',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'is_pinned' => false,
        'view_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_pinned' => 'boolean',
            'view_count' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(NewsImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(NewsAttachment::class)->orderBy('sort_order')->orderBy('id');
    }

    /** ข่าวที่เผยแพร่แล้ว (ถึงเวลาเผยแพร่) ปักหมุดขึ้นก่อน */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->where('published_at', '<=', now())
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at');
    }
}
