<?php

namespace App\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksUser;
use App\Services\Files\DocumentStorer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ไฟล์ดาวน์โหลด
 *   type = file -> อัปโหลดเข้าระบบ ต้อง scan_status = clean จึงจะแสดง
 *   type = link -> ลิงก์ภายนอก
 */
class Download extends Model
{
    use HasBilingualFields, LogsActivity, SoftDeletes, TracksUser;

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

    protected static function booted(): void
    {
        static::creating(function (Download $download): void {
            if ($download->sort_order === null) {
                $download->sort_order = ((int) static::withTrashed()->max('sort_order')) + 1;
            }
        });

        static::saving(function (Download $download): void {
            // ให้ตรงกับ CHECK chk_downloads_type: ไฟล์ต้องไม่มีลิงก์ / ลิงก์ต้องไม่มีไฟล์
            if ($download->type === self::TYPE_LINK) {
                $download->file_path = null;
                $download->original_name = null;
                $download->file_extension = null;
                $download->mime_type = null;
                $download->file_size = null;
                $download->file_hash = null;
                $download->scan_status = null;
                $download->scanned_at = null;

                return;
            }

            $download->external_url = null;

            // อัปไฟล์ใหม่ -> เติมข้อมูลไฟล์จากไฟล์ที่ DocumentStorer บันทึก (ลิงก์ /downloads/{id} เดิมใช้ต่อได้)
            if ($download->isDirty('file_path') && filled($download->file_path)) {
                $disk = Storage::disk(DocumentStorer::DISK);
                $extension = strtolower(pathinfo($download->file_path, PATHINFO_EXTENSION));

                $download->file_extension = $extension;
                $download->mime_type = DocumentStorer::RESPONSE_MIME[$extension] ?? 'application/octet-stream';
                $download->file_size = $disk->exists($download->file_path) ? $disk->size($download->file_path) : 0;
                $download->file_hash = $disk->exists($download->file_path) ? hash_file('sha256', $disk->path($download->file_path)) : null;
                $download->scan_status = DocumentStorer::scanResultFor($download->file_path) ?? self::SCAN_PENDING;
                $download->scanned_at = $download->scan_status === self::SCAN_PENDING ? null : now();

                // original_name มาจาก storeFileNamesIn ของฟอร์ม (ชื่อไฟล์ที่ผู้ใช้อัปโหลด)
                if (blank($download->original_name)) {
                    $download->original_name = basename($download->file_path);
                }
            }
        });

        // ไฟล์เดิมถูกแทนที่ / เปลี่ยนเป็นลิงก์ -> ลบไฟล์เก่า
        static::updated(function (Download $download): void {
            $old = $download->getOriginal('file_path');

            if ($download->wasChanged('file_path') && $old) {
                DocumentStorer::delete($old);
            }
        });

        static::forceDeleted(fn (Download $download) => DocumentStorer::delete($download->file_path));
    }

    public function isFile(): bool
    {
        return $this->type === self::TYPE_FILE;
    }

    /** ลิงก์สาธารณะ (คงที่ตาม id แม้อัปไฟล์ใหม่ทับ) */
    public function publicUrl(): string
    {
        return route('downloads.show', $this);
    }

    /** ชื่อไฟล์ตอนดาวน์โหลด: ใช้ชื่อเดิมของไฟล์ (ถ้าไม่มี ใช้หัวข้อ) */
    public function downloadName(): string
    {
        return $this->original_name ?: Str::limit($this->title_th, 150, '').'.'.$this->file_extension;
    }

    public function humanFileSize(): ?string
    {
        if ($this->file_size === null) {
            return null;
        }

        return match (true) {
            $this->file_size >= 1024 ** 2 => number_format($this->file_size / 1024 ** 2, 1).' MB',
            $this->file_size >= 1024 => number_format($this->file_size / 1024).' KB',
            default => $this->file_size.' B',
        };
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
