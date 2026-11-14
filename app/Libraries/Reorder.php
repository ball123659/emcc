<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * ลากเรียงลำดับอย่างปลอดภัย
 * ผู้ใช้อาจลากเรียงขณะดูแค่บางส่วน (แท็บ/ตัวกรอง) — นำรายการที่ลากไปวางในตำแหน่งเดิมของกลุ่มนั้น
 * แล้วเรียงเลขใหม่ 1..N ทั้งตาราง (รวมที่ถูกลบแบบกู้คืนได้) เลขลำดับจึงไม่ซ้ำกันเสมอ
 */
class Reorder
{
    /**
     * @param list<int|string> $orderedIds id ตามลำดับใหม่ (เฉพาะที่เห็นในหน้าจอ)
     *
     * @return bool เปลี่ยนลำดับจริงหรือไม่
     */
    public static function apply(BaseConnection $db, string $table, array $orderedIds, string $label, array $where = [], string $column = 'sort_order'): bool
    {
        $orderedIds = array_values(array_unique(array_map('intval', $orderedIds)));

        $all = array_map('intval', array_column(
            $db->table($table)->select('id')->where($where)->orderBy($column)->orderBy('id')->get()->getResultArray(),
            'id',
        ));

        // ยอมรับเฉพาะ id ที่มีจริงในตาราง (กันส่ง id มั่ว)
        $moved = array_values(array_intersect($orderedIds, $all));

        if ($moved === []) {
            return false;
        }

        $positions = array_keys(array_intersect($all, $moved)); // ตำแหน่งเดิมของกลุ่มที่ถูกลาก
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
