<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Popup;
use App\Models\SiteSetting;
use App\Models\Slide;
use App\Models\User;
use App\Models\UserPagePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // seed ทุกเทสต์ (property $seed จะ seed แค่ตอน migrate ครั้งแรกของทั้งชุดทดสอบ)
        $this->seed();
    }

    public function test_seed_matches_initial_data_in_sql(): void
    {
        $this->assertSame(10, Page::count());
        $this->assertTrue(Page::where('code', 'users')->value('admin_only'));
        $this->assertSame('highlight', Page::where('code', 'slides')->first()->parent->code);

        $developer = User::where('username', 'developer')->firstOrFail();
        $this->assertSame(User::ROLE_DEVELOPER, $developer->role);
        $this->assertTrue($developer->must_change_password);
        $this->assertTrue(Hash::check('passwordemcc', $developer->password));

        $this->assertSame(10, UserPagePermission::where('user_id', $developer->id)->where('can_view', false)->count());
        $this->assertSame(9, SiteSetting::count());
        $this->assertSame('ศูนย์เฝ้าระวังและควบคุมคุณภาพสิ่งแวดล้อม', SiteSetting::getValue('site_name'));
    }

    public function test_seeder_can_run_twice(): void
    {
        $this->seed();

        $this->assertSame(10, Page::count());
        $this->assertSame(1, User::count());
    }

    public function test_new_user_and_new_page_get_default_hidden_permissions(): void
    {
        $user = User::factory()->create();
        $this->assertSame(Page::count(), $user->permissions()->count());

        $page = Page::create(['code' => 'new_page', 'name_th' => 'หน้าใหม่']);
        $this->assertSame(User::count(), UserPagePermission::where('page_id', $page->id)->count());
        $this->assertSame(0, UserPagePermission::where('can_view', true)->count());
    }

    public function test_page_permission_rules(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->administrator()->create();

        $this->assertFalse($user->canViewPage('news'));
        $this->assertTrue($admin->canViewPage('news'));
        $this->assertTrue($admin->canViewPage('users'));

        UserPagePermission::where('user_id', $user->id)->update(['can_view' => true]);

        $user->forgetPagePermissions(); // ในระบบจริงแต่ละคำขอโหลดผู้ใช้ใหม่

        $this->assertTrue($user->canViewPage('news'));
        $this->assertFalse($user->canViewPage('users'), 'หน้า admin_only ให้สิทธิ์ user ไม่ได้');
    }

    public function test_user_input_is_normalized(): void
    {
        $user = User::factory()->create(['username' => '  Somchai.K ', 'phone' => '081-234-5678']);

        $this->assertSame('somchai.k', $user->username);
        $this->assertSame('0812345678', $user->phone);
    }

    public function test_created_by_and_updated_by_are_filled(): void
    {
        $editor = User::factory()->create();
        $this->actingAs($editor);

        $popup = Popup::create([
            'title' => 'ทดสอบ',
            'image_th' => 'popups/a.webp',
            'alt_th' => 'รูปทดสอบ',
            'start_date' => today(),
            'end_date' => today()->addDays(3),
        ]);

        $this->assertSame($editor->id, $popup->created_by);
        $this->assertSame($editor->id, $popup->updated_by);
    }

    public function test_popup_status_and_visible_scopes(): void
    {
        $base = ['title' => 'p', 'image_th' => 'x.webp', 'alt_th' => 'x'];

        $showing = Popup::create($base + ['start_date' => today()->subDay(), 'end_date' => today()->addDay()]);
        $scheduled = Popup::create($base + ['start_date' => today()->addDay(), 'end_date' => today()->addDays(2)]);
        $expired = Popup::create($base + ['start_date' => today()->subDays(3), 'end_date' => today()->subDay()]);
        $disabled = Popup::create($base + ['start_date' => today(), 'end_date' => today(), 'is_active' => false]);

        $this->assertSame(Popup::STATUS_SHOWING, $showing->displayStatus());
        $this->assertSame(Popup::STATUS_SCHEDULED, $scheduled->displayStatus());
        $this->assertSame(Popup::STATUS_EXPIRED, $expired->displayStatus());
        $this->assertSame(Popup::STATUS_DISABLED, $disabled->displayStatus());
        $this->assertSame([$showing->id], Popup::visible()->pluck('id')->all());

        Slide::create(['title' => 's1', 'image_th' => 'a.webp', 'alt_th' => 'a']);
        Slide::create(['title' => 's2', 'image_th' => 'b.webp', 'alt_th' => 'b', 'end_date' => today()->subDay()]);
        $this->assertSame(['s1'], Slide::visible()->pluck('title')->all());
    }
}
