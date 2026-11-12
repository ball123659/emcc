<?php

namespace Tests\Feature;

use App\Filament\Auth\EditProfile;
use App\Filament\Resources\News\Pages\CreateNews;
use App\Filament\Resources\NewsCategories\Pages\ManageNewsCategories;
use App\Filament\Resources\Popups\Pages\ListPopups;
use App\Filament\Resources\Slides\Pages\CreateSlide;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\ActivityLog;
use App\Models\Download;
use App\Models\DownloadCategory;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Popup;
use App\Models\Slide;
use App\Models\User;
use App\Models\UserPagePermission;
use App\Providers\AppServiceProvider;
use App\Services\Files\DocumentStorer;
use App\Services\Files\FileRejectedException;
use App\Services\Images\ImageProcessor;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * แก้จาก audit รอบ 2 (ข้อ 1–14, 16 และ R2–R8)
 */
class AuditRound2FixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::factory()->administrator()->create();
    }

    // ---------- 1: เปลี่ยน username ตัวเองในโปรไฟล์ ----------

    public function test_1_admins_can_change_own_username_in_profile_but_users_cannot(): void
    {
        $dev = User::where('username', 'developer')->first();
        $dev->forceFill(['must_change_password' => false])->save();
        $this->actingAs($dev);

        Livewire::test(EditProfile::class)->set('data.username', ' Dev.Lead ')->call('save')->assertHasNoErrors();
        $this->assertSame('dev.lead', $dev->fresh()->username);

        $user = User::factory()->create(['username' => 'staff']);
        $this->actingAs($user);
        $this->get('/backend/profile')->assertSee('ต้องการเปลี่ยนชื่อผู้ใช้ กรุณาติดต่อผู้ดูแลระบบ');
        Livewire::test(EditProfile::class)->set('data.username', 'hacker')->call('save');
        $this->assertSame('staff', $user->fresh()->username);
    }

    // ---------- 2: ปิดบัญชีระหว่างใช้งาน ----------

    public function test_2_deactivated_user_is_logged_out_with_message(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get('/backend')->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->get('/backend')->assertRedirect('/backend/login');
        $this->assertGuest();
        Notification::assertNotified('บัญชีของคุณถูกปิดการใช้งาน');
    }

    // ---------- 3 + R7-1: ลากเรียงในแท็บ ----------

    public function test_3_reorder_inside_tab_keeps_unique_order_and_is_logged(): void
    {
        $this->actingAs($this->admin());
        $base = ['image_th' => 'popups/x.webp', 'alt_th' => 'a'];
        $a = Popup::create($base + ['title' => 'A', 'start_date' => today(), 'end_date' => today()->addDay()]);
        $x = Popup::create($base + ['title' => 'X', 'start_date' => today()->addDays(2), 'end_date' => today()->addDays(5)]);
        $c = Popup::create($base + ['title' => 'C', 'start_date' => today(), 'end_date' => today()->addDay()]);

        Livewire::test(ListPopups::class)->set('activeTab', 'showing')->call('reorderTable', [(string) $c->id, (string) $a->id]);

        $order = Popup::orderBy('sort_order')->pluck('sort_order', 'title')->all();
        $this->assertSame(['C' => 1, 'X' => 2, 'A' => 3], $order, 'C กับ A สลับกันในตำแหน่งเดิม X คงตำแหน่ง เลขไม่ซ้ำ');
        $this->assertTrue(ActivityLog::where('action', 'reorder')->where('subject_type', 'popups')->exists());
    }

    // ---------- 4 / R8-1: proxy ----------

    public function test_4_and_r8_trusted_proxies_from_config_and_no_redirect_loop(): void
    {
        $this->app['env'] = 'production';

        // ยังไม่ตั้ง TRUSTED_PROXIES แต่ proxy บอกว่าเป็น https -> ไม่ redirect (กันวนไม่จบ)
        $this->withHeaders(['X-Forwarded-Proto' => 'https'])->get('http://emcc.example/backend/login')->assertOk();
        // http ตรง ๆ -> redirect ตามปกติ
        $this->flushHeaders()->get('http://emcc.example/backend/login')->assertRedirect('https://emcc.example/backend/login');

        // ตั้ง TRUSTED_PROXIES ผ่าน config (เหมือนใส่ใน .env) -> ระบบรู้ว่าเป็น https
        config(['emcc.trusted_proxies' => '10.0.0.5']);
        $this->app->getProvider(AppServiceProvider::class)->boot();
        $response = $this->flushHeaders()->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('http://emcc.example/backend/login');
        $response->assertOk()->assertHeader('Strict-Transport-Security');

        TrustProxies::flushState();
    }

    // ---------- 5 / 6 / 9 / 10 ----------

    public function test_5_6_9_10_config_and_public_pages(): void
    {
        // 5: cookie Secure อัตโนมัติในโปรดักชัน (ไม่ต้องตั้ง SESSION_SECURE_COOKIE)
        $original = getenv('APP_ENV');
        putenv('APP_ENV=production');
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';
        $secure = (require config_path('session.php'))['secure'];
        putenv("APP_ENV={$original}");
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = $original;
        $this->assertTrue($secure);

        // 6: หลังบ้านไม่ติดผลค้นหา
        $this->get('/backend/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/')->assertHeaderMissing('X-Robots-Tag');
        $this->assertStringContainsString('Disallow: /backend', file_get_contents(public_path('robots.txt')));

        // 9: หน้าแรกชั่วคราว ไม่ใช่หน้า Laravel
        $this->get('/')->assertOk()->assertSee('เว็บไซต์กำลังปรับปรุง')->assertDontSee('laravel.com');

        // 10: log รายวัน เก็บ 30 วัน
        $this->assertStringContainsString("env('LOG_STACK', 'daily')", file_get_contents(config_path('logging.php')));
        $this->assertStringContainsString('LOG_STACK=daily', file_get_contents(base_path('.env.example')));
        $this->assertSame(30, (int) config('logging.channels.daily.max_files'));
    }

    // ---------- 7: รูปในเนื้อหาข่าว ----------

    public function test_7_pasted_image_warns_user(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateNews::class)
            ->assertSee('รูปที่วางหรือลากลงในเนื้อหาจะถูกนำออกตอนบันทึก')
            ->fillForm([
                'title_th' => 'ข่าว', 'content_th' => '<p>ข้อความ<img src="data:image/png;base64,AAAA"></p>',
                'cover_image' => UploadedFile::fake()->image('c.jpg', 800, 450), 'category_id' => NewsCategory::first()->id,
            ])
            ->call('create')
            ->assertNotified('รูปในเนื้อหาข่าวถูกนำออก');

        $this->assertStringNotContainsString('<img', News::first()->content_th);
    }

    // ---------- 8 / 12: slug ----------

    public function test_8_slug_stays_locked_after_hiding(): void
    {
        $news = News::create(['category_id' => NewsCategory::first()->id, 'title_th' => 'ข่าว', 'title_en' => 'Shared link',
            'content_th' => '<p>x</p>', 'cover_image' => 'news/x.webp', 'status' => 'published', 'published_at' => today()]);
        $original = $news->slug;

        $news->update(['status' => 'hidden']);
        $news->update(['slug' => 'renamed']);

        $this->assertSame($original, $news->fresh()->slug);
    }

    public function test_12_create_log_has_final_slug(): void
    {
        $news = News::create(['category_id' => NewsCategory::first()->id, 'title_th' => 'ข่าวภาษาไทยล้วน',
            'content_th' => '<p>x</p>', 'cover_image' => 'news/x.webp', 'published_at' => '2026-11-20']);

        $log = ActivityLog::where('subject_type', 'news')->where('subject_id', $news->id)->where('action', 'create')->first();
        $this->assertSame("2026-11-{$news->id}", $log->changes['new']['slug']);
    }

    // ---------- 11: แก้อีเมลตัวเองในหน้า Users ----------

    public function test_11_changing_own_email_on_users_page_requires_password(): void
    {
        $admin = User::factory()->administrator()->create(['password' => 'secret123']);
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $admin->id])
            ->fillForm(['email' => 'new@example.com'])->call('save')
            ->assertHasFormErrors(['current_password' => 'required']);

        Livewire::test(EditUser::class, ['record' => $admin->id])
            ->fillForm(['email' => 'new@example.com', 'current_password' => 'secret123'])->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('new@example.com', $admin->fresh()->email);

        // แก้อีเมลของคนอื่นไม่ต้องใส่รหัส
        $other = User::factory()->create();
        Livewire::test(EditUser::class, ['record' => $other->id])
            ->fillForm(['email' => 'other2@example.com'])->call('save')->assertHasNoFormErrors();
    }

    // ---------- 14: โปรดักชันไม่มีตัวสแกน ----------

    public function test_14_production_without_scanner_rejects_uploads(): void
    {
        $this->app['env'] = 'production';

        try {
            app(ImageProcessor::class)->store(UploadedFile::fake()->image('a.jpg'), 'slides');
            $this->fail('ควรปฏิเสธ');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('ยังไม่ได้ตั้งค่าโปรแกรมสแกนไวรัส', $e->getMessage());
        }

        $this->expectException(FileRejectedException::class);
        app(DocumentStorer::class)->store(UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n%%EOF"), 'downloads');
    }

    // ---------- R2-1 / R2-2: trim ----------

    public function test_r2_fields_are_trimmed_and_urls_with_spaces_accepted(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateNews::class)
            ->fillForm(['title_th' => '  หัวข้อ  ', 'excerpt_th' => '  โปรย ', 'content_th' => '<p>x</p>', 'cover_alt_th' => '   ',
                'cover_image' => UploadedFile::fake()->image('c.jpg', 800, 450), 'category_id' => NewsCategory::first()->id])
            ->call('create')->assertHasNoFormErrors();
        $news = News::first();
        $this->assertSame(['หัวข้อ', 'โปรย', null], [$news->title_th, $news->excerpt_th, $news->cover_alt_th]);

        Livewire::test(CreateSlide::class)
            ->fillForm(['title' => ' s ', 'image_th' => UploadedFile::fake()->image('a.jpg', 800, 300), 'alt_th' => ' a ',
                'link_type' => 'url', 'link_url' => '  https://ieat.go.th  '])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame(['s', 'a', 'https://ieat.go.th'], array_values(Slide::first()->only(['title', 'alt_th', 'link_url'])));

        Livewire::test(ManageNewsCategories::class)->callAction(CreateAction::class, ['name_th' => '  หมวดใหม่  ', 'name_en' => ' New ']);
        $this->assertSame(['หมวดใหม่', 'New'], array_values(NewsCategory::latest('id')->first()->only(['name_th', 'name_en'])));

        // ช่องรหัสผ่านไม่ถูก trim
        $user = User::factory()->create(['password' => 'oldpass1']);
        $this->actingAs($user);
        Livewire::test(EditProfile::class)
            ->set('data.password', ' spaced ')->set('data.passwordConfirmation', ' spaced ')->set('data.currentPassword', 'oldpass1')
            ->call('save')->assertHasNoErrors();
        $this->assertTrue(Hash::check(' spaced ', $user->fresh()->password));
    }

    // ---------- R2-3: log แกลเลอรี/ไฟล์แนบ ----------

    public function test_r2_gallery_and_attachments_are_logged(): void
    {
        $news = News::create(['category_id' => NewsCategory::first()->id, 'title_th' => 'x', 'content_th' => '<p>x</p>', 'cover_image' => 'news/x.webp']);
        $path = app(ImageProcessor::class)->store(UploadedFile::fake()->image('g.jpg'), 'news', true)->path;
        $image = $news->images()->create(['image_path' => $path, 'caption_th' => 'บรรยาย', 'sort_order' => 1]);
        $image->delete();

        $this->assertSame(['create', 'delete'], ActivityLog::where('subject_type', 'news_images')->orderBy('id')->pluck('action')->all());
    }

    // ---------- R2-4: query สิทธิ์ ----------

    public function test_r2_permission_checks_are_cached_per_request(): void
    {
        $user = User::factory()->create();
        UserPagePermission::where('user_id', $user->id)->update(['can_view' => 1]);
        $this->actingAs($user->fresh());

        DB::enableQueryLog();
        $this->get('/backend/news')->assertOk();
        $permQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'user_page_permissions'))->count();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(2, $permQueries);
    }

    // ---------- R2-5: ข้อความไทยสำหรับโปรแกรมอ่านหน้าจอ ----------

    public function test_r2_screen_reader_texts_are_thai(): void
    {
        $this->actingAs($this->admin());

        $this->get('/backend')->assertSee('ข้ามไปยังเนื้อหา')->assertDontSee('Skip to content');
        $this->assertSame('กำลังบันทึก...', __('filament-tables::table.columns.editable.saving'));
    }

    // ---------- R4: ข้อมูลกรณีขอบ ----------

    public function test_r4_long_names(): void
    {
        $name = str_repeat('a', 100);
        NewsCategory::create(['name_th' => 'ก', 'name_en' => $name]);
        $second = NewsCategory::create(['name_th' => 'ข', 'name_en' => $name]);
        $this->assertLessThanOrEqual(100, strlen($second->slug));
        $this->assertStringEndsWith('-2', $second->slug);

        $stored = app(DocumentStorer::class)->store(
            UploadedFile::fake()->createWithContent('รายงาน'.str_repeat('ก', 250).'.pdf', "%PDF-1.4\n%%EOF"), 'downloads');
        $this->assertStringEndsWith('.pdf', $stored->originalName);
        $this->assertLessThanOrEqual(200, mb_strlen($stored->originalName));
    }

    // ---------- R5 / R6: endpoint สาธารณะ ----------

    public function test_r5_download_counting_ignores_head_bots_and_repeats(): void
    {
        $download = Download::create(['category_id' => DownloadCategory::first()->id, 'title_th' => 'x', 'type' => 'link', 'external_url' => 'https://a.example']);
        $browser = ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130'];

        $this->call('HEAD', $download->publicUrl());
        $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])->get($download->publicUrl());
        $this->assertSame(0, $download->fresh()->download_count);

        $this->withHeaders($browser)->get($download->publicUrl());
        $this->flushSession();
        $this->withHeaders($browser)->get($download->publicUrl()); // ไม่มี cookie แต่ IP เดิม
        $this->assertSame(1, $download->fresh()->download_count);
    }

    public function test_r5_r6_bad_public_urls_are_404(): void
    {
        foreach (['/downloads/99999999999999999999', '/downloads/0', '/news/attachments/99999999999999999999',
            '/filament/exports/1/download', '/filament/imports/1/failed-rows/download'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }
}
