<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\SiteSettings;
use App\Filament\Widgets\ActivePopups;
use App\Filament\Widgets\ContentStats;
use App\Filament\Widgets\PopularDownloads;
use App\Models\ActivityLog;
use App\Models\Download;
use App\Models\DownloadCategory;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Popup;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\UserPagePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function grant(User $user, string $code): void
    {
        UserPagePermission::where('user_id', $user->id)
            ->whereIn('page_id', fn ($q) => $q->select('id')->from('pages')->where('code', $code))
            ->update(['can_view' => true]);
        $user->forgetPagePermissions(); // ในระบบจริงแต่ละคำขอโหลดผู้ใช้ใหม่
    }

    // ---------- ตั้งค่าเว็บไซต์ ----------

    public function test_settings_form_loads_and_saves(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        Livewire::test(SiteSettings::class)
            ->assertSchemaStateSet(['phone.th' => '038-683933 / 081-7323485'])
            ->fillForm([
                'phone.th' => ' 038-683933 / 081-7323485 / 02-1234567 ',
                'email.th' => 'info@emcc.example',
                'address.th' => 'นิคมอุตสาหกรรม',
                'address.en' => 'Industrial Estate',
                'facebook_url.th' => 'https://www.facebook.com/emcc',
                'site_name.en' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('บันทึกการตั้งค่าแล้ว');

        $this->assertSame('038-683933 / 081-7323485 / 02-1234567', SiteSetting::where('key', 'phone')->value('value_th'));
        $this->assertNull(SiteSetting::where('key', 'phone')->value('value_en'));
        $this->assertSame('info@emcc.example', SiteSetting::getValue('email'));
        $this->assertNull(SiteSetting::where('key', 'site_name')->value('value_en'));

        app()->setLocale('en');
        $this->assertSame('Industrial Estate', SiteSetting::getValue('address'));
        $this->assertSame('ศูนย์เฝ้าระวังและควบคุมคุณภาพสิ่งแวดล้อม', SiteSetting::getValue('site_name'), 'อังกฤษว่าง -> ใช้ไทย');

        $this->assertTrue(ActivityLog::where('subject_type', 'site_settings')->where('action', 'update')->exists());
    }

    public function test_settings_validation(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'email.th' => 'not-an-email',
                'facebook_url.th' => 'javascript:alert(1)',
                'line_url.th' => 'ftp://line.me',
                'phone.th' => 'โทร 038',
            ])
            ->call('save')
            ->assertHasFormErrors(['email.th', 'facebook_url.th', 'line_url.th', 'phone.th']);

        $this->assertNull(SiteSetting::where('key', 'email')->value('value_th'));
    }

    public function test_settings_page_requires_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get('/backend/site-settings')->assertForbidden();

        $this->grant($user, 'site_settings');
        $this->get('/backend/site-settings')->assertOk()->assertSee('ข้อมูลติดต่อ');
    }

    // ---------- แดชบอร์ด ----------

    public function test_dashboard_widgets_show_counts_and_lists(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $category = NewsCategory::first();
        $base = ['category_id' => $category->id, 'title_th' => 'ข่าว', 'content_th' => '<p>x</p>', 'cover_image' => 'news/x.webp'];
        News::create($base + ['status' => 'published', 'published_at' => today()]);
        News::create($base + ['status' => 'published', 'published_at' => today()->subMonths(2)]);
        News::create($base + ['status' => 'published', 'published_at' => today()->addDays(3)]);
        News::create($base + ['status' => 'draft']);

        $popup = ['image_th' => 'popups/x.webp', 'alt_th' => 'a'];
        Popup::create($popup + ['title' => 'ใกล้หมด', 'start_date' => today()->subDay(), 'end_date' => today()->addDays(2)]);
        Popup::create($popup + ['title' => 'อีกนาน', 'start_date' => today(), 'end_date' => today()->addDays(30)]);
        Popup::create($popup + ['title' => 'หมดแล้ว', 'start_date' => today()->subDays(9), 'end_date' => today()->subDay()]);

        $dl = ['category_id' => DownloadCategory::first()->id, 'type' => 'link'];
        Download::create($dl + ['title_th' => 'ไฟล์ฮิต', 'external_url' => 'https://a.example'])->forceFill(['download_count' => 50])->saveQuietly();
        Download::create($dl + ['title_th' => 'ไฟล์รอง', 'external_url' => 'https://b.example'])->forceFill(['download_count' => 7])->saveQuietly();
        Download::create($dl + ['title_th' => 'ไม่มีใครโหลด', 'external_url' => 'https://c.example']);

        Livewire::test(ContentStats::class)
            ->assertSee('ข่าวที่เผยแพร่แล้ว')
            ->assertSee('เดือนนี้ 1 ข่าว')
            ->assertSee('1 / 1')
            ->assertSee('1 รายการจะหมดอายุใน 7 วัน');

        Livewire::test(ActivePopups::class)
            ->assertSee(['ใกล้หมด', 'อีกนาน', '2 วัน'])
            ->assertDontSee('หมดแล้ว');

        Livewire::test(PopularDownloads::class)
            ->assertSeeInOrder(['ไฟล์ฮิต', '50', 'ไฟล์รอง', '7'])
            ->assertDontSee('ไม่มีใครโหลด');

        $this->get('/backend')->assertOk();
    }

    public function test_dashboard_widgets_follow_dashboard_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/backend')->assertOk();
        $this->assertFalse(ContentStats::canView());
        $this->assertFalse(PopularDownloads::canView());

        $this->grant($user, 'dashboard');
        $this->assertTrue(ContentStats::canView());
        $this->assertTrue(ActivePopups::canView());

        Livewire::test(Dashboard::class)->assertOk();
    }
}
