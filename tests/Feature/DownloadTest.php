<?php

namespace Tests\Feature;

use App\Filament\Resources\DownloadCategories\Pages\ManageDownloadCategories;
use App\Filament\Resources\Downloads\Pages\CreateDownload;
use App\Filament\Resources\Downloads\Pages\EditDownload;
use App\Filament\Resources\Downloads\Pages\ListDownloads;
use App\Models\Download;
use App\Models\DownloadCategory;
use App\Models\User;
use App\Services\Files\DocumentStorer;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DownloadTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    private function storedPdf(string $name = 'คู่มือ.pdf', string $content = self::PDF): string
    {
        return app(DocumentStorer::class)->store(UploadedFile::fake()->createWithContent($name, $content), 'downloads')->path;
    }

    private function fileDownload(array $attributes = []): Download
    {
        return Download::create($attributes + [
            'category_id' => DownloadCategory::first()->id,
            'title_th' => 'คู่มือการใช้งาน',
            'type' => Download::TYPE_FILE,
            'file_path' => $this->storedPdf(),
            'original_name' => 'คู่มือ.pdf',
        ]);
    }

    public function test_file_metadata_is_filled_from_stored_file(): void
    {
        $download = $this->fileDownload();

        $this->assertSame('pdf', $download->file_extension);
        $this->assertSame('application/pdf', $download->mime_type);
        $this->assertSame(strlen(self::PDF), $download->file_size);
        $this->assertSame(hash('sha256', self::PDF), $download->file_hash);
        $this->assertSame(Download::SCAN_CLEAN, $download->scan_status);
        $this->assertNotNull($download->scanned_at);
        $this->assertNull($download->external_url);
        $this->assertSame(1, $download->sort_order);
    }

    public function test_public_download_counts_once_per_session(): void
    {
        $download = $this->fileDownload();

        $this->get($download->publicUrl())
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', "attachment; filename=download-{$download->id}.pdf; filename*=utf-8''".rawurlencode('คู่มือ.pdf'));
        $this->get($download->publicUrl())->assertOk();

        $this->assertSame(1, $download->refresh()->download_count);
    }

    public function test_replacing_file_keeps_same_link_and_deletes_old_file(): void
    {
        $download = $this->fileDownload();
        $url = $download->publicUrl();
        $old = $download->file_path;

        $download->update(['file_path' => $this->storedPdf('ฉบับใหม่.pdf', self::PDF."\n% v2"), 'original_name' => 'ฉบับใหม่.pdf']);

        $this->assertSame($url, $download->publicUrl());
        Storage::disk('local')->assertMissing($old);
        $this->assertSame('ฉบับใหม่.pdf', $download->original_name);
        $this->get($url)->assertOk()->assertHeader('Content-Disposition', "attachment; filename=download-{$download->id}.pdf; filename*=utf-8''".rawurlencode('ฉบับใหม่.pdf'));
    }

    public function test_external_link_redirects_and_clears_file_fields(): void
    {
        $download = $this->fileDownload();
        $old = $download->file_path;

        $download->update(['type' => Download::TYPE_LINK, 'external_url' => 'https://drive.google.com/file/d/abc']);

        $this->assertNull($download->file_path);
        $this->assertNull($download->scan_status);
        Storage::disk('local')->assertMissing($old);

        $this->get($download->publicUrl())->assertRedirect('https://drive.google.com/file/d/abc');
        $this->assertSame(1, $download->refresh()->download_count);
    }

    public function test_hidden_deleted_or_unscanned_items_are_not_public(): void
    {
        $inactive = $this->fileDownload(['is_active' => false]);
        $pending = $this->fileDownload();
        $pending->forceFill(['scan_status' => Download::SCAN_PENDING])->saveQuietly();
        $deleted = $this->fileDownload();
        $deleted->delete();

        $this->get($inactive->publicUrl())->assertNotFound();
        $this->get($pending->publicUrl())->assertNotFound();
        $this->get($deleted->publicUrl())->assertNotFound();
        $this->get('/downloads/999999')->assertNotFound();

        $this->assertSame([], Download::visible()->whereIn('id', [$inactive->id, $pending->id])->pluck('id')->all());

        // ผู้มีสิทธิ์หน้าไฟล์ดาวน์โหลดดูตัวอย่างรายการที่ปิดไว้ได้ (ไม่นับยอด) แต่ไฟล์ที่ยังไม่ clean ห้ามเสมอ
        $this->actingAs(User::factory()->administrator()->create());
        $this->get($inactive->publicUrl())->assertOk();
        $this->get($pending->publicUrl())->assertNotFound();
        $this->assertSame(0, $inactive->refresh()->download_count);
    }

    public function test_create_link_from_form_and_validation(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $category = DownloadCategory::first();

        Livewire::test(CreateDownload::class)
            ->fillForm(['title_th' => '', 'category_id' => null, 'type' => Download::TYPE_LINK, 'external_url' => 'javascript:alert(1)'])
            ->call('create')
            ->assertHasFormErrors(['title_th' => 'required', 'category_id' => 'required', 'external_url']);

        Livewire::test(CreateDownload::class)
            ->fillForm(['type' => Download::TYPE_FILE])
            ->call('create')
            ->assertHasFormErrors(['file_path' => 'required']);

        Livewire::test(CreateDownload::class)
            ->fillForm([
                'title_th' => 'วิดีโอแนะนำ',
                'category_id' => $category->id,
                'type' => Download::TYPE_LINK,
                'external_url' => 'https://www.youtube.com/watch?v=x',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('https://www.youtube.com/watch?v=x', Download::firstOrFail()->external_url);
    }

    public function test_create_file_from_form(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        Livewire::test(CreateDownload::class)
            ->fillForm([
                'title_th' => 'รายงานประจำปี',
                'category_id' => DownloadCategory::first()->id,
                'type' => Download::TYPE_FILE,
                'file_path' => UploadedFile::fake()->createWithContent('annual-report.pdf', self::PDF),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $download = Download::firstOrFail();
        $this->assertMatchesRegularExpression('#^downloads/[0-9a-f\-]{36}\.pdf$#', $download->file_path);
        $this->assertSame('annual-report.pdf', $download->original_name);
        $this->assertSame(Download::SCAN_CLEAN, $download->scan_status);
        $this->assertNotNull($download->created_by);
    }

    public function test_rejected_file_shows_error_under_field(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        Livewire::test(CreateDownload::class)
            ->fillForm([
                'title_th' => 'x',
                'category_id' => DownloadCategory::first()->id,
                'type' => Download::TYPE_FILE,
                'file_path' => UploadedFile::fake()->createWithContent('macro.xlsm', 'x'),
            ])
            ->call('create')
            ->assertHasFormErrors(['file_path']);

        $this->assertSame(0, Download::count());
    }

    public function test_drag_reorder(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $a = $this->fileDownload(['title_th' => 'A']);
        $b = $this->fileDownload(['title_th' => 'B']);

        Livewire::test(ListDownloads::class)->call('reorderTable', [(string) $b->id, (string) $a->id]);

        $this->assertSame(['B', 'A'], Download::visible()->pluck('title_th')->all());
    }

    public function test_category_with_downloads_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $used = DownloadCategory::where('slug', 'documents')->first();
        $empty = DownloadCategory::where('slug', 'media')->first();
        $this->fileDownload(['category_id' => $used->id])->delete();

        Livewire::test(ManageDownloadCategories::class)
            ->assertActionDisabled(TestAction::make(DeleteAction::class)->table($used))
            ->callAction(TestAction::make(DeleteAction::class)->table($used));
        $this->assertNotNull($used->fresh());

        Livewire::test(ManageDownloadCategories::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($empty));
        $this->assertNull($empty->fresh());
    }

    public function test_download_pages_require_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/backend/downloads')->assertForbidden();
        $this->get('/backend/download-categories')->assertForbidden();

        $user->permissions()->whereHas('page', fn ($q) => $q->where('code', 'downloads'))->update(['can_view' => true]);

        $user->forgetPagePermissions(); // ในระบบจริงแต่ละคำขอโหลดผู้ใช้ใหม่

        $this->get('/backend/downloads')->assertOk();
        $this->get('/backend/download-categories')->assertOk();
        $this->get('/backend/news')->assertForbidden();

        $download = $this->fileDownload();
        Livewire::test(EditDownload::class, ['record' => $download->id])->assertOk();
    }
}
