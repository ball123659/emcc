<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;

/**
 * สิทธิ์ผู้ใช้ x หน้า (PK คู่ user_id + page_id) — can_view 0 = ไม่เห็น, 1 = เห็น
 */
class UserPagePermission extends Pivot
{
    public const CREATED_AT = null;

    protected $table = 'user_page_permissions';

    public $incrementing = false;

    public $timestamps = true;

    protected $fillable = ['user_id', 'page_id', 'can_view', 'updated_by'];

    protected $attributes = ['can_view' => false];

    protected function casts(): array
    {
        return [
            'can_view' => 'boolean',
        ];
    }

    /**
     * แถวสิทธิ์ค่าเริ่มต้น 0 สร้างในโค้ดเท่านั้น (ไม่ใช้ trigger — MariaDB 10.4 crash)
     * - Model User::created -> createDefaultsForUser()
     * - Model Page::created -> createDefaultsForPage()
     * - เพิ่มข้อมูลด้วย query builder / SQL ตรง ๆ -> เรียก fillMissing()
     */
    public static function fillMissing(): int
    {
        return DB::table('user_page_permissions')->insertUsing(
            ['user_id', 'page_id', 'can_view', 'updated_at'],
            DB::table('users')
                ->crossJoin('pages')
                ->leftJoin('user_page_permissions as upp', fn ($join) => $join
                    ->on('upp.user_id', '=', 'users.id')
                    ->on('upp.page_id', '=', 'pages.id'))
                ->whereNull('upp.user_id')
                ->select(['users.id', 'pages.id', DB::raw('0'), DB::raw('CURRENT_TIMESTAMP')]),
        );
    }

    /** ผู้ใช้ใหม่ -> แถวสิทธิ์ของทุกหน้า = 0 */
    public static function createDefaultsForUser(User $user): void
    {
        $now = now();

        static::query()->insertOrIgnore(
            Page::query()->pluck('id')->map(fn ($pageId) => [
                'user_id' => $user->getKey(),
                'page_id' => $pageId,
                'can_view' => false,
                'updated_at' => $now,
            ])->all()
        );
    }

    /** หน้าใหม่ -> ผู้ใช้ทุกคน (รวมที่ลบแบบกู้คืนได้) ได้แถวสิทธิ์ = 0 */
    public static function createDefaultsForPage(Page $page): void
    {
        $now = now();

        User::withTrashed()->select('id')->chunkById(500, function ($users) use ($page, $now): void {
            static::query()->insertOrIgnore($users->map(fn (User $user) => [
                'user_id' => $user->getKey(),
                'page_id' => $page->getKey(),
                'can_view' => false,
                'updated_at' => $now,
            ])->all());
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
