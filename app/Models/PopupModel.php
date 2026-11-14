<?php

namespace App\Models;

class PopupModel extends DisplayWindowModel
{
    protected $table         = 'popups';
    protected $allowedFields = [
        'title', 'image_th', 'image_en', 'alt_th', 'alt_en', 'link_url', 'open_new_tab',
        'start_date', 'end_date', 'is_active', 'sort_order', 'created_by', 'updated_by',
    ];
}
