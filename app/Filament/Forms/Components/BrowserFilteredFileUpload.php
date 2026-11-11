<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\FileUpload;

/**
 * FileUpload ที่กรองชนิดไฟล์เฉพาะฝั่งเบราว์เซอร์ (FilePond)
 * ไม่แนบกฎ mimetypes ของ Laravel เพราะฝั่งเซิร์ฟเวอร์ตรวจละเอียดกว่าใน DocumentStorer
 * (นามสกุล + MIME จากเนื้อไฟล์ต่อชนิด + macro + ไวรัส) — กฎ mimetypes ทั่วไปปฏิเสธไฟล์ที่ถูกต้อง
 * เช่น .xls ที่ระบบอื่น export ออกมาเป็น HTML/XML
 */
class BrowserFilteredFileUpload extends FileUpload
{
    public function browserAcceptedFileTypes(array $types): static
    {
        $this->acceptedFileTypes = $types;

        return $this;
    }
}
