<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

class Reorder
{

    public static function apply(BaseConnection $db, string $table, array $orderedIds, string $label, array $where = [], string $column = 'sort_order'): bool
    {
        $orderedIds = array_values(array_unique(array_map('intval', $orderedIds)));

        $all = array_map('intval', array_column(
            $db->table($table)->select('id')->where($where)->orderBy($column)->orderBy('id')->get()->getResultArray(),
            'id',
        ));

        $moved = array_values(array_intersect($orderedIds, $all));

        if ($moved === []) {
            return false;
        }

        $positions = array_keys(array_intersect($all, $moved));
        $result    = $all;

        foreach ($positions as $i => $position) {
            $result[$position] = $moved[$i];
        }

        $db->transStart();

        foreach (array_values($result) as $i => $id) {
            $db->table($table)->where('id', $id)->update([$column => $i + 1]);
        }

        $db->transComplete();

        if ($result === $all) {
            return false;
        }

        ActivityLogger::log('reorder', $table, null, "เรียงลำดับ{$label}ใหม่", ['new' => array_values($moved)]);

        return true;
    }
}
