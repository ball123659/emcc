<?php

namespace Tests\Feature;

use App\Filament\Resources\Popups\Pages\CreatePopup;
use App\Filament\Resources\Popups\Pages\ListPopups;
use App\Filament\Resources\Slides\Pages\CreateSlide;
use App\Filament\Resources\Slides\Pages\EditSlide;
use App\Filament\Resources\Slides\Pages\ListSlides;
use App\Models\Popup;
use App\Models\Slide;
use App\Models\User;
use App\Models\UserPagePermission;
use App\Services\Images\ImageProcessor;
use App\Services\Images\ImageRejectedException;
use App\Services\VirusScan\ScanResult;
use App\Services\VirusScan\VirusScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class HighlightTest extends TestCase
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

    private function slide(array $attributes = []): Slide
    {
        Storage::disk('local')->put('slides/11111111-1111-1111-1111-111111111111.webp', 'x');

        return Slide::create($attributes + [
            'title' => 'สไลด์',
            'image_th' => 'slides/11111111-1111-1111-1111-111111111111.webp',
            'alt_th' => 'รูป',
        ]);
    }

    // ---------- การประมวลผลรูป ----------

    public function test_image_is_resized_converted_to_webp_with_random_name(): void
    {
        $file = UploadedFile::fake()->image('ภาพ ทดสอบ.png', 3000, 1000);

        $stored = app(ImageProcessor::class)->store($file, 'news', withThumbnail: true);

        $this->assertMatchesRegularExpression('#^news/[0-9a-f\-]{36}\.webp$#', $stored->path);
        $this->assertSame([1920, 640], [$stored->width, $stored->height]);
        Storage::disk('local')->assertExists([$stored->path, $stored->thumbPath]);

        $binary = Storage::disk('local')->get($stored->path);
        $this->assertSame('image/webp', (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary));
        $this->assertSame(480, getimagesizefromstring(Storage::disk('local')->get($stored->thumbPath))[0]);
    }

    public function test_small_image_is_not_upscaled(): void
    {
        $stored = app(ImageProcessor::class)->store(UploadedFile::fake()->image('a.jpg', 800, 600), 'popups');

        $this->assertSame([800, 600], [$stored->width, $stored->height]);
    }

    public function test_fake_image_with_image_extension_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('evil.jpg', '<?php echo "x";');

        $this->expectException(ImageRejectedException::class);
        app(ImageProcessor::class)->store($file, 'slides');
    }

    public function test_infected_file_is_rejected(): void
    {
        $this->app->bind(VirusScanner::class, fn () => new class implements VirusScanner
        {
            public function scan(string $absolutePath): ScanResult
            {
                return ScanResult::Infected;
            }
        });

        try {
            app(ImageProcessor::class)->store(UploadedFile::fake()->image('a.jpg'), 'slides');
            $this->fail('ควรปฏิเสธไฟล์ที่มีไวรัส');
        } catch (ImageRejectedException $e) {
            $this->assertStringContainsString('ไวรัส', $e->getMessage());
        }

        $this->assertSame([], Storage::disk('local')->allFiles('slides'));
    }

    public function test_media_route_serves_only_processed_images(): void
    {
        $stored = app(ImageProcessor::class)->store(UploadedFile::fake()->image('a.jpg'), 'slides');

        $this->get('/media/'.$stored->path)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get('/media/slides/00000000-0000-0000-0000-000000000000.webp')->assertNotFound();
        $this->get('/media/../.env')->assertNotFound();
        $this->get('/media/users/secret.txt')->assertNotFound();
    }

    // ---------- Slide Show ----------

    public function test_create_slide_with_upload_and_url_link(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateSlide::class)
            ->fillForm([
                'title' => 'ลด PM2.5',
                'image_th' => UploadedFile::fake()->image('th.jpg', 2400, 750),
                'alt_th' => 'ภาพรณรงค์',
                'link_type' => Slide::LINK_URL,
                'link_url' => 'https://www.ieat.go.th',
                'news_id' => null,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $slide = Slide::firstOrFail();
        $this->assertStringEndsWith('.webp', $slide->image_th);
        Storage::disk('local')->assertExists($slide->image_th);
        $this->assertSame(1920, getimagesizefromstring(Storage::disk('local')->get($slide->image_th))[0]);
        $this->assertSame('https://www.ieat.go.th', $slide->link_url);
        $this->assertSame(1, $slide->sort_order);
        $this->assertNotNull($slide->created_by);
    }

    public function test_slide_validation(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateSlide::class)
            ->fillForm(['title' => '', 'alt_th' => '', 'link_type' => Slide::LINK_URL, 'link_url' => 'javascript:alert(1)'])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required', 'image_th' => 'required', 'alt_th' => 'required', 'link_url']);

        Livewire::test(CreateSlide::class)
            ->fillForm(['link_type' => Slide::LINK_URL, 'link_url' => 'ftp://example.com/file'])
            ->call('create')
            ->assertHasFormErrors(['link_url']);

        Livewire::test(CreateSlide::class)
            ->fillForm(['link_type' => Slide::LINK_NEWS, 'news_id' => null])
            ->call('create')
            ->assertHasFormErrors(['news_id' => 'required']);

        Livewire::test(CreateSlide::class)
            ->fillForm(['start_date' => '2026-12-10', 'end_date' => '2026-12-01'])
            ->call('create')
            ->assertHasFormErrors(['end_date' => 'after_or_equal']);
    }

    public function test_link_fields_are_cleared_when_type_changes(): void
    {
        $this->actingAs($this->admin());
        $slide = $this->slide(['link_type' => Slide::LINK_URL, 'link_url' => 'https://example.com']);

        Livewire::test(EditSlide::class, ['record' => $slide->id])
            ->fillForm(['link_type' => Slide::LINK_NONE])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($slide->refresh()->link_url);
    }

    public function test_replacing_image_deletes_old_file(): void
    {
        $slide = $this->slide();
        $old = $slide->image_th;
        $new = app(ImageProcessor::class)->store(UploadedFile::fake()->image('new.png', 500, 300), 'slides')->path;

        $slide->update(['image_th' => $new]);

        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($new);
    }

    public function test_soft_deleted_slide_keeps_file_and_can_be_restored(): void
    {
        $slide = $this->slide();
        $slide->delete();

        Storage::disk('local')->assertExists($slide->image_th);
        $this->assertSame(0, Slide::count());

        $slide->restore();
        $this->assertSame(1, Slide::count());
    }

    public function test_drag_reorder(): void
    {
        $this->actingAs($this->admin());
        $a = $this->slide(['title' => 'A']);
        $b = $this->slide(['title' => 'B']);
        $c = $this->slide(['title' => 'C']);

        Livewire::test(ListSlides::class)->call('reorderTable', [(string) $c->id, (string) $a->id, (string) $b->id]);

        $this->assertSame(['C', 'A', 'B'], Slide::visible()->pluck('title')->all());
    }

    // ---------- Popup ----------

    public function test_popup_requires_dates_and_valid_range(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreatePopup::class)
            ->fillForm(['title' => 'p', 'alt_th' => 'a', 'start_date' => null, 'end_date' => null])
            ->call('create')
            ->assertHasFormErrors(['start_date' => 'required', 'end_date' => 'required']);

        Livewire::test(CreatePopup::class)
            ->fillForm([
                'title' => 'p',
                'image_th' => UploadedFile::fake()->image('p.jpg', 800, 800),
                'alt_th' => 'a',
                'start_date' => today()->toDateString(),
                'end_date' => today()->addDays(7)->toDateString(),
                'link_url' => '',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $popup = Popup::firstOrFail();
        $this->assertNull($popup->link_url);
        $this->assertSame(Popup::STATUS_SHOWING, $popup->displayStatus());
    }

    public function test_popup_list_shows_status_badges(): void
    {
        $this->actingAs($this->admin());
        $base = ['image_th' => 'popups/x.webp', 'alt_th' => 'a'];
        Popup::create($base + ['title' => 'now', 'start_date' => today(), 'end_date' => today()->addDays(2)]);
        Popup::create($base + ['title' => 'later', 'start_date' => today()->addDay(), 'end_date' => today()->addDays(2)]);
        Popup::create($base + ['title' => 'old', 'start_date' => today()->subDays(5), 'end_date' => today()->subDay()]);
        Popup::create($base + ['title' => 'off', 'start_date' => today(), 'end_date' => today(), 'is_active' => false]);

        Livewire::test(ListPopups::class)
            ->assertSee(['กำลังแสดง', 'รอแสดง', 'หมดอายุ', 'ปิด', 'อีก 2 วันหมดอายุ']);
    }

    public function test_frontend_popup_component_renders_visible_popups_in_order(): void
    {
        $base = ['image_th' => 'popups/aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa.webp', 'start_date' => today(), 'end_date' => today()];
        Popup::create($base + ['title' => 'second', 'alt_th' => 'ป๊อปอัปสอง', 'sort_order' => 2]);
        Popup::create($base + ['title' => 'first', 'alt_th' => 'ป๊อปอัปหนึ่ง', 'sort_order' => 1, 'link_url' => 'https://example.com']);
        Popup::create($base + ['title' => 'off', 'alt_th' => 'ไม่แสดง', 'is_active' => false]);

        $html = Blade::render('<x-site.popups />');

        $this->assertStringContainsString('ไม่ต้องแสดงอีกวันนี้', $html);
        $this->assertStringNotContainsString('ไม่แสดง"', $html);
        $this->assertLessThan(strpos($html, 'ป๊อปอัปสอง'), strpos($html, 'ป๊อปอัปหนึ่ง'));
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);

        Popup::query()->update(['is_active' => false]);
        $this->assertSame('', trim(Blade::render('<x-site.popups />')));
    }

    // ---------- สิทธิ์ ----------

    public function test_page_permissions_are_enforced_per_menu(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/backend/slides')->assertForbidden();
        $this->get('/backend/popups')->assertForbidden();

        UserPagePermission::where('user_id', $user->id)
            ->whereIn('page_id', fn ($q) => $q->select('id')->from('pages')->where('code', 'slides'))
            ->update(['can_view' => true]);
        $user->forgetPagePermissions(); // ในระบบจริงแต่ละคำขอโหลดผู้ใช้ใหม่

        $this->get('/backend/slides')->assertOk();
        $this->get('/backend/slides/create')->assertOk();
        $this->get('/backend/popups')->assertForbidden();
        $this->assertFalse($user->can('create', Popup::class));
    }
}
