<?php

use App\Http\Controllers\DownloadController;
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
    ->where('attachment', '[1-9][0-9]{0,17}')
    ->name('news.attachment');

// ไฟล์ดาวน์โหลด / ลิงก์ภายนอก (ลิงก์คงที่ตาม id)
Route::get('/downloads/{download}', DownloadController::class)
    ->where('download', '[0-9]+')
    ->middleware('throttle:60,1')
    ->name('downloads.show');
