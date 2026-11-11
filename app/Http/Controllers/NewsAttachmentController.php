<?php

namespace App\Http\Controllers;

use App\Models\Download;
use App\Models\NewsAttachment;
use App\Models\User;
use App\Services\Files\DocumentStorer;
use App\Services\Files\DownloadResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ดาวน์โหลดไฟล์แนบข่าว (ไฟล์เก็บนอก public)
 * - ต้องสแกนผ่าน (clean) เท่านั้น
 * - ข่าวต้องเผยแพร่แล้ว หรือผู้ใช้หลังบ้านที่มีสิทธิ์หน้า "ข่าว" (ดูตัวอย่างก่อนเผยแพร่)
 */
class NewsAttachmentController extends Controller
{
    public function __invoke(NewsAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->scan_status === Download::SCAN_CLEAN, 404);

        $news = $attachment->news()->withTrashed()->first();
        $user = Auth::user();
        $canPreview = $user instanceof User && $user->is_active && $user->canViewPage('news');

        abort_unless($news && ($news->isPubliclyVisible() || $canPreview), 404);

        $disk = Storage::disk(DocumentStorer::DISK);
        abort_unless($disk->exists($attachment->file_path), 404);

        return DownloadResponse::make($disk, $attachment->file_path, $attachment->original_name, $attachment->mime_type, 'attachment-'.$attachment->id);
    }
}
