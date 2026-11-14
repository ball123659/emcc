<?php

namespace App\Models;

class DownloadCategoryModel extends CategoryModel
{
    protected $table             = 'download_categories';
    protected string $itemsTable = 'downloads';
}
