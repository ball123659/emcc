<?php

namespace App\Models;

use App\Libraries\Storage;

/**
 * ไฟล์ดาวน์โหลด: อัปโหลดไฟล์ หรือ ลิงก์ภายนอก
 * อัปไฟล์ใหม่ทับได้ ลิงก์ /downloads/{id} เดิมใช้ต่อได้ (ไฟล์เก่าถูกลบ)
 */
class DownloadModel extends BaseModel
{
    public const TYPE_FILE = 'file';
    public const TYPE_LINK = 'link';

    public const TYPES = [
        self::TYPE_FILE => 'อัปโหลดไฟล์',
        self::TYPE_LINK => 'ลิงก์ภายนอก',
    ];

    public const SCAN_CLEAN = 'clean';

    protected $table          = 'downloads';
    protected $useSoftDeletes = true;
    protected bool $tracksUser   = true;
    protected bool $logsActivity = true;
    protected $allowedFields  = [
        'category_id', 'title_th', 'title_en', 'description_th', 'description_en', 'type',
        'file_path', 'original_name', 'file_extension', 'mime_type', 'file_size', 'file_hash', 'scan_status', 'scanned_at',
        'external_url', 'is_active', 'sort_order', 'created_by', 'updated_by',
    ];

    public function __construct(...$args)
    {
        parent::__construct(...$args);
        array_unshift($this->beforeInsert, 'appendSortOrder', 'matchType');
        array_unshift($this->beforeUpdate, 'matchType');
    }

    protected function appendSortOrder(array $data): array
    {
        if (! isset($data['data']['sort_order'])) {
            $max                        = $this->db->table($this->table)->selectMax('sort_order')->get()->getRowArray();
            $data['data']['sort_order'] = (int) ($max['sort_order'] ?? 0) + 1;
        }

        return $data;
    }

    /** ให้ตรงกับ CHECK chk_downloads_type: ไฟล์ต้องไม่มีลิงก์ / ลิงก์ต้องไม่มีไฟล์ */
    protected function matchType(array $data): array
    {
        $row = &$data['data'];

        if (($row['type'] ?? null) === self::TYPE_LINK) {
            foreach (['file_path', 'original_name', 'file_extension', 'mime_type', 'file_size', 'file_hash', 'scan_status', 'scanned_at'] as $field) {
                $row[$field] = null;
            }
        } elseif (($row['type'] ?? null) === self::TYPE_FILE) {
            $row['external_url'] = null;
        }

        return $data;
    }

    public static function publicUrl(array $row): string
    {
        return site_url('downloads/' . $row['id']);
    }

    /** ชื่อไฟล์ตอนดาวน์โหลด: ใช้ชื่อเดิมของไฟล์ (ถ้าไม่มี ใช้หัวข้อ) */
    public static function downloadName(array $row): string
    {
        return $row['original_name'] ?: mb_substr((string) $row['title_th'], 0, 150) . '.' . $row['file_extension'];
    }

    /** รายการที่แสดงหน้าเว็บได้: เปิดอยู่ และถ้าเป็นไฟล์ต้องสแกนผ่านแล้ว */
    public function visible(): static
    {
        return $this->where('is_active', 1)
            ->groupStart()
            ->where('type', self::TYPE_LINK)
            ->orGroupStart()->where('type', self::TYPE_FILE)->where('scan_status', self::SCAN_CLEAN)->groupEnd()
            ->groupEnd()
            ->orderBy('sort_order')->orderBy('id');
    }

    /** ลบถาวร (ลบไฟล์ด้วย) */
    public function forceDelete(int $id): void
    {
        $row = $this->withDeleted()->find($id);
        $this->delete($id, true);
        Storage::delete($row['file_path'] ?? null);
    }
}
