<?php

namespace App\Http\Controllers;

use App\Services\Images\ImageProcessor;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * ส่งรูปที่ผ่านการประมวลผลแล้ว (WebP ชื่อสุ่ม) จาก storage นอก public
 * path ถูกจำกัดด้วย regex ใน routes/web.php จึงอ่านไฟล์อื่นนอกโฟลเดอร์รูปไม่ได้
 */
class MediaController extends Controller
{
    public function show(string $path): BinaryFileResponse
    {
        $disk = Storage::disk(ImageProcessor::DISK);

        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
