<?php

namespace App\Models\Concerns;

use App\Services\Images\ImageProcessor;
use Illuminate\Database\Eloquent\Model;

/**
 * Model ที่มีคอลัมน์ path รูป — กำหนด protected static array $imageFields = ['image_th', ...]
 * - เปลี่ยนรูปแล้วลบไฟล์เก่าทิ้ง
 * - ลบแบบ soft delete เก็บไฟล์ไว้ (กู้คืนได้) ลบไฟล์จริงเมื่อ force delete เท่านั้น
 */
trait HasImageFiles
{
    public static function bootHasImageFiles(): void
    {
        static::updated(function (Model $model): void {
            foreach (static::$imageFields as $field) {
                if ($model->wasChanged($field)) {
                    $old = $model->getOriginal($field);

                    if ($old && $old !== $model->getAttribute($field)) {
                        ImageProcessor::delete($old, static::thumbnailPathFor($old));
                    }
                }
            }
        });

        static::deleted(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            foreach (static::$imageFields as $field) {
                $path = $model->getAttribute($field);
                ImageProcessor::delete($path, $path ? static::thumbnailPathFor($path) : null);
            }
        });
    }

    /** @return list<string> */
    public static function imageFields(): array
    {
        return static::$imageFields;
    }

    protected static function thumbnailPathFor(string $path): string
    {
        return preg_replace('/\.webp$/', '_thumb.webp', $path);
    }

    /** URL สำหรับแสดงรูป (ผ่าน MediaController) */
    public function imageUrl(string $field): ?string
    {
        $path = $this->getAttribute($field);

        return $path ? route('media.show', ['path' => $path]) : null;
    }

    /** URL รูปตามภาษาปัจจุบัน: {$base}_en ว่าง -> ใช้ {$base}_th */
    public function localizedImageUrl(string $base = 'image', ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        if ($locale === 'en' && filled($this->getAttribute($base.'_en'))) {
            return $this->imageUrl($base.'_en');
        }

        return $this->imageUrl($base.'_th');
    }
}
