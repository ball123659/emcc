<?php

namespace App\Models;

use App\Libraries\Slug;

abstract class CategoryModel extends BaseModel
{
    protected bool $logsActivity = true;
    protected $allowedFields     = ['slug', 'name_th', 'name_en', 'sort_order', 'is_active'];

    protected string $itemsTable;

    public function __construct(...$args)
    {
        parent::__construct(...$args);
        array_unshift($this->beforeInsert, 'prepare');
        array_unshift($this->beforeUpdate, 'prepare');
    }

    protected function prepare(array $data): array
    {
        $row = &$data['data'];
        $id  = isset($data['id']) ? (array) $data['id'] : [];

        if (! isset($row['sort_order']) && $id === []) {
            $max               = $this->db->table($this->table)->selectMax('sort_order')->get()->getRowArray();
            $row['sort_order'] = (int) ($max['sort_order'] ?? 0) + 1;
        }

        if (array_key_exists('name_th', $row) || array_key_exists('name_en', $row) || array_key_exists('slug', $row)) {
            $current = $id !== [] ? ($this->db->table($this->table)->where('id', $id[0])->get()->getRowArray() ?? []) : [];
            $source  = $row['slug'] ?? null;
            $source  = $source ?: ($row['name_en'] ?? $current['name_en'] ?? null) ?: ($row['name_th'] ?? $current['name_th'] ?? '');

            $base = Slug::make($source) ?: Slug::random();
            $base = Slug::limit($base, 90);
            $slug = $base;
            $i    = 2;

            while ($this->db->table($this->table)->where('slug', $slug)->when($id !== [], static fn ($q) => $q->whereNotIn('id', $id))->countAllResults() > 0) {
                $slug = $base . '-' . $i++;
            }

            $row['slug'] = $slug;
        }

        return $data;
    }

    public function itemCount(int $id): int
    {
        return $this->db->table($this->itemsTable)->where('category_id', $id)->countAllResults();
    }

    public function options(?int $includeId = null): array
    {
        $rows = $this->groupStart()->where('is_active', 1)->when($includeId, static fn ($q) => $q->orWhere('id', $includeId))->groupEnd()
            ->orderBy('sort_order')->orderBy('id')->findAll();

        return array_column($rows, 'name_th', 'id');
    }
}
