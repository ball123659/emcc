<?php

namespace App\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ไฟล์ดาวน์โหลด
 *   type = file -> อัปโหลดเข้าระบบ ต้อง scan_status = clean จึงจะแสดง
 *   type = link -> ลิงก์ภายนอก
 */
class Download extends Model
{
    use HasBilingualFields, SoftDeletes, TracksUser;

    public const TYPE_FILE = 'file';

    public const TYPE_LINK = 'link';

    public const SCAN_PENDING = 'pending';

    public const SCAN_CLEAN = 'clean';

    public const SCAN_INFECTED = 'infected';

    public const SCAN_FAILED = 'failed';

    /** นามสกุลไฟล์ที่อนุญาต (ไม่รับไฟล์ macro) */
    public const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt',
        'jpg', 'png', 'webp', 'mp4', 'mp3',
    ];

    protected $fillable = [
        'category_id',
        'title_th',
        'title_en',
        'description_th',
        'description_en',
        'type',
        'file_path',
        'original_name',
        'file_extension',
        'mime_type',
        'file_size',
        'file_hash',
        'scan_status',
        'scanned_at',
        'external_url',
        'is_active',
        'sort_order',
    ];

    protected $attributes = [
        'type' => self::TYPE_FILE,
        'download_count' => 0,
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'scanned_at' => 'datetime',
            'download_count' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DownloadCategory::class, 'category_id');
    }

    /** รายการที่แสดงหน้าเว็บได้: เปิดอยู่ และถ้าเป็นไฟล์ต้องสแกนผ่านแล้ว */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->where('type', self::TYPE_LINK)
                ->orWhere(fn (Builder $q) => $q->where('type', self::TYPE_FILE)->where('scan_status', self::SCAN_CLEAN)))
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
