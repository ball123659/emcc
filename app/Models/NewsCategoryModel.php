<?php

namespace App\Models;

class NewsCategoryModel extends CategoryModel
{
    protected $table             = 'news_categories';
    protected string $itemsTable = 'news';
}
