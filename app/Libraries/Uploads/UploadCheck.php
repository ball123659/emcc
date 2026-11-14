<?php

namespace App\Libraries\Uploads;

use CodeIgniter\HTTP\Files\UploadedFile;

class UploadCheck
{

    public static function chosen(?UploadedFile $file): bool
    {
        return $file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE;
    }

    public static function assertUploaded(UploadedFile $file, int $maxKb): void
    {
        helper('ui');
        $mb = mb_label(upload_limit_kb($maxKb));

        match ($file->getError()) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => throw new UploadRejected("ไฟล์ใหญ่เกิน {$mb} (ขนาดสูงสุดที่เซิร์ฟเวอร์รับได้) กรุณาลดขนาดไฟล์ก่อนอัปโหลด"),
            UPLOAD_ERR_PARTIAL => throw new UploadRejected('อัปโหลดไฟล์ไม่ครบ (สัญญาณขาดหาย) กรุณาลองใหม่อีกครั้ง'),
            UPLOAD_ERR_NO_FILE => throw new UploadRejected('กรุณาเลือกไฟล์'),
            default            => throw new UploadRejected('เซิร์ฟเวอร์รับไฟล์ไม่ได้ กรุณาลองใหม่ หรือแจ้งผู้ดูแลระบบ'),
        };

        if (! $file->isValid() && ENVIRONMENT !== 'testing') {
            throw new UploadRejected('ไฟล์ที่อัปโหลดไม่ถูกต้อง กรุณาเลือกไฟล์ใหม่');
        }
    }
}
