<?php

namespace App\Support;

use App\Models\Page;
use App\Models\User;
use App\Models\UserPagePermission;
use Illuminate\Support\Facades\Auth;

/**
 * จัดการสิทธิ์รายหน้า (เห็น / ไม่เห็น) ของผู้ใช้บทบาท user
 */
class UserPermissions
{
    /** หน้าที่ให้สิทธิ์ได้ (ไม่รวม admin_only และเมนูแม่ที่มีเมนูย่อย) [id => ชื่อ] */
    public static function assignablePageOptions(): array
    {
        $pages = Page::query()->active()->ordered()->with('parent')->withCount('children')->get();

        return $pages
            ->filter(fn (Page $page) => ! $page->admin_only && $page->children_count === 0)
            ->mapWithKeys(fn (Page $page) => [
                $page->id => $page->parent ? $page->parent->name_th.' › '.$page->name_th : $page->name_th,
            ])
            ->all();
    }

    /** id ของหน้าที่ผู้ใช้เห็นอยู่ (เฉพาะหน้าที่ให้สิทธิ์ได้) */
    public static function selectedPageIds(User $user): array
    {
        return UserPagePermission::query()
            ->where('user_id', $user->id)
            ->where('can_view', true)
            ->whereIn('page_id', array_keys(static::assignablePageOptions()))
            ->pluck('page_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * บันทึกสิทธิ์: หน้าที่เลือก = 1, หน้าอื่น = 0, เมนูแม่ = 1 ถ้ามีเมนูย่อยที่เห็น, หน้า admin_only = 0 เสมอ
     * administrator/developer ไม่ต้องใช้แถวสิทธิ์ (เข้าได้ทุกหน้า) จึงตั้งเป็น 0 ทั้งหมด
     */
    public static function sync(User $user, array $selectedPageIds): void
    {
        $assignable = array_map('intval', array_keys(static::assignablePageOptions()));
        $selected = $user->isAdmin() ? [] : array_values(array_intersect($assignable, array_map('intval', $selectedPageIds)));

        $before = static::visibleCodes($user);
        $now = now();

        foreach (Page::query()->get() as $page) {
            $canView = in_array($page->id, $selected, true);

            if (! $user->isAdmin() && ! $page->admin_only && ! in_array($page->id, $assignable, true)) {
                // เมนูแม่: เห็นถ้ามีเมนูย่อยที่เลือกไว้
                $canView = Page::query()->where('parent_id', $page->id)->whereIn('id', $selected)->exists();
            }

            UserPagePermission::query()->updateOrInsert(
                ['user_id' => $user->id, 'page_id' => $page->id],
                ['can_view' => $canView, 'updated_by' => Auth::id(), 'updated_at' => $now],
            );
        }

        $user->forgetPagePermissions();
        $after = static::visibleCodes($user);

        if ($before !== $after) {
            ActivityLogger::log('permission_update', $user, "แก้สิทธิ์รายหน้าของ {$user->username}", [
                'old' => $before,
                'new' => $after,
            ]);
        }
    }

    private static function visibleCodes(User $user): array
    {
        return Page::query()
            ->join('user_page_permissions as upp', 'upp.page_id', '=', 'pages.id')
            ->where('upp.user_id', $user->id)
            ->where('upp.can_view', true)
            ->orderBy('pages.id')
            ->pluck('pages.code')
            ->all();
    }
}
