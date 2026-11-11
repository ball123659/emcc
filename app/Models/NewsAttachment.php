<?php

namespace App\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Services\Files\DocumentStorer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * ไฟล์แนบของข่าว — ดาวน์โหลดได้เฉพาะ scan_status = clean (ผ่าน controller)
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

    protected static function booted(): void
    {
        static::saving(function (NewsAttachment $attachment): void {
            if (! $attachment->isDirty('file_path') || blank($attachment->file_path)) {
                return;
            }

            $extension = strtolower(pathinfo($attachment->file_path, PATHINFO_EXTENSION));
            $disk = Storage::disk(DocumentStorer::DISK);

            $attachment->mime_type = DocumentStorer::RESPONSE_MIME[$extension] ?? 'application/octet-stream';
            $attachment->file_size = $disk->exists($attachment->file_path) ? $disk->size($attachment->file_path) : 0;
            $attachment->scan_status = DocumentStorer::scanResultFor($attachment->file_path) ?? Download::SCAN_PENDING;
            $attachment->original_name = filled($attachment->original_name) ? $attachment->original_name : basename($attachment->file_path);
        });

        static::updated(function (NewsAttachment $attachment): void {
            if ($attachment->wasChanged('file_path') && $attachment->getOriginal('file_path')) {
                DocumentStorer::delete($attachment->getOriginal('file_path'));
            }
        });

        static::deleted(fn (NewsAttachment $attachment) => DocumentStorer::delete($attachment->file_path));
    }

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }

    public function scopeClean(Builder $query): Builder
    {
        return $query->where('scan_status', Download::SCAN_CLEAN);
    }

    public function downloadUrl(): string
    {
        return route('news.attachment', $this);
    }
}
