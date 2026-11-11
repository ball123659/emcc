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
];
