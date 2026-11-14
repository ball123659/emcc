<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * ค่าตั้งค่าของระบบ EMCC — แก้ผ่าน .env ได้ เช่น emcc.virusScanner = defender
 */
class Emcc extends BaseConfig
{
    /** ชื่อระบบหลังบ้าน */
    public string $brandName = 'EMCC Backend';

    /**
     * รหัสผ่านเริ่มต้นของผู้ใช้ใหม่ / ตอนกดรีเซ็ตรหัสผ่าน
     * ผู้ใช้ต้องเปลี่ยนรหัสตอนล็อกอินครั้งแรก (must_change_password)
     */
    public string $defaultPassword = 'passwordemcc';

    /** รหัสผ่านใหม่ต้องยาวอย่างน้อย */
    public int $passwordMinLength = 6;

    /** ล็อกอินผิดครบกี่ครั้ง (ภายในเวลาที่ล็อก) จึงล็อก และล็อกกี่นาที */
    public int $loginMaxAttempts = 5;

    public int $loginLockoutMinutes = 15;

    /** "จดจำฉัน" อยู่ได้กี่วัน */
    public int $rememberDays = 30;

    /** โปรดักชัน: เปิดด้วย http ให้ redirect ไป https (ปิดได้ถ้า IIS/proxy redirect ให้แล้ว) */
    public bool $forceHttps = true;

    /** เก็บบันทึกการใช้งานย้อนหลังกี่วัน (ลบด้วย php spark emcc:daily) */
    public int $activityLogRetentionDays = 730;

    /** รูปแบบวันที่ที่ใช้ทั้งระบบ (ค.ศ.) */
    public string $dateFormat = 'd/m/Y';

    public string $datetimeFormat = 'd/m/Y H:i';

    /** สแกนไวรัสไฟล์อัปโหลด: none (dev) | clamav (Linux) | defender (Windows) */
    public string $virusScanner = 'none';

    public int $virusScanTimeout = 120;

    public string $clamavBinary = 'clamdscan';

    public string $defenderBinary = 'C:\\Program Files\\Windows Defender\\MpCmdRun.exe';

    /** โฟลเดอร์เก็บไฟล์อัปโหลด (นอก public) */
    public string $storagePath = WRITEPATH . 'storage/';

    /** ไฟล์เอกสาร/สื่อ (ไฟล์แนบข่าว, ไฟล์ดาวน์โหลด) — ต้องไม่เกิน upload_max_filesize ของ PHP */
    public int $fileMaxKb = 20480;

    /** รูปภาพ: ต้นฉบับไม่เกิน 10 MB, ด้านยาวเกินกำหนดย่ออัตโนมัติ, แปลงเป็น WebP */
    public int $imageMaxKb = 10240;

    public int $imageMaxDimension = 1920;

    public int $imageMaxSourcePixels = 50_000_000;

    public int $webpQuality = 85;

    public int $thumbDimension = 480;

    /** ค่าขั้นต่ำใน php.ini (ตรวจด้วย php spark emcc:check) */
    public int $phpUploadMaxMb = 20;

    public int $phpPostMaxMb = 30;
}
