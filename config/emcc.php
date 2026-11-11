<?php

return [
    /*
    | รหัสผ่านเริ่มต้นของผู้ใช้ใหม่ / ตอนกดรีเซ็ตรหัสผ่าน
    | ผู้ใช้ต้องเปลี่ยนรหัสตอนล็อกอินครั้งแรก (must_change_password)
    */
    'default_password' => env('EMCC_DEFAULT_PASSWORD', 'passwordemcc'),

    // รหัสผ่านใหม่ต้องยาวอย่างน้อย
    'password_min_length' => 6,

    // โปรดักชัน: เปิดด้วย http ให้ redirect ไป https (ปิดได้ถ้า IIS/proxy redirect ให้แล้ว)
    'force_https' => (bool) env('FORCE_HTTPS', true),

    // เก็บบันทึกการใช้งานย้อนหลังกี่วัน (ลบอัตโนมัติด้วย scheduler)
    'activity_log_retention_days' => (int) env('ACTIVITY_LOG_RETENTION_DAYS', 730),

    // รูปแบบวันที่ที่ใช้ทั้งระบบ (ค.ศ.)
    'date_format' => 'd/m/Y',
    'datetime_format' => 'd/m/Y H:i',

    // ล็อกอินผิดครบกี่ครั้ง (ภายในเวลาที่ล็อก) จึงล็อก และล็อกกี่นาที
    'login_max_attempts' => 5,
    'login_lockout_minutes' => 15,

    /*
    | สแกนไวรัสไฟล์อัปโหลด: none (dev) | clamav (Linux) | defender (Windows)
    */
    'virus_scanner' => [
        'driver' => env('VIRUS_SCANNER', 'none'),
        'timeout' => (int) env('VIRUS_SCAN_TIMEOUT', 120),
        'clamav_binary' => env('CLAMAV_BINARY', 'clamdscan'),
        'defender_binary' => env('DEFENDER_BINARY', 'C:\\Program Files\\Windows Defender\\MpCmdRun.exe'),
    ],

    /*
    | รูปภาพ: ต้นฉบับไม่เกิน 10 MB, ด้านยาวเกินกำหนดย่ออัตโนมัติ, แปลงเป็น WebP
    */
    /*
    | ค่าขั้นต่ำใน php.ini ที่ระบบต้องการ (ตรวจด้วย php artisan emcc:check)
    | upload_max_filesize = 20M / post_max_size = 30M — รองรับไฟล์ดาวน์โหลดสูงสุด 20 MB
    */
    'php_upload_max_mb' => 20,
    'php_post_max_mb' => 30,

    /*
    | ไฟล์เอกสาร/สื่อ (ไฟล์แนบข่าว, ไฟล์ดาวน์โหลด) — ต้องไม่เกิน upload_max_filesize ของ PHP
    */
    'files' => [
        'max_upload_kb' => 20480,
    ],

    'images' => [
        'max_upload_kb' => 10240,
        'max_dimension' => 1920,
        'max_source_pixels' => 50_000_000, // กันไฟล์รูปขนาดมหึมา (decompression bomb)
        'webp_quality' => 85,
        'thumb_dimension' => 480,
        'accepted_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
    ],
];
