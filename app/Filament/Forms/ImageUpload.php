<?php

namespace App\Filament\Forms;

use App\Services\Images\ImageProcessor;
use App\Services\Images\ImageRejectedException;
use Filament\Forms\Components\FileUpload;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * ช่องอัปโหลดรูปมาตรฐานของหลังบ้าน
 * - ฝั่งเบราว์เซอร์: จำกัดชนิด/ขนาด + preview ก่อนบันทึก
 * - ฝั่งเซิร์ฟเวอร์: ตรวจ MIME จากเนื้อไฟล์, สแกนไวรัส, ย่อ + แปลง WebP, ลบ EXIF, ชื่อสุ่ม, เก็บนอก public
 */
class ImageUpload
{
    public static function make(string $name, string $directory, string $recommendedSize, bool $withThumbnail = false): FileUpload
    {
        $maxKb = (int) config('emcc.images.max_upload_kb');

        return FileUpload::make($name)
            ->image()
            ->disk(ImageProcessor::DISK)
            ->directory($directory)
            ->visibility('private')
            ->acceptedFileTypes(config('emcc.images.accepted_mimes'))
            ->maxSize($maxKb)
            ->imagePreviewHeight('180')
            ->placeholder('ลากรูปมาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์')
            ->preventFilePathTampering(allowFilePathUsing: fn (string $file): bool => UploadedPathRegistry::allows($file))
            ->downloadable(false)
            ->openable()
            ->helperText("ขนาดแนะนำ {$recommendedSize} px · รองรับ JPG, PNG, WebP ไม่เกิน ".($maxKb / 1024).' MB · ระบบย่อและแปลงเป็น WebP ให้อัตโนมัติ')
            ->validationMessages([
                'required' => 'กรุณาอัปโหลดรูป',
                'image' => 'ไฟล์ต้องเป็นรูปภาพ (JPG, PNG หรือ WebP)',
                'mimetypes' => 'รองรับเฉพาะรูป JPG, PNG หรือ WebP',
                'max' => 'ไฟล์รูปใหญ่เกิน '.($maxKb / 1024).' MB กรุณาลดขนาดก่อนอัปโหลด',
            ])
            ->saveUploadedFileUsing(function (FileUpload $component, TemporaryUploadedFile $file) use ($directory, $withThumbnail): string {
                try {
                    return UploadedPathRegistry::remember(app(ImageProcessor::class)->store($file, $directory, $withThumbnail)->path);
                } catch (ImageRejectedException $e) {
                    throw ValidationException::withMessages([$component->getStatePath() => $e->getMessage()]);
                }
            });
    }
}
