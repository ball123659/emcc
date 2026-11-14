<?php

namespace App\Models;

/** ไฟล์แนบของข่าว (ดาวน์โหลดผ่าน /news/attachments/{id} เท่านั้น) */
class NewsAttachmentModel extends BaseModel
{
    protected $table         = 'news_attachments';
    protected bool $logsActivity = true;
    protected $allowedFields = ['news_id', 'title_th', 'title_en', 'file_path', 'original_name', 'mime_type', 'file_size', 'scan_status', 'sort_order'];
}
