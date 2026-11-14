<?php

namespace App\Controllers\Backend;

use App\Models\CategoryModel;
use App\Models\DownloadCategoryModel;

class DownloadCategories extends CategoryController
{
    protected string $path       = 'backend/download-categories';
    protected string $title      = 'หมวดหมู่ไฟล์';
    protected string $countLabel = 'จำนวนไฟล์';

    protected function model(): CategoryModel
    {
        return new DownloadCategoryModel();
    }
}
