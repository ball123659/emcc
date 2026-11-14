<?php

namespace App\Controllers\Backend;

use App\Models\CategoryModel;
use App\Models\NewsCategoryModel;

class NewsCategories extends CategoryController
{
    protected string $path       = 'backend/news-categories';
    protected string $title      = 'หมวดหมู่ข่าว';
    protected string $countLabel = 'จำนวนข่าว';

    protected function model(): CategoryModel
    {
        return new NewsCategoryModel();
    }
}
