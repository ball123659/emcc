<?php

use App\Http\Controllers\MediaController;
use App\Http\Controllers\NewsAttachmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// รูปภาพหน้าเว็บ (เก็บนอก public) — ชื่อไฟล์สุ่มเป็น UUID + .webp เท่านั้น
Route::get('/media/{path}', [MediaController::class, 'show'])
    ->where('path', '(slides|popups|news|settings)/[0-9a-f\-]{36}(_thumb)?\.webp')
    ->name('media.show');

// ไฟล์แนบข่าว (ดาวน์โหลดผ่าน controller เท่านั้น)
Route::get('/news/attachments/{attachment}', NewsAttachmentController::class)
    ->whereNumber('attachment')
    ->name('news.attachment');
