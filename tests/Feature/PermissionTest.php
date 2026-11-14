<?php

namespace Tests\Feature;

use App\Libraries\UserPermissions;
use App\Models\PageModel;
use App\Models\UserPagePermissionModel;
use Tests\Support\TestCase;

/**
 * สิทธิ์รายหน้า (เห็น / ไม่เห็น) — ตรวจฝั่งเซิร์ฟเวอร์ทุกเส้นทาง, แถวสิทธิ์สร้างในโค้ด (ไม่ใช้ trigger)
 */
final class PermissionTest extends TestCase
{
    private const PAGES = [
        'slides'        => ['backend/slides', 'backend/slides/create'],
        'popups'        => ['backend/popups', 'backend/popups/create'],
        'news'          => ['backend/news', 'backend/news/create', 'backend/news-categories'],
        'downloads'     => ['backend/downloads', 'backend/downloads/create', 'backend/download-categories'],
        'site_settings' => ['backend/site-settings'],
        'monitoring'    => ['backend/monitoring'],
    ];

    public function testNoTriggersAndPermissionRowsCreatedInCode(): void
    {
        $this->assertSame([], db_connect()->query('SHOW TRIGGERS')->getResultArray());

        $user  = $this->makeUser();
        $pages = db_connect()->table('pages')->countAllResults();
        $this->assertSame($pages, db_connect()->table('user_page_permissions')->where(['user_id' => $user['id'], 'can_view' => 0])->countAllResults());

        $pageId = (new PageModel())->insert(['code' => 'reports', 'name_th' => 'รายงาน']);
        $users  = db_connect()->table('users')->countAllResults();
        $this->assertSame($users, db_connect()->table('user_page_permissions')->where('page_id', $pageId)->countAllResults());

        // insert ตรง ๆ แล้วเติมด้วย fillMissing
        db_connect()->table('users')->insert(['username' => 'raw', 'password' => 'x', 'first_name' => 'a', 'last_name' => 'b', 'email' => 'raw@example.com']);
        $this->assertSame($pages + 1, UserPagePermissionModel::fillMissing());
    }

    public function testUserWithoutPermissionGets403EverywhereAndAdminsGetAll(): void
    {
        $user  = $this->makeUser();
        $admin = $this->admin();

        foreach (self::PAGES as $urls) {
            foreach ($urls as $url) {
                $this->actingAs($user)->get($url)->assertStatus(403);
                $this->actingAs($admin)->get($url)->assertOK();
            }
        }

        foreach (['backend/users', 'backend/users/create', 'backend/activity-logs'] as $url) {
            $res = $this->actingAs($user)->get($url);
            $res->assertStatus(403);
            $res->assertSee('คุณไม่มีสิทธิ์เข้าใช้งานหน้านี้');
        }

        // POST / AJAX ก็ถูกตรวจ ไม่ใช่แค่ซ่อนเมนู
        $this->actingAs($user)->post('backend/slides/reorder', ['ids' => ['1']])->assertStatus(403);
        $this->actingAs($user)->post('backend/site-settings', [])->assertStatus(403);
    }

    public function testGrantedPagesAreVisibleAndMenuFollowsPermissions(): void
    {
        $user = $this->makeUser([], ['news']);
        $res  = $this->actingAs($user)->get('backend');
        $res->assertOK();
        $res->assertSee('หมวดหมู่ข่าว');
        $res->assertDontSee('Slide Show');
        $res->assertDontSee('จัดการผู้ใช้งาน');
        $res->assertDontSee('ข่าวที่เผยแพร่แล้ว'); // สรุปบนแดชบอร์ดต้องมีสิทธิ์หน้า dashboard

        foreach (self::PAGES['news'] as $url) {
            $this->actingAs($user)->get($url)->assertOK();
        }

        $this->actingAs($user)->get('backend/slides')->assertStatus(403);
    }

    public function testAdminOnlyPagesCannotBeGrantedToUsers(): void
    {
        $user = $this->makeUser();
        $ids  = array_column(db_connect()->table('pages')->get()->getResultArray(), 'id');
        UserPermissions::sync($user, $ids);

        $this->assertNotContains('users', UserPermissions::visibleCodes((int) $user['id']));
        $this->assertNotContains('activity_logs', UserPermissions::visibleCodes((int) $user['id']));
        $this->assertContains('highlight', UserPermissions::visibleCodes((int) $user['id']), 'เมนูแม่เห็นเมื่อมีเมนูย่อย');

        // แม้ตั้ง can_view = 1 ตรง ๆ ในฐานข้อมูล ก็เข้าไม่ได้
        db_connect()->table('user_page_permissions')->where('user_id', $user['id'])->update(['can_view' => 1]);
        $this->actingAs($user)->get('backend/users')->assertStatus(403);
        $this->actingAs($user)->get('backend/activity-logs')->assertStatus(403);
        $this->assertSame('permission_update', $this->lastLog('permission_update')['action']);
    }
}
