<?php

namespace App\Models;

use App\Libraries\ActivityLogger;
use CodeIgniter\Model;

abstract class BaseModel extends Model
{
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $dateFormat     = 'datetime';
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';
    protected bool $tracksUser   = false;
    protected bool $logsActivity = false;

    protected array $activityIgnored = [
        'id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by',
        'failed_login_attempts', 'locked_until', 'last_login_at', 'last_login_ip',
        'password_changed_at', 'view_count', 'download_count', 'remember_token',
    ];

    protected $beforeInsert = ['stampCreator'];
    protected $afterInsert  = ['logCreate'];
    protected $beforeUpdate = ['stampUpdater', 'rememberOriginals'];
    protected $afterUpdate  = ['logUpdate'];
    protected $beforeDelete = ['rememberDeleted'];
    protected $afterDelete  = ['logDelete'];

    private array $originals = [];

    private array $deleting = [];

    private bool $muted = false;

    public function quietly(callable $callback): mixed
    {
        $this->muted = true;

        try {
            return $callback($this);
        } finally {
            $this->muted = false;
        }
    }

    public function updateQuietly(int|string $id, array $data): bool
    {
        return $this->db->table($this->table)->where($this->primaryKey, $id)->update($data);
    }

    public function restore(int|string $id): bool
    {
        $row = $this->onlyDeleted()->find($id);

        if ($row === null) {
            return false;
        }

        $data = [$this->deletedField => null];

        if ($this->tracksUser) {
            $data['updated_by'] = service('auth')->id();
        }

        $this->db->table($this->table)->where($this->primaryKey, $id)->update($data);

        if ($this->logsActivity) {
            ActivityLogger::log('restore', $this->table, $id, $this->activityLabel($row));
        }

        return true;
    }

    public function activityLabel(array $row): ?string
    {
        foreach (['username', 'title_th', 'title', 'name_th', 'label_th', 'key', 'caption_th', 'original_name'] as $field) {
            if (isset($row[$field]) && trim((string) $row[$field]) !== '') {
                return (string) $row[$field];
            }
        }

        return null;
    }

    protected function stampCreator(array $data): array
    {
        if ($this->tracksUser && ($userId = service('auth')->id()) !== null) {
            $data['data']['created_by'] ??= $userId;
            $data['data']['updated_by'] ??= $userId;
        }

        return $data;
    }

    protected function stampUpdater(array $data): array
    {
        if ($this->tracksUser && ($userId = service('auth')->id()) !== null) {
            $data['data']['updated_by'] = $userId;
        }

        return $data;
    }

    protected function rememberOriginals(array $data): array
    {
        $this->originals = [];

        if ($this->logsActivity && ! $this->muted && ! empty($data['id'])) {
            $rows = $this->db->table($this->table)->whereIn($this->primaryKey, (array) $data['id'])->get()->getResultArray();

            foreach ($rows as $row) {
                $this->originals[$row[$this->primaryKey]] = $row;
            }
        }

        return $data;
    }

    protected function logCreate(array $data): array
    {
        if ($this->logsActivity && ! $this->muted && ($data['result'] ?? false)) {
            $row = $this->db->table($this->table)->where($this->primaryKey, $data['id'])->get()->getRowArray() ?? $data['data'];
            ActivityLogger::log('create', $this->table, $data['id'], $this->activityLabel($row), ['new' => $this->activityFilter($row)]);
        }

        return $data;
    }

    protected function logUpdate(array $data): array
    {
        if (! $this->logsActivity || $this->muted || ! ($data['result'] ?? false)) {
            return $data;
        }

        foreach ($this->originals as $id => $old) {
            $new     = $this->db->table($this->table)->where($this->primaryKey, $id)->get()->getRowArray() ?? [];
            $changed = [];

            foreach ($this->activityFilter($new, false) as $key => $value) {
                if (array_key_exists($key, $old) && (string) $old[$key] !== (string) $value) {
                    $changed[$key] = $value;
                }
            }

            if ($changed === []) {
                continue;
            }

            ActivityLogger::log('update', $this->table, $id, $this->activityLabel($new), [
                'old' => $this->activityFilter(array_intersect_key($old, $changed)),
                'new' => $this->activityFilter($changed),
            ]);
        }

        $this->originals = [];

        return $data;
    }

    protected function rememberDeleted(array $data): array
    {
        $this->deleting = [];

        if ($this->logsActivity && ! $this->muted && ! empty($data['id'])) {
            foreach ($this->db->table($this->table)->whereIn($this->primaryKey, (array) $data['id'])->get()->getResultArray() as $row) {
                $this->deleting[$row[$this->primaryKey]] = $row;
            }
        }

        return $data;
    }

    protected function logDelete(array $data): array
    {
        if ($this->logsActivity && ! $this->muted && ($data['result'] ?? false)) {
            foreach ($this->deleting as $id => $row) {
                ActivityLogger::log('delete', $this->table, $id, $this->activityLabel($row));
            }
        }

        $this->deleting = [];

        return $data;
    }

    protected function activityFilter(array $attributes, bool $shorten = true): array
    {
        $attributes = ActivityLogger::sanitize(array_diff_key($attributes, array_flip($this->activityIgnored)));

        if (! $shorten) {
            return $attributes;
        }

        return array_map(
            static fn ($value) => is_string($value) && mb_strlen($value) > 300 ? '[ข้อความยาว ' . mb_strlen($value) . ' ตัวอักษร]' : $value,
            $attributes,
        );
    }
}
