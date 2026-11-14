<?php

namespace App\Controllers;

use App\Libraries\FileResponse;
use App\Libraries\Storage;
use App\Models\NewsAttachmentModel;
use App\Models\NewsModel;
use CodeIgniter\Exceptions\PageNotFoundException;

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
        $canPreview = $this->canPreview('news');
        $public     = $news !== null && empty($news['deleted_at']) && $news['status'] === NewsModel::STATUS_PUBLISHED
            && ! empty($news['published_at']) && strtotime($news['published_at']) <= time();

        if ($news === null || (! $public && ! $canPreview)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return FileResponse::make($attachment['file_path'], $attachment['original_name'], $attachment['mime_type'], 'attachment-' . $attachment['id']);
    }
}
