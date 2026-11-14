<?php

namespace App\Libraries\Uploads;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * ตรวจผลการอัปโหลดของ PHP แล้วแปลงเป็นข้อความภาษาไทยที่บอกวิธีแก้
 */
class UploadCheck
{
    /** ไฟล์ถูกเลือกมาหรือไม่ (ช่องว่าง = ไม่ได้เลือก) */
    public static function chosen(?UploadedFile $file): bool
    {
        return $file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE;
    }

    public static function assertUploaded(UploadedFile $file, int $maxKb): void
    {
        $mb = rtrim(rtrim(number_format($maxKb / 1024, 1), '0'), '.');

        match ($file->getError()) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => throw new UploadRejected("ไฟล์ใหญ่เกิน {$mb} MB กรุณาลดขนาดไฟล์ก่อนอัปโหลด"),
            UPLOAD_ERR_PARTIAL => throw new UploadRejected('อัปโหลดไฟล์ไม่ครบ (สัญญาณขาดหาย) กรุณาลองใหม่อีกครั้ง'),
            UPLOAD_ERR_NO_FILE => throw new UploadRejected('กรุณาเลือกไฟล์'),
            default            => throw new UploadRejected('เซิร์ฟเวอร์รับไฟล์ไม่ได้ กรุณาลองใหม่ หรือแจ้งผู้ดูแลระบบ'),
        };

        if (! $file->isValid() && ENVIRONMENT !== 'testing') {
            throw new UploadRejected('ไฟล์ที่อัปโหลดไม่ถูกต้อง กรุณาเลือกไฟล์ใหม่');
        }
    }
}
