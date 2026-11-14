<?php

namespace App\Controllers;

use App\Libraries\FileResponse;
use App\Libraries\Storage;
use App\Models\NewsAttachmentModel;
use App\Models\NewsModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * ดาวน์โหลดไฟล์แนบข่าว (ไฟล์เก็บนอก public)
 * - ต้องสแกนผ่าน (clean) เท่านั้น
 * - ข่าวต้องเผยแพร่แล้ว หรือผู้ใช้หลังบ้านที่มีสิทธิ์หน้า "ข่าว" (ดูตัวอย่างก่อนเผยแพร่)
 */
class NewsAttachments extends BaseController
{
    public function show(string $id)
    {
        if (preg_match('/^[1-9][0-9]{0,17}$/', $id) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }

        $attachment = (new NewsAttachmentModel())->find($id);

        if ($attachment === null || $attachment['scan_status'] !== 'clean' || ! Storage::exists($attachment['file_path'])) {
            throw PageNotFoundException::forPageNotFound();
        }

        $news       = (new NewsModel())->withDeleted()->find($attachment['news_id']);
        $auth       = service('auth');
        $user       = $auth->user();
        $canPreview = $user !== null && (bool) $user['is_active'] && empty($user['deleted_at']) && $auth->canViewPage('news');
        $public     = $news !== null && empty($news['deleted_at']) && $news['status'] === NewsModel::STATUS_PUBLISHED
            && ! empty($news['published_at']) && strtotime($news['published_at']) <= time();

        if ($news === null || (! $public && ! $canPreview)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return FileResponse::make($attachment['file_path'], $attachment['original_name'], $attachment['mime_type'], 'attachment-' . $attachment['id']);
    }
}
