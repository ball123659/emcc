<?php

return [
    /*
    | รหัสผ่านเริ่มต้นของผู้ใช้ใหม่ / ตอนกดรีเซ็ตรหัสผ่าน
    | ผู้ใช้ต้องเปลี่ยนรหัสตอนล็อกอินครั้งแรก (must_change_password)
    */
    'default_password' => env('EMCC_DEFAULT_PASSWORD', 'passwordemcc'),

    // รหัสผ่านใหม่ต้องยาวอย่างน้อย
    'password_min_length' => 6,

    // ล็อกอินผิดครบกี่ครั้งจึงล็อก และล็อกกี่นาที
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
    'images' => [
        'max_upload_kb' => 10240,
        'max_dimension' => 1920,
        'max_source_pixels' => 50_000_000, // กันไฟล์รูปขนาดมหึมา (decompression bomb)
        'webp_quality' => 85,
        'thumb_dimension' => 480,
        'accepted_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
    ],
];
