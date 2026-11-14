<?php

namespace App\Controllers;

use App\Libraries\FileResponse;
use App\Libraries\Storage;
use App\Models\DownloadModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * ดาวน์โหลดไฟล์ / เปิดลิงก์ภายนอก: /downloads/{id}
 * - ลิงก์คงที่ตาม id (อัปไฟล์ใหม่ทับ ลิงก์ไม่เสีย)
 * - ไฟล์ต้อง scan_status = clean และรายการต้องเปิดอยู่ (ผู้มีสิทธิ์หน้า downloads ดูตัวอย่างได้)
 * - นับยอดดาวน์โหลดแบบกันซ้ำ
 */
class Downloads extends BaseController
{
    public function show(string $id)
    {
        // รับเฉพาะเลขที่อยู่ในช่วงของ BIGINT (กันตัวเลขยาวเกินทำให้ error)
        if (preg_match('/^[1-9][0-9]{0,17}$/', $id) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }

        // จำกัดความถี่ต่อ IP (กันบอทยิงรัว)
        if (! service('throttler')->check('downloads-' . md5($this->request->getIPAddress()), 60, MINUTE)) {
            return $this->response->setStatusCode(429)->setBody('ขอดาวน์โหลดถี่เกินไป กรุณารอสักครู่แล้วลองใหม่');
        }

        $item       = (new DownloadModel())->withDeleted()->find($id);
        $canPreview = $this->canPreview('downloads');

        if ($item === null || (! ((bool) $item['is_active'] && empty($item['deleted_at'])) && ! $canPreview)) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ($item['type'] === DownloadModel::TYPE_FILE) {
            if ($item['scan_status'] !== DownloadModel::SCAN_CLEAN || ! Storage::exists($item['file_path'])) {
                throw PageNotFoundException::forPageNotFound();
            }
        } elseif (preg_match('#^https?://#i', (string) $item['external_url']) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! $canPreview) {
            $this->countOnce($item);
        }

        if ($item['type'] === DownloadModel::TYPE_LINK) {
            return redirect()->to($item['external_url']);
        }

        return FileResponse::make($item['file_path'], DownloadModel::downloadName($item), $item['mime_type'] ?? 'application/octet-stream', 'download-' . $item['id']);
    }

    /**
     * นับยอดดาวน์โหลดแบบกันซ้ำ: ไม่นับคำขอ HEAD และบอท, นับ 1 ครั้งต่อ session
     * และ 1 ครั้งต่อ IP ต่อไฟล์ต่อชั่วโมง (กันคนหรือบอทที่ไม่เก็บ cookie)
     */
    private function countOnce(array $item): void
    {
        if (strtolower($this->request->getMethod()) !== 'get' || self::isBot((string) $this->request->getUserAgent())) {
            return;
        }

        // ผู้ที่มี session อยู่แล้ว: นับครั้งเดียวต่อ session (ไม่เริ่ม session ใหม่ให้ผู้เยี่ยมชมทั่วไป)
        if ($this->hasSessionCookie()) {
            $sessionKey = 'downloaded_' . $item['id'];

            if (session()->has($sessionKey)) {
                return;
            }

            session()->set($sessionKey, true);
        }

        $cacheKey = 'download_count_' . $item['id'] . '_' . sha1($this->request->getIPAddress());

        if (cache($cacheKey) !== null) {
            return;
        }

        cache()->save($cacheKey, 1, HOUR);
        db_connect()->table('downloads')->where('id', $item['id'])->set('download_count', 'download_count + 1', false)->update();
    }

    public static function isBot(string $userAgent): bool
    {
        return $userAgent === ''
            || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|curl|wget|python-requests|httpclient|headless/i', $userAgent) === 1;
    }
}
