<?php

use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// รูปภาพหน้าเว็บ (เก็บนอก public) — ชื่อไฟล์สุ่มเป็น UUID + .webp เท่านั้น
Route::get('/media/{path}', [MediaController::class, 'show'])
    ->where('path', '(slides|popups|news|settings)/[0-9a-f\-]{36}(_thumb)?\.webp')
    ->name('media.show');
