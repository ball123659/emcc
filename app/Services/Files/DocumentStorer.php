<?php

namespace App\Services\Files;

use App\Services\VirusScan\ScanResult;
use App\Services\VirusScan\VirusScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SplFileInfo;
use ZipArchive;

/**
 * รับไฟล์เอกสาร/สื่อ (ไฟล์แนบข่าว, ไฟล์ดาวน์โหลด)
 * whitelist นามสกุล + ตรวจ MIME จากเนื้อไฟล์ + ปฏิเสธไฟล์ Office ที่ฝัง macro + สแกนไวรัส
 * ตั้งชื่อสุ่ม เก็บนอก public (disk local) — ดาวน์โหลดผ่าน controller เท่านั้น
 */
class DocumentStorer
{
    public const DISK = 'local';

    /** นามสกุล => MIME ที่ยอมรับ (ตรวจจากเนื้อไฟล์ด้วย finfo) */
    public const ALLOWED = [
        'pdf' => ['application/pdf'],
        // .doc / .xls ที่ระบบอื่น export มักเป็น HTML / XML / RTF / ข้อความ (Word/Excel เปิดได้)
        // ปลอดภัยเพราะดาวน์โหลดแบบ attachment + nosniff เสมอ เบราว์เซอร์ไม่เรนเดอร์เป็นหน้าเว็บ
        'doc' => ['application/msword', 'application/CDFV2', 'application/x-ole-storage', 'text/html', 'text/rtf', 'application/rtf', 'text/xml', 'application/xml'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-ole-storage', 'text/html', 'text/xml', 'application/xml', 'text/plain', 'text/csv'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/CDFV2', 'application/x-ole-storage'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'txt' => ['text/plain'],
        'jpg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'mp4' => ['video/mp4'],
        'mp3' => ['audio/mpeg', 'audio/mp3'],
    ];

    /** MIME ที่ส่งให้เบราว์เซอร์ตอนดาวน์โหลด */
    public const RESPONSE_MIME = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'csv' => 'text/csv',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'mp4' => 'video/mp4',
        'mp3' => 'audio/mpeg',
    ];

    /** ผลสแกนของไฟล์ที่เพิ่งบันทึกใน request นี้ (ให้ model เติม scan_status) */
    private static array $scanResults = [];

    public function __construct(private readonly VirusScanner $scanner) {}

    public static function scanResultFor(string $path): ?string
    {
        return self::$scanResults[$path] ?? null;
    }

    /** MIME ทั้งหมดสำหรับ acceptedFileTypes ฝั่งเบราว์เซอร์ */
    public static function browserMimeTypes(): array
    {
        // เบราว์เซอร์บอกชนิดไฟล์จากนามสกุล (Windows บางเครื่องบอก .csv เป็น application/vnd.ms-excel)
        return array_values(array_unique([...array_values(self::RESPONSE_MIME), 'application/vnd.ms-excel', 'text/plain', 'application/csv', 'text/x-csv', 'audio/mp3']));
    }

    public static function extensionsLabel(): string
    {
        return strtoupper(implode(', ', array_keys(self::ALLOWED)));
    }

    public function store(SplFileInfo $file, string $directory): StoredFile
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_file($path)) {
            throw new FileRejectedException('อ่านไฟล์ที่อัปโหลดไม่ได้ กรุณาอัปโหลดใหม่อีกครั้ง');
        }

        $originalName = $file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename();
        $originalName = Str::limit(trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', $originalName)), 200, '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (! array_key_exists($extension, self::ALLOWED)) {
            throw new FileRejectedException('ไม่รองรับไฟล์ชนิดนี้ รองรับเฉพาะ '.self::extensionsLabel().' (ไม่รับไฟล์ที่มี macro เช่น .docm .xlsm)');
        }

        $maxKb = (int) config('emcc.files.max_upload_kb');

        if (filesize($path) > $maxKb * 1024) {
            throw new FileRejectedException('ไฟล์ใหญ่เกิน '.($maxKb / 1024).' MB กรุณาลดขนาดไฟล์ หรือใช้ลิงก์ภายนอกแทน');
        }

        if (filesize($path) === 0) {
            throw new FileRejectedException('ไฟล์ว่างเปล่า กรุณาเลือกไฟล์ใหม่');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';

        if (! in_array($mime, self::ALLOWED[$extension], true)) {
            throw new FileRejectedException("เนื้อไฟล์ไม่ตรงกับนามสกุล .{$extension} (ตรวจพบเป็น {$mime} — ไฟล์อาจถูกเปลี่ยนนามสกุล) กรุณาเปิดไฟล์ในโปรแกรมต้นฉบับแล้ว Save As เป็น .{$extension} ใหม่");
        }

        if (in_array($extension, ['docx', 'xlsx', 'pptx'], true)) {
            $this->assertOfficeWithoutMacro($path);
        }

        $scan = $this->scanner->scan($path);

        if ($scan === ScanResult::Infected) {
            throw new FileRejectedException('ตรวจพบไวรัสหรือมัลแวร์ในไฟล์ ระบบไม่รับไฟล์นี้');
        }

        if ($scan !== ScanResult::Clean) {
            throw new FileRejectedException('สแกนไวรัสไม่สำเร็จ กรุณาลองใหม่อีกครั้ง หรือแจ้งผู้ดูแลระบบ');
        }

        $storedPath = trim($directory, '/').'/'.Str::uuid()->toString().'.'.$extension;
        $stream = fopen($path, 'rb');
        Storage::disk(self::DISK)->writeStream($storedPath, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        self::$scanResults[$storedPath] = $scan->value;

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
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new FileRejectedException('ไฟล์ Office เสียหรือไม่ใช่ไฟล์ Office จริง กรุณาบันทึกไฟล์ใหม่');
        }

        try {
            if ($zip->locateName('[Content_Types].xml') === false) {
                throw new FileRejectedException('ไฟล์ Office เสียหรือไม่ใช่ไฟล์ Office จริง กรุณาบันทึกไฟล์ใหม่');
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (str_ends_with(strtolower((string) $zip->getNameIndex($i)), 'vbaproject.bin')) {
                    throw new FileRejectedException('ไฟล์นี้มี macro ระบบไม่รับเพื่อความปลอดภัย กรุณาบันทึกเป็นไฟล์ปกติ (.docx .xlsx .pptx) ที่ไม่มี macro');
                }
            }
        } finally {
            $zip->close();
        }
    }

    public static function delete(?string ...$paths): void
    {
        $paths = array_values(array_filter($paths));

        if ($paths !== []) {
            Storage::disk(self::DISK)->delete($paths);
        }
    }
}
