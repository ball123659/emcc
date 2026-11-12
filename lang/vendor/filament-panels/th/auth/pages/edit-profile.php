<?php

// เติมข้อความภาษาไทยที่ Filament ยังไม่มี (ทับเฉพาะคีย์ที่ระบุ)
return array (
  'form' => 
  array (
    'password' => 
    array (
      'validation_attribute' => 'รหัสผ่าน',
    ),
    'password_confirmation' => 
    array (
      'validation_attribute' => 'ยืนยันรหัสผ่าน',
    ),
    'current_password' => 
    array (
      'label' => 'รหัสผ่านปัจจุบัน',
      'below_content' => 'เพื่อความปลอดภัย กรุณายืนยันรหัสผ่านปัจจุบัน',
      'validation_attribute' => 'รหัสผ่านปัจจุบัน',
    ),
  ),
  'notifications' => 
  array (
    'throttled' => 
    array (
      'title' => 'ทำรายการถี่เกินไป กรุณาลองใหม่ใน :seconds วินาที',
      'body' => 'กรุณาลองใหม่ใน :seconds วินาที',
    ),
  ),
);
