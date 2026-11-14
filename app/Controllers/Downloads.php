<?php

namespace App\Controllers;

use App\Libraries\FileResponse;
use App\Libraries\Storage;
use App\Models\DownloadModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Downloads extends BaseController
{
    public function show(string $id)
    {

        if (preg_match('/^[1-9][0-9]{0,17}$/', $id) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }

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

    private function countOnce(array $item): void
    {
        if (strtolower($this->request->getMethod()) !== 'get' || self::isBot((string) $this->request->getUserAgent())) {
            return;
        }

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
