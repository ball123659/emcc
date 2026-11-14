<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

/**
 * สิทธิ์ผู้ใช้ x หน้า (PK คู่ user_id + page_id) — can_view 0 = ไม่เห็น, 1 = เห็น
 *
 * แถวสิทธิ์ค่าเริ่มต้น 0 สร้างในโค้ดเท่านั้น (ไม่ใช้ trigger — MariaDB 10.4 crash)
 * - UserModel afterInsert -> createDefaultsForUser()
 * - PageModel afterInsert -> createDefaultsForPage()
 * - เพิ่มข้อมูลด้วย SQL ตรง ๆ -> เรียก fillMissing() (หรือ php spark emcc:fill-permissions)
 */
class UserPagePermissionModel extends Model
{
    protected $table         = 'user_page_permissions';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['user_id', 'page_id', 'can_view', 'updated_by', 'updated_at'];

    /** เติมแถวสิทธิ์ที่ขาด (ค่า 0) ให้ทุกคู่ผู้ใช้ x หน้า — คืนจำนวนแถวที่เพิ่ม */
    public static function fillMissing(): int
    {
        $db = Database::connect();
        $db->query(
            'INSERT INTO user_page_permissions (user_id, page_id, can_view, updated_at)
             SELECT u.id, p.id, 0, ? FROM users u CROSS JOIN pages p
             LEFT JOIN user_page_permissions upp ON upp.user_id = u.id AND upp.page_id = p.id
             WHERE upp.user_id IS NULL',
            [date('Y-m-d H:i:s')],
        );

        return $db->affectedRows();
    }

    /** ผู้ใช้ใหม่ -> แถวสิทธิ์ของทุกหน้า = 0 */
    public static function createDefaultsForUser(int $userId): void
    {
        $db  = Database::connect();
        $now = date('Y-m-d H:i:s');

        $rows = array_map(
            static fn (array $page) => ['user_id' => $userId, 'page_id' => (int) $page['id'], 'can_view' => 0, 'updated_at' => $now],
            $db->table('pages')->select('id')->get()->getResultArray(),
        );

        if ($rows !== []) {
            $db->table('user_page_permissions')->ignore(true)->insertBatch($rows);
        }
    }

    /** หน้าใหม่ -> ผู้ใช้ทุกคน (รวมที่ลบแบบกู้คืนได้) ได้แถวสิทธิ์ = 0 */
    public static function createDefaultsForPage(int $pageId): void
    {
        $db  = Database::connect();
        $now = date('Y-m-d H:i:s');

        $rows = array_map(
            static fn (array $user) => ['user_id' => (int) $user['id'], 'page_id' => $pageId, 'can_view' => 0, 'updated_at' => $now],
            $db->table('users')->select('id')->get()->getResultArray(),
        );

        foreach (array_chunk($rows, 500) as $chunk) {
            $db->table('user_page_permissions')->ignore(true)->insertBatch($chunk);
        }
    }
}
