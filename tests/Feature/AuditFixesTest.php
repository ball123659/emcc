<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Downloads\Pages\EditDownload;
use App\Filament\Resources\News\NewsResource;
use App\Filament\Resources\News\Pages\EditNews;
use App\Filament\Resources\Slides\Pages\EditSlide;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Widgets\ActivePopups;
use App\Models\ActivityLog;
use App\Models\Download;
use App\Models\DownloadCategory;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Popup;
use App\Models\Slide;
use App\Models\User;
use App\Services\Html\HtmlSanitizer;
use App\Services\Images\ImageProcessor;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AuditFixesTest extends TestCase
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

    private function cover(): string
    {
        return app(ImageProcessor::class)->store(UploadedFile::fake()->image('c.jpg', 800, 450), 'news', withThumbnail: true)->path;
    }

    private function news(array $attributes = []): News
    {
        return News::create($attributes + [
            'category_id' => NewsCategory::first()->id,
            'title_th' => 'ข่าว',
            'title_en' => 'News item',
            'content_th' => '<p>x</p>',
            'cover_image' => $this->cover(),
        ]);
    }

    private function attempt(string $username, string $password)
    {
        return Livewire::test(Login::class)->set('data.username', $username)->set('data.password', $password)->call('authenticate');
    }

    // ---------- B1–B3 ----------

    public function test_b1_records_in_inactive_category_can_still_be_saved(): void
    {
        $this->actingAs($this->admin());
        $news = $this->news();
        $download = Download::create(['category_id' => DownloadCategory::first()->id, 'title_th' => 'd', 'type' => 'link', 'external_url' => 'https://a.example']);
        NewsCategory::query()->update(['is_active' => false]);
        DownloadCategory::query()->update(['is_active' => false]);

        Livewire::test(EditNews::class, ['record' => $news->getRouteKey()])
            ->assertSee('(ปิดใช้งาน)')
            ->fillForm(['title_th' => 'แก้แล้ว'])->call('save')->assertHasNoFormErrors();
        Livewire::test(EditDownload::class, ['record' => $download->id])
            ->fillForm(['title_th' => 'แก้แล้ว'])->call('save')->assertHasNoFormErrors();

        $this->assertSame('แก้แล้ว', $news->refresh()->title_th);
        $this->assertSame('แก้แล้ว', $download->refresh()->title_th);
    }

    public function test_b2_slide_linked_to_old_news_can_be_saved_but_only_published_news_selectable(): void
    {
        $this->actingAs($this->admin());
        $old = $this->news(['status' => 'published', 'published_at' => now()->subYears(2)]);
        $cover = $old->cover_image;
        $category = NewsCategory::first()->id;
        News::withoutEvents(function () use ($category, $cover) {
            for ($i = 0; $i < 60; $i++) {
                News::create(['category_id' => $category, 'slug' => "n{$i}", 'title_th' => "ข่าว {$i}", 'content_th' => '<p>x</p>', 'cover_image' => $cover, 'status' => 'published', 'published_at' => now()->subDay()]);
            }
        });
        $draft = $this->news(['status' => 'draft']);
        Storage::disk('local')->put('slides/11111111-1111-1111-1111-111111111111.webp', 'x');
        $slide = Slide::create(['title' => 's', 'image_th' => 'slides/11111111-1111-1111-1111-111111111111.webp', 'alt_th' => 'a', 'link_type' => 'news', 'news_id' => $old->id]);

        Livewire::test(EditSlide::class, ['record' => $slide->id])
            ->fillForm(['title' => 's2'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('s2', $slide->refresh()->title);

        Livewire::test(EditSlide::class, ['record' => $slide->id])
            ->fillForm(['news_id' => $draft->id])->call('save')->assertHasFormErrors(['news_id']);
    }

    public function test_b3_duplicate_category_names_get_unique_slugs(): void
    {
        $a = NewsCategory::create(['name_th' => 'ก', 'name_en' => 'Research']);
        $b = NewsCategory::create(['name_th' => 'ข', 'name_en' => 'Research']);
        $c = DownloadCategory::create(['name_th' => 'ค', 'name_en' => 'Documents']);

        $this->assertSame('research', $a->slug);
        $this->assertSame('research-2', $b->slug);
        $this->assertSame('documents-2', $c->slug);
    }

    // ---------- B4 / B5 / U5 ----------

    public function test_b4_livewire_temporary_upload_allows_20mb(): void
    {
        $this->assertContains('max:20480', config('livewire.temporary_file_upload.rules'));
    }

    public function test_b5_memory_limit_is_raised_for_large_images(): void
    {
        $original = ini_get('memory_limit');
        ini_set('memory_limit', '128M');

        try {
            $stored = app(ImageProcessor::class)->store(UploadedFile::fake()->image('big.jpg', 6000, 4000), 'news');
            $this->assertSame(1920, $stored->width);
            $this->assertGreaterThan(128 * 1024 * 1024, ImageProcessor::iniBytes((string) ini_get('memory_limit')));
        } finally {
            ini_set('memory_limit', $original);
        }
    }

    public function test_u5_exif_orientation_is_applied(): void
    {
        $image = imagecreatetruecolor(200, 100);
        ob_start();
        imagejpeg($image);
        $jpeg = ob_get_clean();

        // APP1/EXIF: Orientation = 6 (หมุน 90° ตามเข็ม)
        $tiff = 'MM'.pack('n', 42).pack('N', 8).pack('n', 1).pack('nnNnn', 0x0112, 3, 1, 6, 0).pack('N', 0);
        $app1 = "\xFF\xE1".pack('n', strlen($tiff) + 8)."Exif\0\0".$tiff;
        $file = UploadedFile::fake()->createWithContent('rotated.jpg', substr($jpeg, 0, 2).$app1.substr($jpeg, 2));

        $stored = app(ImageProcessor::class)->store($file, 'news');

        $this->assertSame([100, 200], [$stored->width, $stored->height]);
    }

    // ---------- ล็อกอิน: B6 / B7 / S5 / S7 ----------

    public function test_b6_inactive_account_with_correct_password_is_not_counted_as_wrong(): void
    {
        $user = User::factory()->inactive()->create(['username' => 'staff', 'password' => 'secret123']);

        $this->attempt('staff', 'secret123')->assertHasErrors(['data.username']);
        $this->assertSame(0, $user->refresh()->failed_login_attempts);
        $this->assertTrue(ActivityLog::where('description', 'บัญชีถูกปิดการใช้งาน')->exists());
        $this->assertGuest();
    }

    public function test_b7_failed_attempts_reset_after_lockout_window(): void
    {
        $user = User::factory()->create(['username' => 'staff', 'password' => 'secret123']);

        for ($i = 0; $i < 4; $i++) {
            $this->attempt('staff', 'wrong');
        }
        $this->assertSame(4, $user->refresh()->failed_login_attempts);

        $this->travel(20)->minutes();
        $this->attempt('staff', 'wrong');

        $user->refresh();
        $this->assertSame(1, $user->failed_login_attempts);
        $this->assertFalse($user->isLocked());
    }

    public function test_s5_unknown_username_is_masked_in_log(): void
    {
        $this->attempt('MySecretPassword99', 'x');

        $log = ActivityLog::where('action', 'login_failed')->latest('id')->firstOrFail();
        $this->assertStringNotContainsString('mysecretpassword99', mb_strtolower($log->description));
        $this->assertStringContainsString('my***', $log->description);
    }

    public function test_s7_unknown_username_gets_same_lock_message(): void
    {
        User::factory()->create(['username' => 'realuser', 'password' => 'secret123']);

        for ($i = 0; $i < 5; $i++) {
            $this->attempt('realuser', 'wrong');
            $this->attempt('ghostuser', 'wrong');
            $this->travel(40)->seconds(); // หลบตัวจำกัด 5 ครั้ง/นาที/IP ของ Filament
        }

        $real = $this->attempt('realuser', 'wrong')->errors()->first('data.username');
        $ghost = $this->attempt('ghostuser', 'wrong')->errors()->first('data.username');

        $this->assertStringContainsString('ถูกล็อกชั่วคราว', $real);
        $this->assertSame(preg_replace('/\d+ นาที/', 'N นาที', $real), preg_replace('/\d+ นาที/', 'N นาที', $ghost));
    }

    // ---------- S1–S3 / S8 ----------

    public function test_s1_security_headers(): void
    {
        $response = $this->get('/backend/login');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('X-Powered-By');
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_s2_production_redirects_to_https(): void
    {
        $this->app['env'] = 'production';

        $this->get('http://emcc.example/backend/login')->assertRedirect('https://emcc.example/backend/login');
        $this->get('https://emcc.example/backend/login')->assertOk()->assertHeader('Strict-Transport-Security');
    }

    public function test_s3_remember_me_lasts_30_days(): void
    {
        $this->assertSame(43200, config('auth.guards.web.remember'));
    }

    public function test_s8_pages_and_search_blocked_until_password_changed(): void
    {
        $this->actingAs(User::where('username', 'developer')->first());

        $this->assertFalse(NewsResource::canAccess());
        $this->assertFalse(NewsResource::canGloballySearch());
    }

    // ---------- U ----------

    public function test_u1_thai_messages(): void
    {
        $this->assertStringStartsWith('ไฟล์ในช่อง', __('filament-forms::validation.tampered_file_path', ['attribute' => 'รูป']));

        $this->actingAs($this->admin());
        $this->get('/backend/slides/create')->assertSee('labelFileProcessingComplete', false)->assertSee('อัปโหลดเสร็จแล้ว');
    }

    public function test_u2_internal_links_are_kept(): void
    {
        $this->assertStringContainsString('href="/downloads/5"', HtmlSanitizer::clean('<a href="/downloads/5">x</a>'));
        $this->assertStringNotContainsString('javascript', HtmlSanitizer::clean('<a href="javascript:alert(1)">x</a>'));
    }

    public function test_u3_monitoring_placeholder_page(): void
    {
        $this->actingAs($this->admin());
        $this->get('/backend/monitoring')->assertOk()->assertSee('อยู่ระหว่างพัฒนา');

        $this->actingAs(User::factory()->create());
        $this->get('/backend/monitoring')->assertForbidden();
    }

    public function test_u4_dashboard_popups_only_currently_showing(): void
    {
        $this->actingAs($this->admin());
        $base = ['image_th' => 'popups/x.webp', 'alt_th' => 'a'];
        Popup::create($base + ['title' => 'แสดงอยู่', 'start_date' => today(), 'end_date' => today()->addDays(3)]);
        Popup::create($base + ['title' => 'รอแสดงพรุ่งนี้', 'start_date' => today()->addDay(), 'end_date' => today()->addDays(3)]);

        Livewire::test(ActivePopups::class)->assertSee('แสดงอยู่')->assertDontSee('รอแสดงพรุ่งนี้');
    }

    public function test_u6_thai_only_slug_uses_news_id_and_locks_after_publish(): void
    {
        $news = $this->news(['title_en' => null, 'title_th' => 'ข่าวภาษาไทยล้วน', 'published_at' => '2026-11-20']);
        $this->assertSame("2026-11-{$news->id}", $news->refresh()->slug);

        $published = $this->news(['title_en' => 'Published news', 'status' => 'published', 'published_at' => today()]);
        $original = $published->slug;
        $published->update(['slug' => 'changed-slug']);
        $this->assertSame($original, $published->refresh()->slug);

        $draft = $this->news(['title_en' => 'Draft news']);
        $draft->update(['slug' => 'changed-slug']);
        $this->assertSame('changed-slug', $draft->refresh()->slug);
    }

    public function test_u8_thai_error_pages(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('ไม่พบหน้าที่ต้องการ');

        $this->actingAs(User::factory()->create());
        $this->get('/backend/users')->assertForbidden()->assertSee('ไม่มีสิทธิ์เข้าถึงหน้านี้');
    }

    public function test_u9_user_list_links_to_activity_log(): void
    {
        $this->actingAs($this->admin());
        $user = User::factory()->create();

        Livewire::test(ListUsers::class)
            ->assertActionHasUrl(
                TestAction::make('activity')->table($user),
                ActivityLogResource::getUrl('index', ['filters' => ['user_id' => ['value' => $user->id]]]),
            );
        $this->assertStringContainsString('filters%5Buser_id%5D%5Bvalue%5D='.$user->id, ActivityLogResource::getUrl('index', ['filters' => ['user_id' => ['value' => $user->id]]]));
    }

    public function test_u10_dates_use_one_format(): void
    {
        $this->actingAs($this->admin());
        $this->travelTo(now()->setDate(2026, 11, 25)->setTime(14, 30));
        $this->news(['status' => 'published', 'published_at' => today()]);

        $this->get('/backend/news')->assertSee('25/11/2026');
        ActivityLog::create(['action' => 'login', 'description' => 'ทดสอบ']);
        $this->get('/backend/activity-logs')->assertSee('25/11/2026 14:30')->assertDontSee('Nov 25, 2026');
    }

    // ---------- O1 ----------

    public function test_o1_old_activity_logs_are_pruned(): void
    {
        ActivityLog::query()->delete();
        ActivityLog::create(['action' => 'login'])->forceFill(['created_at' => now()->subDays(800)])->save();
        ActivityLog::create(['action' => 'login'])->forceFill(['created_at' => now()->subDays(10)])->save();

        Artisan::call('model:prune', ['--model' => [ActivityLog::class]]);

        $this->assertSame(1, ActivityLog::count());
    }
}
