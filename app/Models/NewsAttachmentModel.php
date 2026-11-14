<?php

namespace App\Models;

class NewsAttachmentModel extends BaseModel
{
    protected $table         = 'news_attachments';
    protected bool $logsActivity = true;
    protected $allowedFields = ['news_id', 'title_th', 'title_en', 'file_path', 'original_name', 'mime_type', 'file_size', 'scan_status', 'sort_order'];
}
