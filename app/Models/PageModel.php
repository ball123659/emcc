<?php

namespace App\Models;

use CodeIgniter\Model;

class PageModel extends Model
{
    protected $table         = 'pages';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['code', 'parent_id', 'name_th', 'name_en', 'icon', 'sort_order', 'is_active', 'admin_only'];
    protected $afterInsert   = ['createPermissionRows'];

    protected function createPermissionRows(array $data): array
    {
        if ($data['result'] ?? false) {
            UserPagePermissionModel::createDefaultsForPage((int) $data['id']);
        }

        return $data;
    }

    public function ordered(): static
    {
        return $this->orderBy('sort_order')->orderBy('id');
    }
}
