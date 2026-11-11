<?php

namespace App\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Models\Concerns\HasImageFiles;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksUser;
use App\Services\Html\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ข่าวประชาสัมพันธ์
 * - เนื้อหา rich text ถูกกรอง HTML (allowlist) ทุกครั้งที่บันทึก
 * - slug สร้างอัตโนมัติถ้าเว้นว่าง
 * - status = published + published_at (ระดับวัน) ในอนาคต = ตั้งเวลาเผยแพร่
 */
class News extends Model
{
    use HasBilingualFields, HasImageFiles, LogsActivity, SoftDeletes, TracksUser;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_HIDDEN = 'hidden';

    public const STATUSES = [
        self::STATUS_DRAFT => 'ร่าง',
        self::STATUS_PUBLISHED => 'เผยแพร่',
        self::STATUS_HIDDEN => 'ซ่อน',
    ];

    /** สถานะที่แสดงในหลังบ้าน (เพิ่ม "ตั้งเวลาเผยแพร่") */
    public const DISPLAY_SCHEDULED = 'scheduled';

    public const DISPLAY_LABELS = [
        self::STATUS_DRAFT => 'ร่าง',
        self::STATUS_PUBLISHED => 'เผยแพร่แล้ว',
        self::DISPLAY_SCHEDULED => 'ตั้งเวลาเผยแพร่',
        self::STATUS_HIDDEN => 'ซ่อน',
    ];

    public const DISPLAY_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_PUBLISHED => 'success',
        self::DISPLAY_SCHEDULED => 'info',
        self::STATUS_HIDDEN => 'warning',
    ];

    protected static array $imageFields = ['cover_image'];

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

    protected static function booted(): void
    {
        static::created(function (News $news): void {
            if ($news->hasTemporarySlug()) {
                $news->slug = static::uniqueSlug(($news->published_at ?? now())->format('Y-m').'-'.$news->getKey(), $news);
                $news->saveQuietly();
            }
        });

        static::saving(function (News $news): void {
            // วันที่เผยแพร่เป็นระดับวัน (เวลา 00:00) — เผยแพร่โดยไม่ระบุวัน = วันนี้
            if ($news->status === self::STATUS_PUBLISHED && $news->published_at === null) {
                $news->published_at = today();
            }

            if ($news->published_at !== null && $news->isDirty('published_at')) {
                $news->published_at = $news->published_at->copy()->startOfDay();
            }

            if (blank($news->slug)) {
                $news->slug = static::generateSlug($news);
            }

            if ($news->isSlugLocked() && $news->isDirty('slug')) {
                $news->slug = $news->getOriginal('slug');
            }

            // รูปปกย่อ (_thumb.webp) ถูกสร้างคู่กับรูปปกโดย ImageProcessor
            if ($news->isDirty('cover_image')) {
                $news->cover_thumb = $news->cover_image ? static::thumbnailPathFor($news->cover_image) : null;
            }
        });
    }

    // ---------- เนื้อหา: กรอง HTML ก่อนเก็บเสมอ ----------

    protected function contentTh(): Attribute
    {
        return Attribute::set(fn (?string $value) => HtmlSanitizer::clean($value) ?? '');
    }

    protected function contentEn(): Attribute
    {
        return Attribute::set(fn (?string $value) => blank(strip_tags((string) $value)) ? null : HtmlSanitizer::clean($value));
    }

    protected function slug(): Attribute
    {
        return Attribute::set(fn (?string $value) => blank($value) ? null : Str::slug(trim($value)));
    }

    /**
     * slug: ปี-เดือน-ชื่อภาษาอังกฤษ เช่น 2026-11-pm25-report
     * ไม่มีชื่ออังกฤษ (แปลงอักษรไทยเป็น URL ไม่ได้) -> ปี-เดือน-เลขข่าว เช่น 2026-11-15 (เติมหลังบันทึกเพราะต้องรู้ id)
     * ไม่ซ้ำ (รวมข่าวที่ถูกลบ)
     */
    public static function generateSlug(News $news): string
    {
        $date = ($news->published_at ?? now())->format('Y-m');
        $words = Str::slug(Str::words((string) ($news->title_en ?: Str::ascii((string) $news->title_th)), 8, ''));

        if ($words === '' || strlen($words) < 3) {
            $base = $news->exists ? "{$date}-{$news->getKey()}" : "{$date}-tmp-".Str::lower(Str::random(10));
        } else {
            $base = trim($date.'-'.Str::limit($words, 120, ''), '-');
        }

        return static::uniqueSlug($base, $news);
    }

    private static function uniqueSlug(string $base, News $news): string
    {
        $slug = $base;
        $i = 2;

        while (static::withTrashed()->where('slug', $slug)->when($news->exists, fn ($q) => $q->whereKeyNot($news->getKey()))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /** slug ชั่วคราวของข่าวชื่อไทยล้วน (ถูกแทนด้วย ปี-เดือน-เลขข่าว ทันทีหลังสร้าง) */
    public function hasTemporarySlug(): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-tmp-[a-z0-9]{10}$/', (string) $this->slug);
    }

    /** ข่าวที่เผยแพร่แล้วห้ามเปลี่ยน slug เพื่อไม่ให้ลิงก์ที่แชร์ไปแล้วเสีย */
    public function isSlugLocked(): bool
    {
        return $this->exists && $this->getOriginal('status') === self::STATUS_PUBLISHED
            && $this->getOriginal('published_at') !== null
            && ! Carbon::parse($this->getOriginal('published_at'))->isFuture();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ---------- ความสัมพันธ์ ----------

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

    // ---------- การแสดงผล ----------

    public function displayStatus(): string
    {
        if ($this->status === self::STATUS_PUBLISHED && $this->published_at?->isFuture()) {
            return self::DISPLAY_SCHEDULED;
        }

        return $this->status;
    }

    public function isPubliclyVisible(): bool
    {
        return ! $this->trashed()
            && $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && ! $this->published_at->isFuture();
    }

    /** เพิ่มยอดเข้าชม (ไม่แตะ updated_at / ไม่ลง activity log) */
    public function recordView(): void
    {
        static::withoutTimestamps(fn () => static::query()->whereKey($this->getKey())->update(['view_count' => DB::raw('view_count + 1')]));
        $this->view_count++;
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
