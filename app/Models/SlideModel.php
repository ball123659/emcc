<?php

namespace App\Models;

class SlideModel extends DisplayWindowModel
{
    public const LINK_NONE = 'none';
    public const LINK_NEWS = 'news';
    public const LINK_URL  = 'url';

    public const LINK_TYPES = [
        self::LINK_NONE => 'ไม่มีลิงก์',
        self::LINK_NEWS => 'ข่าวในเว็บไซต์',
        self::LINK_URL  => 'URL ภายนอก',
    ];

    protected $table         = 'slides';
    protected $allowedFields = [
        'title', 'image_th', 'image_en', 'image_mobile', 'alt_th', 'alt_en', 'link_type', 'news_id', 'link_url',
        'open_new_tab', 'start_date', 'end_date', 'is_active', 'sort_order', 'created_by', 'updated_by',
    ];
}
