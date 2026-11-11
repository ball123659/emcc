<?php

namespace App\Http\Controllers;

use App\Models\Download;
use App\Models\User;
use App\Services\Files\DocumentStorer;
use App\Services\Files\DownloadResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ดาวน์โหลดไฟล์ / เปิดลิงก์ภายนอก: /downloads/{id}
 * - ลิงก์คงที่ตาม id (อัปไฟล์ใหม่ทับ ลิงก์ไม่เสีย)
 * - ไฟล์ต้อง scan_status = clean และรายการต้องเปิดอยู่ (ผู้มีสิทธิ์หน้า downloads ดูตัวอย่างได้)
 * - นับยอดดาวน์โหลดครั้งเดียวต่อ session ต่อไฟล์
 */
class DownloadController extends Controller
{
    public function __invoke(Request $request, int $download): StreamedResponse|RedirectResponse
    {
        $item = Download::withTrashed()->find($download);
        $user = Auth::user();
        $canPreview = $user instanceof User && $user->is_active && $user->canViewPage('downloads');

        abort_unless($item && (($item->is_active && ! $item->trashed()) || $canPreview), 404);

        if ($item->isFile()) {
            abort_unless($item->scan_status === Download::SCAN_CLEAN && $item->file_path, 404);
            abort_unless(Storage::disk(DocumentStorer::DISK)->exists($item->file_path), 404);
        } else {
            abort_unless(preg_match('#^https?://#i', (string) $item->external_url) === 1, 404);
        }

        if (! $canPreview) {
            $this->countOnce($request, $item);
        }

        if (! $item->isFile()) {
            return redirect()->away($item->external_url);
        }

        return DownloadResponse::make(
            Storage::disk(DocumentStorer::DISK),
            $item->file_path,
            $item->downloadName(),
            $item->mime_type ?? 'application/octet-stream',
            'download-'.$item->id,
        );
    }

    private function countOnce(Request $request, Download $item): void
    {
        $key = 'downloaded.'.$item->id;

        if ($request->hasSession() && $request->session()->has($key)) {
            return;
        }

        Download::withoutTimestamps(fn () => Download::query()->whereKey($item->id)->increment('download_count'));

        if ($request->hasSession()) {
            $request->session()->put($key, true);
        }
    }
}
