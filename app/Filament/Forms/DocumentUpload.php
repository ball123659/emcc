<?php

namespace App\Filament\Forms;

use App\Filament\Forms\Components\BrowserFilteredFileUpload;
use App\Services\Files\DocumentStorer;
use App\Services\Files\FileRejectedException;
use Filament\Forms\Components\FileUpload;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * ช่องอัปโหลดไฟล์เอกสาร/สื่อมาตรฐานของหลังบ้าน
 * เบราว์เซอร์: จำกัดชนิด/ขนาด — เซิร์ฟเวอร์: whitelist นามสกุล + MIME จากเนื้อไฟล์ + กัน macro + สแกนไวรัส + ชื่อสุ่ม
 */
class DocumentUpload
{
    public static function make(string $name, string $directory): FileUpload
    {
        $maxKb = (int) config('emcc.files.max_upload_kb');
        $accepted = DocumentStorer::browserMimeTypes();

        return BrowserFilteredFileUpload::make($name)
            ->disk(DocumentStorer::DISK)
            ->directory($directory)
            ->visibility('private')
            ->browserAcceptedFileTypes($accepted)
            ->maxSize($maxKb)
            ->preventFilePathTampering(allowFilePathUsing: fn (string $file): bool => UploadedPathRegistry::allows($file))
            ->downloadable()
            ->openable(false)
            ->previewable(false)
            ->placeholder('ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์')
            ->helperText('รองรับ '.DocumentStorer::extensionsLabel().' ไม่เกิน '.($maxKb / 1024).' MB (ไม่รับไฟล์ที่มี macro)')
            ->validationMessages([
                'required' => 'กรุณาเลือกไฟล์',
                'max' => 'ไฟล์ใหญ่เกิน '.($maxKb / 1024).' MB',
            ])
            ->saveUploadedFileUsing(function (FileUpload $component, TemporaryUploadedFile $file) use ($directory): string {
                try {
                    return UploadedPathRegistry::remember(app(DocumentStorer::class)->store($file, $directory)->path);
                } catch (FileRejectedException $e) {
                    throw ValidationException::withMessages([$component->getStatePath() => $e->getMessage()]);
                }
            });
    }
}
