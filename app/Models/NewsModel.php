<?php

namespace App\Models;

use App\Libraries\ActivityLogger;
use App\Libraries\HtmlSanitizer;
use App\Libraries\Slug;
use App\Libraries\Uploads\ImageProcessor;

/**
 * ข่าวประชาสัมพันธ์
 * - เนื้อหา rich text ถูกกรอง HTML (allowlist) ทุกครั้งที่บันทึก
 * - slug สร้างอัตโนมัติถ้าเว้นว่าง: ปี-เดือน-ชื่ออังกฤษ หรือ ปี-เดือน-เลขข่าว (ชื่อไทยล้วน)
 * - status = published + published_at (ระดับวัน) ในอนาคต = ตั้งเวลาเผยแพร่
 * - ข่าวที่เคยเผยแพร่แล้วห้ามเปลี่ยน slug (ลิงก์ที่แชร์ไปแล้วจะไม่เสีย)
 */
class NewsModel extends BaseModel
{
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_HIDDEN    = 'hidden';

    public const STATUSES = [
        self::STATUS_DRAFT     => 'ร่าง',
        self::STATUS_PUBLISHED => 'เผยแพร่',
        self::STATUS_HIDDEN    => 'ซ่อน',
    ];

    public const STATUS_HINTS = [
        self::STATUS_DRAFT     => 'ร่าง — ยังไม่แสดงหน้าเว็บ',
        self::STATUS_PUBLISHED => 'เผยแพร่ — แสดงหน้าเว็บตั้งแต่วันที่เผยแพร่',
        self::STATUS_HIDDEN    => 'ซ่อน — นำออกจากหน้าเว็บชั่วคราว',
    ];

    /** สถานะที่แสดงในหลังบ้าน (เพิ่ม "ตั้งเวลาเผยแพร่") */
    public const DISPLAY_SCHEDULED = 'scheduled';

    public const DISPLAY_LABELS = [
        self::STATUS_DRAFT       => 'ร่าง',
        self::STATUS_PUBLISHED   => 'เผยแพร่แล้ว',
        self::DISPLAY_SCHEDULED  => 'ตั้งเวลาเผยแพร่',
        self::STATUS_HIDDEN      => 'ซ่อน',
    ];

    public const DISPLAY_COLORS = [
        self::STATUS_DRAFT       => 'gray',
        self::STATUS_PUBLISHED   => 'success',
        self::DISPLAY_SCHEDULED  => 'info',
        self::STATUS_HIDDEN      => 'warning',
    ];

    protected $table          = 'news';
    protected $useSoftDeletes = true;
    protected bool $tracksUser   = true;
    protected bool $logsActivity = true;
    protected $allowedFields  = [
        'category_id', 'slug', 'title_th', 'title_en', 'excerpt_th', 'excerpt_en', 'content_th', 'content_en',
        'cover_image', 'cover_thumb', 'cover_alt_th', 'cover_alt_en', 'status', 'published_at', 'is_pinned',
        'created_by', 'updated_by',
    ];

    public function __construct(...$args)
    {
        parent::__construct(...$args);
        array_unshift($this->beforeInsert, 'prepare');
        array_unshift($this->beforeUpdate, 'prepare');
        array_unshift($this->afterInsert, 'replaceTemporarySlug');
    }

    protected function prepare(array $data): array
    {
        $row      = &$data['data'];
        $id       = isset($data['id']) ? ((array) $data['id'])[0] ?? null : null;
        $original = $id !== null ? $this->db->table($this->table)->where('id', $id)->get()->getRowArray() : null;

        // เนื้อหา: กรอง HTML ก่อนเก็บเสมอ
        if (array_key_exists('content_th', $row)) {
            $row['content_th'] = HtmlSanitizer::clean((string) $row['content_th']) ?? '';
        }

        if (array_key_exists('content_en', $row)) {
            $row['content_en'] = HtmlSanitizer::isBlank($row['content_en']) ? null : HtmlSanitizer::clean($row['content_en']);
        }

        // วันที่เผยแพร่เป็นระดับวัน (เวลา 00:00) — เผยแพร่โดยไม่ระบุวัน = วันนี้
        $status = $row['status'] ?? $original['status'] ?? self::STATUS_DRAFT;

        if (array_key_exists('published_at', $row) && ! empty($row['published_at'])) {
            $row['published_at'] = date('Y-m-d 00:00:00', strtotime((string) $row['published_at']));
        }

        $publishedAt = array_key_exists('published_at', $row) ? $row['published_at'] : ($original['published_at'] ?? null);

        if ($status === self::STATUS_PUBLISHED && empty($publishedAt)) {
            $row['published_at'] = date('Y-m-d 00:00:00');
            $publishedAt         = $row['published_at'];
        }

        // slug
        if ($original !== null && self::isSlugLocked($original)) {
            unset($row['slug']); // เคยเผยแพร่แล้ว ห้ามเปลี่ยน
        } elseif (array_key_exists('slug', $row) || $original === null) {
            $slug = Slug::make((string) ($row['slug'] ?? ''));

            if ($slug === '') {
                $merged = array_merge($original ?? [], $row);
                $slug   = $this->generateSlug($merged, $publishedAt, $id);
            } else {
                $slug = $this->uniqueSlug(Slug::limit($slug, 190), $id);
            }

            $row['slug'] = $slug;
        }

        // รูปปกย่อ (_thumb.webp) ถูกสร้างคู่กับรูปปกโดย ImageProcessor
        if (array_key_exists('cover_image', $row)) {
            $row['cover_thumb'] = $row['cover_image'] ? ImageProcessor::thumbnailPathFor($row['cover_image']) : null;
        }

        return $data;
    }

