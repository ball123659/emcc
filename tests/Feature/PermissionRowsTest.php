<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Models\UserPagePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * แถวสิทธิ์รายหน้าค่าเริ่มต้น 0 สร้างในโค้ด (Model User / Page) — ไม่ใช้ trigger แล้ว
 * (MariaDB 10.4 / XAMPP crash ตอน INSERT users ใน execute_trigger)
 */
class PermissionRowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function isMysql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    public function test_database_has_no_permission_triggers(): void
    {
        if (! $this->isMysql()) {
            $this->markTestSkipped('ตรวจ trigger ได้เฉพาะ MySQL / MariaDB');
        }

        $triggers = DB::select('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()');

        $this->assertSame([], $triggers);
    }

    public function test_new_user_gets_hidden_row_for_every_page(): void
    {
        $user = User::factory()->create();

        $rows = UserPagePermission::where('user_id', $user->id)->get();

        $this->assertSame(Page::count(), $rows->count());
        $this->assertSame(Page::pluck('id')->sort()->values()->all(), $rows->pluck('page_id')->sort()->values()->all());
        $this->assertTrue($rows->every(fn ($row) => $row->can_view === false));
    }

    public function test_new_page_gets_hidden_row_for_every_user_including_soft_deleted(): void
    {
        $active = User::factory()->create();
        $deleted = User::factory()->create();
        $deleted->delete();

        $page = Page::create(['code' => 'reports', 'name_th' => 'รายงาน']);

        $rows = UserPagePermission::where('page_id', $page->id)->get();

        $this->assertSame(User::withTrashed()->count(), $rows->count());
        $this->assertEqualsCanonicalizing(User::withTrashed()->pluck('id')->all(), $rows->pluck('user_id')->all());
        $this->assertTrue($rows->every(fn ($row) => $row->can_view === false));
        $this->assertContains($active->id, $rows->pluck('user_id')->all());
        $this->assertContains($deleted->id, $rows->pluck('user_id')->all());
    }

    public function test_full_matrix_after_seeding_and_existing_rights_are_kept(): void
    {
        $user = User::factory()->create();
        UserPagePermission::where('user_id', $user->id)->update(['can_view' => true]);

        $this->seed(); // seed ซ้ำ ต้องไม่ทับสิทธิ์ที่ให้ไว้แล้ว

        $this->assertSame(User::count() * Page::count(), UserPagePermission::count());
        $this->assertSame(Page::count(), UserPagePermission::where('user_id', $user->id)->where('can_view', true)->count());
    }

    public function test_fill_missing_covers_rows_inserted_without_model_events(): void
    {
        // เพิ่มผู้ใช้/หน้าด้วย query builder (ไม่มี model event) แบบที่ import SQL หรือสคริปต์ทำ
        $userId = DB::table('users')->insertGetId([
            'username' => 'rawuser', 'password' => bcrypt('x'), 'role' => 'user',
            'first_name' => 'a', 'last_name' => 'b', 'email' => 'raw@example.com',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $pageId = DB::table('pages')->insertGetId(['code' => 'raw_page', 'name_th' => 'หน้าดิบ', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(0, UserPagePermission::where('user_id', $userId)->count());

        $inserted = UserPagePermission::fillMissing();

        $this->assertGreaterThan(0, $inserted);
        $this->assertSame(Page::count(), UserPagePermission::where('user_id', $userId)->count());
        $this->assertSame(User::withTrashed()->count(), UserPagePermission::where('page_id', $pageId)->count());
        $this->assertSame(0, UserPagePermission::fillMissing(), 'รันซ้ำไม่เพิ่มแถว');
    }

    public function test_drop_migration_removes_old_triggers_and_fills_rows(): void
    {
        if (! $this->isMysql()) {
            $this->markTestSkipped('trigger มีเฉพาะ MySQL / MariaDB');
        }

        DB::unprepared('CREATE TRIGGER `trg_pages_after_insert` AFTER INSERT ON `pages` FOR EACH ROW BEGIN END');
        DB::table('user_page_permissions')->delete();

        $migration = require database_path('migrations/2026_11_18_000001_drop_permission_triggers.php');
        $migration->up();

        $this->assertSame([], DB::select('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()'));
        $this->assertSame(User::count() * Page::count(), UserPagePermission::count());
    }
}
