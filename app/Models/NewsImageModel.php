<?php

namespace App\Models;

/** แกลเลอรีรูปของข่าว */
class NewsImageModel extends BaseModel
{
    protected $table         = 'news_images';
    protected bool $logsActivity = true;
    protected $allowedFields = ['news_id', 'image_path', 'thumb_path', 'caption_th', 'caption_en', 'width', 'height', 'file_size', 'sort_order'];
}
