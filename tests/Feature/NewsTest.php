<?php

namespace Tests\Feature;

use App\Filament\Resources\News\Pages\CreateNews;
use App\Filament\Resources\News\Pages\EditNews;
use App\Filament\Resources\News\Pages\ListNews;
use App\Filament\Resources\NewsCategories\Pages\ManageNewsCategories;
use App\Models\ActivityLog;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\NewsImage;
use App\Models\User;
use App\Services\Files\DocumentStorer;
use App\Services\Files\FileRejectedException;
use App\Services\Html\HtmlSanitizer;
use App\Services\Images\ImageProcessor;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class NewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    private function cover(): string
    {
        return app(ImageProcessor::class)->store(UploadedFile::fake()->image('c.jpg', 1600, 900), 'news', withThumbnail: true)->path;
    }

    private function news(array $attributes = []): News
    {
        return News::create($attributes + [
            'category_id' => NewsCategory::first()->id,
            'title_th' => 'ข่าวทดสอบ',
            'title_en' => 'Test news',
            'content_th' => '<p>เนื้อหา</p>',
            'cover_image' => $this->cover(),
        ]);
    }

    // ---------- HTML / XSS ----------

    public function test_rich_text_is_sanitized_on_save(): void
    {
        $news = $this->news([
            'content_th' => '<p onclick="x()">ข่าว<script>alert(1)</script><img src=x onerror=alert(1)>'
                .'<a href="javascript:alert(1)">bad</a><a href="https://ieat.go.th" target="_blank">ok</a></p>',
            'content_en' => '<p></p>',
        ]);

        $html = $news->refresh()->content_th;
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('href="https://ieat.go.th"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertNull($news->content_en, 'เนื้อหาอังกฤษว่าง -> null (ใช้ภาษาไทยแทน)');
    }

    public function test_sanitizer_keeps_editor_formatting(): void
    {
        $html = '<h2>หัวข้อ</h2><p><strong>หนา</strong> <em>เอียง</em> <u>ขีด</u></p><ul><li>a</li></ul><blockquote>q</blockquote>'
            .'<table><tbody><tr><td colspan="2">c</td></tr></tbody></table>';

        $this->assertSame($html, HtmlSanitizer::clean($html));
    }

    // ---------- slug / สถานะ ----------

    public function test_slug_is_generated_and_unique(): void
    {
        $a = $this->news(['title_en' => 'PM2.5 Report', 'published_at' => '2026-11-05 10:00']);
        $b = $this->news(['title_en' => 'PM2.5 Report', 'published_at' => '2026-11-06 10:00']);
        $c = $this->news(['title_en' => null, 'title_th' => 'ข่าวภาษาไทยล้วน']);

        $this->assertSame('2026-11-pm25-report', $a->slug);
        $this->assertSame('2026-11-pm25-report-2', $b->slug);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-[a-z0-9-]+$/', $c->slug);
    }

    public function test_publish_status_and_schedule(): void
    {
        $now = $this->news(['status' => News::STATUS_PUBLISHED]);
        $this->assertNotNull($now->published_at, 'เผยแพร่โดยไม่ระบุวัน = วันนี้');
        $this->assertSame(News::STATUS_PUBLISHED, $now->displayStatus());

        $later = $this->news(['status' => News::STATUS_PUBLISHED, 'published_at' => now()->addDay()]);
        $this->assertSame(News::DISPLAY_SCHEDULED, $later->displayStatus());

        $this->news(['status' => News::STATUS_DRAFT]);
        $this->news(['status' => News::STATUS_HIDDEN, 'published_at' => now()->subDay()]);

        $this->assertSame([$now->id], News::published()->pluck('id')->all());

        $this->travel(2)->days();
        $this->assertEqualsCanonicalizing([$now->id, $later->id], News::published()->pluck('id')->all());
    }

    public function test_publish_date_is_day_level(): void
    {
        $this->travelTo(now()->setTime(14, 35));

        $today = $this->news(['status' => News::STATUS_PUBLISHED]);
        $this->assertSame(today()->format('Y-m-d H:i:s'), $today->published_at->format('Y-m-d H:i:s'));

        $withTime = $this->news(['status' => News::STATUS_PUBLISHED, 'published_at' => '2026-12-01 15:45']);
        $this->assertSame('2026-12-01 00:00:00', $withTime->published_at->format('Y-m-d H:i:s'));

        // ตั้งเผยแพร่พรุ่งนี้ -> ขึ้นหน้าเว็บตอน 00:00 ของวันพรุ่งนี้
        $tomorrow = $this->news(['status' => News::STATUS_PUBLISHED, 'published_at' => today()->addDay()->toDateString()]);
        $this->assertFalse($tomorrow->isPubliclyVisible());
        $this->travelTo(today()->addDay()->setTime(0, 0, 1));
        $this->assertTrue($tomorrow->fresh()->isPubliclyVisible());
    }

    public function test_publish_date_field_is_date_only_in_form(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $news = $this->news(['status' => News::STATUS_PUBLISHED, 'published_at' => '2026-11-20']);

        Livewire::test(EditNews::class, ['record' => $news->getRouteKey()])
            ->assertSchemaStateSet(['published_at' => '2026-11-20'])
            ->fillForm(['published_at' => '2026-11-25'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('2026-11-25 00:00:00', $news->refresh()->published_at->format('Y-m-d H:i:s'));

        Livewire::test(ListNews::class)->assertSee('25/11/2026')->assertDontSee('25/11/2026 00:00');
    }

    public function test_pinned_news_comes_first_and_view_count(): void
    {
        $old = $this->news(['status' => 'published', 'published_at' => now()->subDays(5), 'is_pinned' => true]);
        $new = $this->news(['status' => 'published', 'published_at' => now()->subDay()]);

        $this->assertSame([$old->id, $new->id], News::published()->pluck('id')->all());

        $updatedAt = $new->updated_at;
        $new->recordView();
        $new->recordView();
        $this->assertSame(2, $new->refresh()->view_count);
        $this->assertEquals($updatedAt, $new->updated_at);
    }

    // ---------- ฟอร์มหลังบ้าน ----------

    public function test_create_news_from_form(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        Livewire::test(CreateNews::class)
            ->fillForm([
                'title_th' => 'ตรวจวัดคุณภาพอากาศ',
                'title_en' => 'Air Quality Monitoring',
                'excerpt_th' => 'คำโปรย',
                'content_th' => '<p>เนื้อหา <script>x</script></p>',
                'cover_image' => UploadedFile::fake()->image('cover.jpg', 2400, 1350),
                'category_id' => NewsCategory::first()->id,
                'status' => News::STATUS_PUBLISHED,
                'is_pinned' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $news = News::firstOrFail();
        $this->assertStringStartsWith(now()->format('Y-m').'-air-quality-monitoring', $news->slug);
        $this->assertStringNotContainsString('script', $news->content_th);
        $this->assertNotNull($news->published_at);
        Storage::disk('local')->assertExists([$news->cover_image, $news->cover_thumb]);
        $this->assertSame(1920, getimagesizefromstring(Storage::disk('local')->get($news->cover_image))[0]);
        $this->assertTrue(ActivityLog::where('action', 'create')->where('subject_type', 'news')->exists());
    }

    public function test_news_form_validation(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $this->news(['slug' => 'taken-slug']);

        Livewire::test(CreateNews::class)
            ->fillForm(['title_th' => '', 'content_th' => '', 'category_id' => null, 'slug' => 'ภาษาไทย'])
            ->call('create')
            ->assertHasFormErrors(['title_th' => 'required', 'content_th', 'cover_image' => 'required', 'category_id' => 'required', 'slug' => 'regex']);

        Livewire::test(CreateNews::class)
            ->fillForm(['slug' => 'taken-slug'])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_gallery_images_get_thumbnail_and_size_and_file_is_deleted_with_image(): void
    {
        $news = $this->news();
        $path = app(ImageProcessor::class)->store(UploadedFile::fake()->image('g.png', 3000, 2000), 'news', withThumbnail: true)->path;

        $image = $news->images()->create(['image_path' => $path, 'caption_th' => 'บรรยาย', 'sort_order' => 1]);

        $this->assertSame([1920, 1280], [$image->width, $image->height]);
        $this->assertStringEndsWith('_thumb.webp', $image->thumb_path);
        Storage::disk('local')->assertExists($image->thumb_path);

        $image->delete();
        Storage::disk('local')->assertMissing([$path, $image->thumb_path]);
    }

    public function test_gallery_reorder_from_form(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $news = $this->news();
        $first = $news->images()->create(['image_path' => $this->cover(), 'caption_th' => 'หนึ่ง', 'sort_order' => 1]);
        $second = $news->images()->create(['image_path' => $this->cover(), 'caption_th' => 'สอง', 'sort_order' => 2]);

        $component = Livewire::test(EditNews::class, ['record' => $news->getRouteKey()]);
        $keys = array_keys($component->get('data.images'));
        $component->set('data.images', [$keys[1] => $component->get('data.images')[$keys[1]], $keys[0] => $component->get('data.images')[$keys[0]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['สอง', 'หนึ่ง'], $news->images()->pluck('caption_th')->all());
        $this->assertSame(2, NewsImage::count());
        $this->assertNotNull($first->fresh());
        $this->assertNotNull($second->fresh());
    }

    // ---------- ไฟล์แนบ ----------

    public function test_document_storer_accepts_whitelisted_files(): void
    {
        $stored = app(DocumentStorer::class)->store(UploadedFile::fake()->createWithContent('รายงาน ประจำปี.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"), 'news-attachments');

        $this->assertMatchesRegularExpression('#^news-attachments/[0-9a-f\-]{36}\.pdf$#', $stored->path);
        $this->assertSame('รายงาน ประจำปี.pdf', $stored->originalName);
        $this->assertSame('application/pdf', $stored->mimeType);
        $this->assertSame(64, strlen($stored->hash));
        Storage::disk('local')->assertExists($stored->path);
    }

    public function test_exported_xls_files_are_accepted(): void
    {
        $storer = app(DocumentStorer::class);
        $samples = [
            'html.xls' => '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><body><table><tr><td>สถานี</td><td>PM2.5</td></tr></table></body></html>',
            'xml.xls' => '<?xml version="1.0"?>'."\n".'<?mso-application progid="Excel.Sheet"?>'."\n".'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"/>',
            'tsv.xls' => "a\tb\n1\t2\n",
            'ole.xls' => hex2bin('D0CF11E0A1B11AE1').str_repeat("\0", 504),
            'html.doc' => '<html><body><p>เอกสาร</p></body></html>',
        ];

        foreach ($samples as $name => $content) {
            $stored = $storer->store(UploadedFile::fake()->createWithContent($name, $content), 'news-attachments');
            $this->assertStringEndsWith('.'.pathinfo($name, PATHINFO_EXTENSION), $stored->path, $name);
        }

        // แต่ไฟล์ PHP ที่ตั้งนามสกุล .xls ยังถูกปฏิเสธ
        $this->expectException(FileRejectedException::class);
        $storer->store(UploadedFile::fake()->createWithContent('evil.xls', '<?php system($_GET["c"]);'), 'news-attachments');
    }

    public function test_document_storer_rejects_bad_files(): void
    {
        $storer = app(DocumentStorer::class);
        $cases = [
            'shell.php' => '<?php echo 1;',
            'macro.docm' => 'x',
            'fake.pdf' => '<?php echo 1;',
            'script.exe' => 'MZ',
        ];

        foreach ($cases as $name => $content) {
            try {
                $storer->store(UploadedFile::fake()->createWithContent($name, $content), 'news-attachments');
                $this->fail("ควรปฏิเสธ {$name}");
            } catch (FileRejectedException) {
                $this->addToAssertionCount(1);
            }
        }

        // .docx ที่ฝัง macro (vbaProject.bin) ต้องถูกปฏิเสธ
        $docx = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($docx, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->addFromString('word/document.xml', '<w:document/>');
        $zip->addFromString('word/vbaProject.bin', 'macro');
        $zip->close();

        try {
            $storer->store(new UploadedFile($docx, 'macro.docx', null, null, true), 'news-attachments');
            $this->fail('ควรปฏิเสธ docx ที่มี macro');
        } catch (FileRejectedException $e) {
            $this->assertStringContainsString('macro', $e->getMessage());
        }

        $this->assertSame([], Storage::disk('local')->allFiles('news-attachments'));
    }

    public function test_attachment_download_rules(): void
    {
        $news = $this->news(['status' => News::STATUS_DRAFT]);
        $stored = app(DocumentStorer::class)->store(UploadedFile::fake()->createWithContent('a.txt', 'hello'), 'news-attachments');
        $attachment = $news->attachments()->create(['title_th' => 'ไฟล์', 'file_path' => $stored->path, 'original_name' => 'เอกสาร.txt']);

        $this->assertSame('clean', $attachment->scan_status);
        $this->assertSame('text/plain', $attachment->mime_type);
        $this->assertSame(5, $attachment->file_size);

        // ข่าวยังไม่เผยแพร่ -> คนทั่วไปโหลดไม่ได้, ผู้มีสิทธิ์หน้าข่าวโหลดได้ (ตรวจก่อนเผยแพร่)
        $this->get($attachment->downloadUrl())->assertNotFound();
        $this->actingAs(User::factory()->administrator()->create())
            ->get($attachment->downloadUrl())->assertOk()->assertHeader('Content-Disposition', "attachment; filename=attachment-{$attachment->id}.txt; filename*=utf-8''".rawurlencode('เอกสาร.txt'));
        auth()->logout();

        $news->update(['status' => News::STATUS_PUBLISHED]);
        $this->get($attachment->downloadUrl())->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        // ไฟล์ที่ยังไม่ clean ห้ามแสดง
        $attachment->update(['scan_status' => 'pending']);
        $this->get($attachment->downloadUrl())->assertNotFound();

        // ลบรายการ -> ลบไฟล์
        $attachment->delete();
        Storage::disk('local')->assertMissing($stored->path);
    }

    // ---------- หมวดหมู่ ----------

    public function test_category_with_news_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $used = NewsCategory::where('slug', 'news')->first();
        $empty = NewsCategory::where('slug', 'announcements')->first();
        $news = $this->news(['category_id' => $used->id]);
        $news->delete(); // แม้ข่าวถูกลบแบบกู้คืนได้ หมวดก็ยังลบไม่ได้

        Livewire::test(ManageNewsCategories::class)
            ->assertActionDisabled(TestAction::make(DeleteAction::class)->table($used))
            ->callAction(TestAction::make(DeleteAction::class)->table($used));

        $this->assertNotNull($used->fresh());
        $this->assertFalse(auth()->user()->can('delete', $used));

        Livewire::test(ManageNewsCategories::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($empty));
        $this->assertNull($empty->fresh());
    }

    public function test_category_slug_is_generated(): void
    {
        $category = NewsCategory::create(['name_th' => 'งานวิจัย', 'name_en' => 'Research Papers']);

        $this->assertSame('research-papers', $category->slug);
        $this->assertSame(4, $category->sort_order);
    }

    // ---------- สิทธิ์ / log ----------

    public function test_news_pages_require_news_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/backend/news')->assertForbidden();
        $this->get('/backend/news-categories')->assertForbidden();

        $user->permissions()->whereHas('page', fn ($q) => $q->where('code', 'news'))->update(['can_view' => true]);

        $this->get('/backend/news')->assertOk();
        $this->get('/backend/news/create')->assertOk();
        $this->get('/backend/news-categories')->assertOk();
    }

    public function test_activity_log_does_not_store_full_content(): void
    {
        $news = $this->news(['content_th' => '<p>'.str_repeat('ก', 1000).'</p>']);

        $log = ActivityLog::where('subject_type', 'news')->where('subject_id', $news->id)->firstOrFail();
        $this->assertStringContainsString('ข้อความยาว', $log->changes['new']['content_th']);
    }

    public function test_list_page_shows_status_labels(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $this->news(['status' => News::STATUS_PUBLISHED, 'published_at' => now()->addDay()]);
        $this->news(['status' => News::STATUS_DRAFT]);

        Livewire::test(ListNews::class)->assertSee(['ตั้งเวลาเผยแพร่', 'ร่าง']);
    }
}
