<?php

namespace App\Http\Controllers;

use App\Models\Download;
use App\Models\User;
use App\Services\Files\DocumentStorer;
use App\Services\Files\DownloadResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
    public function __invoke(Request $request, string $download): StreamedResponse|RedirectResponse
    {
        // รับเฉพาะเลขที่อยู่ในช่วงของ BIGINT (กันตัวเลขยาวเกินทำให้ error 500)
        abort_unless(preg_match('/^[1-9][0-9]{0,17}$/', $download) === 1, 404);

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

    /**
     * นับยอดดาวน์โหลดแบบกันซ้ำ: ไม่นับคำขอ HEAD และบอท, นับ 1 ครั้งต่อ session
     * และ 1 ครั้งต่อ IP ต่อไฟล์ต่อชั่วโมง (กันคนหรือบอทที่ไม่เก็บ cookie)
     */
    private function countOnce(Request $request, Download $item): void
    {
        if (! $request->isMethod('GET') || self::isBot((string) $request->userAgent())) {
            return;
        }

        $sessionKey = 'downloaded.'.$item->id;

        if ($request->hasSession() && $request->session()->has($sessionKey)) {
            return;
        }

        if ($request->hasSession()) {
            $request->session()->put($sessionKey, true);
        }

        // Cache::add คืน false ถ้ามีคีย์อยู่แล้ว (IP นี้นับไฟล์นี้ไปแล้วในชั่วโมงนี้)
        if (! Cache::add('download-count:'.$item->id.':'.sha1((string) $request->ip()), true, now()->addHour())) {
            return;
        }

        Download::withoutTimestamps(fn () => Download::query()->whereKey($item->id)->increment('download_count'));
    }

    public static function isBot(string $userAgent): bool
    {
        return $userAgent === ''
            || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|curl|wget|python-requests|httpclient|headless/i', $userAgent) === 1;
    }
}
