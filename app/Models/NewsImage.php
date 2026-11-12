<?php

namespace App\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Models\Concerns\HasImageFiles;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * แกลเลอรีรูปของข่าว (ลบข่าวถาวร -> ลบรูปตาม FK cascade; ลบรูปทีละรูป -> ลบไฟล์)
 */
class NewsImage extends Model
{
    use HasBilingualFields, HasImageFiles, LogsActivity;

    protected static array $imageFields = ['image_path'];

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

    protected static function booted(): void
    {
        // เติม thumbnail / ขนาดรูป จากไฟล์ที่ ImageProcessor สร้าง
        static::saving(function (NewsImage $image): void {
            if (! $image->isDirty('image_path') || blank($image->image_path)) {
                return;
            }

            $disk = Storage::disk('local');
            $image->thumb_path = static::thumbnailPathFor($image->image_path);

            if ($disk->exists($image->image_path)) {
                $size = @getimagesize($disk->path($image->image_path));
                $image->width = $size[0] ?? null;
                $image->height = $size[1] ?? null;
                $image->file_size = $disk->size($image->image_path);
            }
        });
    }

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }
}
