<?php

namespace App\Libraries\Uploads;

use App\Libraries\Storage;
use App\Libraries\VirusScan\NullScanner;
use App\Libraries\VirusScan\ScanResult;
use App\Libraries\VirusScan\VirusScanner;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Emcc;
use ZipArchive;

/**
 * รับไฟล์เอกสาร/สื่อ (ไฟล์แนบข่าว, ไฟล์ดาวน์โหลด)
 * whitelist นามสกุล + ตรวจ MIME จากเนื้อไฟล์ + ปฏิเสธไฟล์ Office ที่ฝัง macro + สแกนไวรัส
 * ตั้งชื่อสุ่ม เก็บนอก public — ดาวน์โหลดผ่าน controller เท่านั้น
 */
class DocumentStorer
{
    /** นามสกุล => MIME ที่ยอมรับ (ตรวจจากเนื้อไฟล์ด้วย finfo) */
    public const ALLOWED = [
        'pdf' => ['application/pdf'],
        // .doc / .xls ที่ระบบอื่น export มักเป็น HTML / XML / RTF / ข้อความ (Word/Excel เปิดได้)
        // ปลอดภัยเพราะดาวน์โหลดแบบ attachment + nosniff เสมอ เบราว์เซอร์ไม่เรนเดอร์เป็นหน้าเว็บ
        'doc'  => ['application/msword', 'application/CDFV2', 'application/x-ole-storage', 'text/html', 'text/rtf', 'application/rtf', 'text/xml', 'application/xml'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls'  => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-ole-storage', 'text/html', 'text/xml', 'application/xml', 'text/plain', 'text/csv'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'ppt'  => ['application/vnd.ms-powerpoint', 'application/CDFV2', 'application/x-ole-storage'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'csv'  => ['text/csv', 'text/plain', 'application/csv'],
        'txt'  => ['text/plain'],
        'jpg'  => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'mp4'  => ['video/mp4'],
        'mp3'  => ['audio/mpeg', 'audio/mp3'],
    ];

    /** MIME ที่ส่งให้เบราว์เซอร์ตอนดาวน์โหลด */
    public const RESPONSE_MIME = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'csv'  => 'text/csv',
        'txt'  => 'text/plain',
        'jpg'  => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'mp4'  => 'video/mp4',
        'mp3'  => 'audio/mpeg',
    ];

    public function __construct(private readonly VirusScanner $scanner)
    {
    }

    public static function extensionsLabel(): string
    {
        return strtoupper(implode(', ', array_keys(self::ALLOWED)));
    }

    /** ค่า accept ของ input file ฝั่งเบราว์เซอร์ */
    public static function acceptAttribute(): string
    {
        return implode(',', array_map(static fn ($ext) => '.' . $ext, array_keys(self::ALLOWED)));
    }

    public function storeUpload(UploadedFile $file, string $directory): StoredFile
    {
        UploadCheck::assertUploaded($file, config(Emcc::class)->fileMaxKb);

        return $this->store($file->getTempName(), $file->getClientName(), $directory);
    }

    public function store(string $path, string $originalName, string $directory): StoredFile
    {
        @set_time_limit(120); // สแกนไวรัส + คัดลอกไฟล์ใหญ่ อาจเกิน max_execution_time ปกติ

        if (! is_file($path)) {
            throw new UploadRejected('อ่านไฟล์ที่อัปโหลดไม่ได้ กรุณาอัปโหลดใหม่อีกครั้ง');
        }

        $originalName = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', $originalName));
        $extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // ชื่อยาวเกิน: ตัดเฉพาะชื่อ คงนามสกุลไว้ (คอลัมน์ original_name ยาวได้ 255)
        if (mb_strlen($originalName) > 200) {
            $originalName = mb_substr(pathinfo($originalName, PATHINFO_FILENAME), 0, 190) . ($extension !== '' ? '.' . $extension : '');
        }

        if (! array_key_exists($extension, self::ALLOWED)) {
            throw new UploadRejected('ไม่รองรับไฟล์ชนิดนี้ รองรับเฉพาะ ' . self::extensionsLabel() . ' (ไม่รับไฟล์ที่มี macro เช่น .docm .xlsm)');
        }

        $maxKb = config(Emcc::class)->fileMaxKb;

        if (filesize($path) > $maxKb * 1024) {
            throw new UploadRejected('ไฟล์ใหญ่เกิน ' . ($maxKb / 1024) . ' MB กรุณาลดขนาดไฟล์ หรือใช้ลิงก์ภายนอกแทน');
        }

        if (filesize($path) === 0) {
            throw new UploadRejected('ไฟล์ว่างเปล่า กรุณาเลือกไฟล์ใหม่');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';

        if (! in_array($mime, self::ALLOWED[$extension], true)) {
            throw new UploadRejected("เนื้อไฟล์ไม่ตรงกับนามสกุล .{$extension} (ตรวจพบเป็น {$mime} — ไฟล์อาจถูกเปลี่ยนนามสกุล) กรุณาเปิดไฟล์ในโปรแกรมต้นฉบับแล้ว Save As เป็น .{$extension} ใหม่");
        }

        if (in_array($extension, ['docx', 'xlsx', 'pptx'], true)) {
            $this->assertOfficeWithoutMacro($path);
        }

        if ($this->scanner instanceof NullScanner && NullScanner::blocksUploads()) {
            throw new UploadRejected('ระบบยังไม่ได้ตั้งค่าโปรแกรมสแกนไวรัส จึงยังรับไฟล์ไม่ได้ กรุณาแจ้งผู้ดูแลระบบ (ตั้ง emcc.virusScanner ใน .env)');
        }

        $scan = $this->scanner->scan($path);

        if ($scan === ScanResult::Infected) {
            throw new UploadRejected('ตรวจพบไวรัสหรือมัลแวร์ในไฟล์ ระบบไม่รับไฟล์นี้');
        }

        if ($scan !== ScanResult::Clean) {
            throw new UploadRejected('สแกนไวรัสไม่สำเร็จ กรุณาลองใหม่อีกครั้ง หรือแจ้งผู้ดูแลระบบ');
        }

        $storedPath = trim($directory, '/') . '/' . Storage::uuid() . '.' . $extension;
        Storage::copyFrom($path, $storedPath);

        return new StoredFile(
            path: $storedPath,
            originalName: $originalName,
            extension: $extension,
            mimeType: self::RESPONSE_MIME[$extension],
            size: (int) filesize($path),
            hash: hash_file('sha256', $path),
            scanStatus: $scan,
        );
    }

    /** ไฟล์ Office Open XML ต้องเปิดเป็น zip ได้ และต้องไม่มี vbaProject.bin (macro) */
    private function assertOfficeWithoutMacro(string $path): void
    {
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new UploadRejected('ไฟล์ Office เสียหรือไม่ใช่ไฟล์ Office จริง กรุณาบันทึกไฟล์ใหม่');
        }

        try {
            if ($zip->locateName('[Content_Types].xml') === false) {
                throw new UploadRejected('ไฟล์ Office เสียหรือไม่ใช่ไฟล์ Office จริง กรุณาบันทึกไฟล์ใหม่');
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (str_ends_with(strtolower((string) $zip->getNameIndex($i)), 'vbaproject.bin')) {
                    throw new UploadRejected('ไฟล์นี้มี macro ระบบไม่รับเพื่อความปลอดภัย กรุณาบันทึกเป็นไฟล์ปกติ (.docx .xlsx .pptx) ที่ไม่มี macro');
                }
            }
        } finally {
            $zip->close();
        }
    }
}
