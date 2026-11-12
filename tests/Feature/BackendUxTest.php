<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\News\NewsResource;
use App\Filament\Resources\News\Pages\ListNews;
use App\Filament\Resources\Popups\Pages\ListPopups;
use App\Filament\Resources\Popups\PopupResource;
use App\Filament\Resources\Slides\Pages\ListSlides;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\RelationManagers\ActivityLogsRelationManager;
use App\Filament\Widgets\RecentActivity;
use App\Filament\Widgets\TodoList;
use App\Filament\Widgets\WelcomeBanner;
use App\Models\ActivityLog;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Popup;
use App\Models\Slide;
use App\Models\User;
use App\Models\UserPagePermission;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BackendUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    private function grant(User $user, string ...$codes): void
    {
        UserPagePermission::where('user_id', $user->id)
            ->whereIn('page_id', fn ($q) => $q->select('id')->from('pages')->whereIn('code', $codes))
            ->update(['can_view' => true]);
        $user->forgetPagePermissions(); // ในระบบจริงแต่ละคำขอโหลดผู้ใช้ใหม่
    }

    private function news(array $attributes = []): News
    {
        return News::create($attributes + [
            'category_id' => NewsCategory::first()->id, 'title_th' => 'ข่าว', 'title_en' => 'News',
            'content_th' => '<p>x</p>', 'cover_image' => 'news/x.webp',
        ]);
    }

    private function popup(array $attributes = []): Popup
    {
        return Popup::create($attributes + [
            'title' => 'p', 'image_th' => 'popups/aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa.webp', 'alt_th' => 'a',
            'start_date' => today(), 'end_date' => today()->addDays(2),
        ]);
    }

    // ---------- แท็บกรอง ----------

    public function test_news_tabs_filter_by_status(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $published = $this->news(['status' => 'published', 'published_at' => today()->subDay()]);
        $scheduled = $this->news(['status' => 'published', 'published_at' => today()->addDays(3)]);
        $draft = $this->news();

        Livewire::test(ListNews::class)->assertCanSeeTableRecords([$published, $scheduled, $draft])
            ->set('activeTab', 'scheduled')->assertCanSeeTableRecords([$scheduled])->assertCanNotSeeTableRecords([$published, $draft])
            ->set('activeTab', 'draft')->assertCanSeeTableRecords([$draft])->assertCanNotSeeTableRecords([$published]);
    }

    public function test_popup_tabs_filter_by_display_status(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $showing = $this->popup();
        $expired = $this->popup(['start_date' => today()->subDays(5), 'end_date' => today()->subDay()]);

        Livewire::test(ListPopups::class)
            ->set('activeTab', 'showing')->assertCanSeeTableRecords([$showing])->assertCanNotSeeTableRecords([$expired])
            ->set('activeTab', 'expired')->assertCanSeeTableRecords([$expired])->assertCanNotSeeTableRecords([$showing]);
    }

    // ---------- ปุ่มลัด ----------

    public function test_publish_now_and_hide_actions(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $draft = $this->news();

        Livewire::test(ListNews::class)->callAction(TestAction::make('publishNow')->table($draft));
        $draft->refresh();
        $this->assertSame('published', $draft->status);
        $this->assertTrue($draft->published_at->isToday());

        Livewire::test(ListNews::class)->callAction(TestAction::make('hide')->table($draft));
        $this->assertSame('hidden', $draft->refresh()->status);
    }

    public function test_extend_popup_action(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $active = $this->popup(['end_date' => today()->addDays(2)]);
        $expired = $this->popup(['start_date' => today()->subDays(9), 'end_date' => today()->subDays(3)]);

        Livewire::test(ListPopups::class)->callAction(TestAction::make('extend')->table($active));
        $this->assertSame(today()->addDays(9)->toDateString(), $active->refresh()->end_date->toDateString());

        Livewire::test(ListPopups::class)->set('activeTab', 'expired')->callAction(TestAction::make('extend')->table($expired));
        $this->assertSame(today()->addDays(7)->toDateString(), $expired->refresh()->end_date->toDateString(), 'หมดอายุแล้วนับจากวันนี้');
    }

    public function test_duplicate_copies_image_files(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        Storage::disk('local')->put('slides/11111111-1111-1111-1111-111111111111.webp', 'img');
        $slide = Slide::create(['title' => 'หน้าแรก', 'image_th' => 'slides/11111111-1111-1111-1111-111111111111.webp', 'alt_th' => 'a']);

        Livewire::test(ListSlides::class)->callAction(TestAction::make('duplicate')->table($slide));

        $copy = Slide::whereKeyNot($slide->id)->firstOrFail();
        $this->assertSame('สำเนา - หน้าแรก', $copy->title);
        $this->assertFalse($copy->is_active);
        $this->assertNotSame($slide->image_th, $copy->image_th);
        Storage::disk('local')->assertExists($copy->image_th);

        // ลบต้นฉบับถาวร ไม่กระทบไฟล์ของสำเนา
        $slide->forceDelete();
        Storage::disk('local')->assertExists($copy->image_th);
    }

    // ---------- ตัวเลขบนเมนู ----------

    public function test_navigation_badges(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $this->popup(['end_date' => today()->addDay()]);
        $this->popup(['end_date' => today()->addDays(20)]);
        $this->news();

        $this->assertSame('1', PopupResource::getNavigationBadge());
        $this->assertSame('1', NewsResource::getNavigationBadge());
    }

    // ---------- แดชบอร์ด ----------

    public function test_welcome_banner_shows_only_permitted_shortcuts(): void
    {
        $user = User::factory()->create(['first_name' => 'สมหญิง']);
        $this->grant($user, 'news');
        $this->actingAs($user);

        Livewire::test(WelcomeBanner::class)
            ->assertSee('คุณสมหญิง')
            ->assertSee('เพิ่มข่าว')
            ->assertDontSee('เพิ่ม Popup')
            ->assertDontSee('อัปโหลดไฟล์');
    }

    public function test_todo_list_items(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $this->popup(['end_date' => today()->addDay()]);
        $old = $this->news();
        News::query()->whereKey($old->id)->update(['updated_at' => now()->subDays(10)]);
        User::factory()->create(['locked_until' => now()->addMinutes(10)]);

        Livewire::test(TodoList::class)
            ->assertSee('Popup ใกล้หมดอายุ')
            ->assertSee('ข่าวร่างค้างเกิน 7 วัน')
            ->assertSee('บัญชีที่ถูกล็อก')
            ->assertDontSee('ไฟล์ที่สแกนไม่ผ่าน');

        Popup::query()->delete();
        News::query()->delete();
        User::query()->update(['locked_until' => null]);
        Livewire::test(TodoList::class)->assertSee('ไม่มีงานค้าง');
    }

    public function test_recent_activity_only_for_log_viewers(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        ActivityLog::create(['action' => 'create', 'subject_type' => 'news', 'description' => 'ข่าวทดสอบ']);
        $this->assertTrue(RecentActivity::canView());
        Livewire::test(RecentActivity::class)->assertSee('เมื่อสักครู่')->assertSeeText('สร้าง')->assertSeeText('ข่าวทดสอบ');

        $user = User::factory()->create();
        $this->grant($user, 'dashboard');
        $this->actingAs($user);
        $this->assertFalse(RecentActivity::canView());
    }

    // ---------- หน้าอื่น ----------

    public function test_user_edit_page_has_activity_tab(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $target = User::factory()->create();
        ActivityLog::create(['user_id' => $target->id, 'action' => 'login', 'description' => 'เข้าสู่ระบบ']);

        Livewire::test(ActivityLogsRelationManager::class, ['ownerRecord' => $target, 'pageClass' => EditUser::class])
            ->assertOk()
            ->assertSee('เข้าสู่ระบบ');
    }

    public function test_settings_are_tabbed(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        Livewire::test(SiteSettings::class)->assertSee(['ข้อมูลทั่วไป', 'ข้อมูลติดต่อ', 'โซเชียลมีเดีย']);
    }

    public function test_login_page_has_brand_panel_and_theme(): void
    {
        $this->get('/backend/login')
            ->assertSee('emcc-auth-brand', false)
            ->assertSee('css/emcc/admin.css', false);
    }

    public function test_empty_list_shows_call_to_action(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        $this->get('/backend/slides')->assertSee('ยังไม่มีสไลด์')->assertSee('เพิ่มสไลด์แรก');
    }
}