    /**
     * slug: ปี-เดือน-ชื่อภาษาอังกฤษ เช่น 2026-11-pm25-report
     * ไม่มีชื่ออังกฤษ -> ปี-เดือน-เลขข่าว เช่น 2026-11-15 (ข่าวใหม่ได้ slug ชั่วคราวก่อน แล้วแทนทันทีหลังบันทึก)
     */
    private function generateSlug(array $row, ?string $publishedAt, int|string|null $id): string
    {
        $date  = date('Y-m', $publishedAt ? strtotime($publishedAt) : time());
        $words = Slug::make((string) (($row['title_en'] ?? '') ?: ($row['title_th'] ?? '')), 8);

        if (strlen($words) < 3) {
            $base = $id !== null ? "{$date}-{$id}" : "{$date}-tmp-" . Slug::random(10);
        } else {
            $base = trim($date . '-' . Slug::limit($words, 120), '-');
        }

        return $this->uniqueSlug($base, $id);
    }

    private function uniqueSlug(string $base, int|string|null $id): string
    {
        $slug = $base;
        $i    = 2;

        while ($this->db->table($this->table)->where('slug', $slug)->when($id !== null, static fn ($q) => $q->where('id !=', $id))->countAllResults() > 0) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /** ข่าวชื่อไทยล้วน: แทน slug ชั่วคราวด้วย ปี-เดือน-เลขข่าว และแก้ log การสร้างให้ตรง */
    protected function replaceTemporarySlug(array $data): array
    {
        if (! ($data['result'] ?? false)) {
            return $data;
        }

        $row = $this->db->table($this->table)->where('id', $data['id'])->get()->getRowArray();

        if ($row && preg_match('/^\d{4}-\d{2}-tmp-[a-z0-9]{10}$/', (string) $row['slug'])) {
            $date = date('Y-m', $row['published_at'] ? strtotime($row['published_at']) : time());
            $slug = $this->uniqueSlug("{$date}-{$row['id']}", $row['id']);
            $this->db->table($this->table)->where('id', $row['id'])->update(['slug' => $slug]);
            $data['tempSlug']  = $row['slug'];
            $data['finalSlug'] = $slug;
        }

        return $data;
    }

    protected function logCreate(array $data): array
    {
        $data = parent::logCreate($data);

        if (isset($data['finalSlug'])) {
            $log = $this->db->table('activity_logs')->where(['subject_type' => 'news', 'subject_id' => $data['id'], 'action' => 'create'])
                ->orderBy('id', 'desc')->get()->getRowArray();

            if ($log) {
                $changes                = json_decode((string) $log['changes'], true) ?: [];
                $changes['new']['slug'] = $data['finalSlug'];
                $this->db->table('activity_logs')->where('id', $log['id'])
                    ->update(['changes' => json_encode(ActivityLogger::sanitize($changes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            }
        }

        return $data;
    }

    /**
     * ข่าวที่เคยเผยแพร่แล้วห้ามเปลี่ยน slug ตลอดไป (แม้จะซ่อนภายหลัง)
     * "เคยเผยแพร่" = สถานะไม่ใช่ร่าง และวันที่เผยแพร่มาถึงแล้ว
     */
    public static function isSlugLocked(array $row): bool
    {
        return ($row['status'] ?? self::STATUS_DRAFT) !== self::STATUS_DRAFT
            && ! empty($row['published_at'])
            && strtotime((string) $row['published_at']) <= time();
    }

    public static function displayStatus(array $row): string
    {
        if ($row['status'] === self::STATUS_PUBLISHED && ! empty($row['published_at']) && strtotime($row['published_at']) > time()) {
            return self::DISPLAY_SCHEDULED;
        }

        return $row['status'];
    }

    /** ข่าวที่เผยแพร่แล้ว (ถึงเวลาเผยแพร่) ปักหมุดขึ้นก่อน */
    public function published(): static
    {
        return $this->where('status', self::STATUS_PUBLISHED)
            ->where('published_at <=', date('Y-m-d H:i:s'))
            ->orderBy('is_pinned', 'desc')
            ->orderBy('published_at', 'desc');
    }

    /** กรองตามสถานะที่แสดงในหลังบ้าน */
    public function whereDisplayStatus(?string $status): static
    {
        $now = date('Y-m-d H:i:s');

        match ($status) {
            self::STATUS_PUBLISHED  => $this->where('status', self::STATUS_PUBLISHED)->where('published_at <=', $now),
            self::DISPLAY_SCHEDULED => $this->where('status', self::STATUS_PUBLISHED)->where('published_at >', $now),
            self::STATUS_DRAFT, self::STATUS_HIDDEN => $this->where('status', $status),
            default => null,
        };

        return $this;
    }
}
