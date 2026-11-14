<?php

namespace Tests\Feature;

use App\Libraries\Storage;
use App\Models\NewsAttachmentModel;
use App\Models\NewsCategoryModel;
use App\Models\NewsImageModel;
use App\Models\NewsModel;
use Tests\Support\TestCase;

/**
 * ข่าวประชาสัมพันธ์
 */
final class NewsTest extends TestCase
{
    private function form(array $override = []): array
    {
        return $override + ['title_th' => ' ข่าวทดสอบ ', 'content_th' => '<p>เนื้อหา</p>', 'status' => 'draft', 'category_id' => '1', 'is_pinned' => '0', 'slug' => ''];
    }

    private function news(array $data = []): int
    {
        return (int) (new NewsModel())->insert($data + ['category_id' => 1, 'title_th' => 'ข่าว', 'content_th' => '<p>x</p>', 'cover_image' => 'news/x.webp']);
    }

    public function testCreateWithCoverGalleryAndAttachment(): void
    {
        $admin = $this->admin();
        $this->upload('cover_image', $this->image(2400, 1350));
        $this->upload('gallery_new', $this->image(), 'g.jpg', 'k1');
        $this->upload('attachment_new', $this->pdf(), 'รายงาน ประจำปี.pdf', 'k2');

        $this->actingAs($admin)->post('backend/news', $this->form([
            'title_en' => 'PM2.5 Report', 'status' => 'published',
            'gallery_new_meta' => ['k1' => ['caption_th' => ' คำบรรยาย ', 'sort' => '1']],
            'attachment_new_meta' => ['k2' => ['title_th' => '', 'sort' => '1']],
        ]));

        $news = (new NewsModel())->first();
        $this->assertNotNull($news, json_encode(session('errors')));
        $this->assertSame('ข่าวทดสอบ', $news['title_th']);
        $this->assertSame(date('Y-m') . '-pm2-5-report', $news['slug']);
        $this->assertSame(date('Y-m-d 00:00:00'), $news['published_at'], 'เผยแพร่โดยไม่ระบุวัน = วันนี้');
        $this->assertSame(1920, getimagesize(Storage::path($news['cover_image']))[0]);
        $this->assertTrue(Storage::exists($news['cover_thumb']));

        $image = (new NewsImageModel())->where('news_id', $news['id'])->first();
        $this->assertSame('คำบรรยาย', $image['caption_th']);
        $attachment = (new NewsAttachmentModel())->where('news_id', $news['id'])->first();
        $this->assertSame('รายงาน ประจำปี', $attachment['title_th'], 'ไม่ตั้งชื่อ = ใช้ชื่อไฟล์');
        $this->assertSame('clean', $attachment['scan_status']);

        // ไฟล์แนบดาวน์โหลดได้ (ข่าวเผยแพร่แล้ว) — ชื่อไทยผ่าน filename*
        $res = $this->withSession(['x' => 1])->get('news/attachments/' . $attachment['id']);
        $res->assertStatus(200);
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("filename*=UTF-8''" . rawurlencode('รายงาน ประจำปี.pdf'), $res->response()->getHeaderLine('Content-Disposition'));

        $this->assertSame(['create'], array_column(db_connect()->table('activity_logs')->where('subject_type', 'news_images')->get()->getResultArray(), 'action'));
    }

    public function testThaiOnlyTitleGetsIdSlugAndCreateLogHasFinalSlug(): void
    {
        $id   = $this->news(['title_th' => 'ข่าวภาษาไทยล้วน', 'published_at' => '2026-11-20']);
        $news = (new NewsModel())->find($id);
        $this->assertSame("2026-11-{$id}", $news['slug']);

        $log = db_connect()->table('activity_logs')->where(['subject_type' => 'news', 'subject_id' => $id, 'action' => 'create'])->get()->getRowArray();
        $this->assertSame("2026-11-{$id}", json_decode($log['changes'], true)['new']['slug']);
    }

    public function testSlugLockedAfterPublishEvenWhenHidden(): void
    {
        $id = $this->news(['title_en' => 'Shared link', 'status' => 'published', 'published_at' => date('Y-m-d')]);
        $original = (new NewsModel())->find($id)['slug'];

        (new NewsModel())->update($id, ['status' => 'hidden']);
        (new NewsModel())->update($id, ['slug' => 'renamed']);
        $this->assertSame($original, (new NewsModel())->find($id)['slug']);

        // ร่างยังเปลี่ยน slug ได้
        $draft = $this->news(['title_en' => 'Draft news']);
        (new NewsModel())->update($draft, ['slug' => 'My Custom']);
        $this->assertSame('my-custom', (new NewsModel())->find($draft)['slug']);
    }

    public function testContentIsSanitizedAndPastedImagesWarn(): void
    {
        $admin = $this->admin();
        $this->upload('cover_image', $this->image());
        $this->actingAs($admin)->post('backend/news', $this->form(['content_th' => '<p>ข้อความ<img src="data:image/png;base64,AAAA"><script>x</script></p>']));

        $news = (new NewsModel())->first();
        $this->assertStringNotContainsString('<img', $news['content_th']);
        $this->assertStringNotContainsString('<script', $news['content_th']);
        $this->assertSame('รูปในเนื้อหาข่าวถูกนำออก', session('toast')['title']);
    }

    public function testValidation(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('backend/news', $this->form(['title_th' => '', 'content_th' => '<p><br></p>', 'category_id' => '', 'slug' => 'มีภาษาไทย']));
        $errors = session('errors');
        $this->assertSame('กรุณากรอกหัวข้อข่าวภาษาไทย', $errors['title_th']);
        $this->assertSame('กรุณากรอกเนื้อหาข่าวภาษาไทย', $errors['content_th']);
        $this->assertSame('กรุณาเลือกหมวดหมู่', $errors['category_id']);
        $this->assertStringContainsString('a-z', $errors['slug']);

        // ไม่มีรูปปก
        $this->actingAs($admin)->post('backend/news', $this->form());
        $this->assertSame('กรุณาเลือกรูปปก', session('errors')['cover_image']);
    }

    public function testScheduledNewsAndAttachmentAccess(): void
    {
        $user = $this->makeUser([], ['news']);
        $id   = $this->news(['status' => 'published', 'published_at' => date('Y-m-d', strtotime('+3 days'))]);
        $this->assertSame('scheduled', NewsModel::displayStatus((new NewsModel())->find($id)));

        $path = 'news-attachments/' . Storage::uuid() . '.pdf';
        Storage::copyFrom($this->pdf(), $path);
        $att = (new NewsAttachmentModel())->insert(['news_id' => $id, 'title_th' => 'a', 'file_path' => $path, 'original_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'file_size' => 10, 'scan_status' => 'clean']);

        // ยังไม่เผยแพร่: คนทั่วไป 404, ผู้มีสิทธิ์หน้าข่าวดูตัวอย่างได้
        $this->assertNotFound(fn () => $this->withSession(['x' => 1])->get('news/attachments/' . $att));
        $this->actingAs($user)->get('news/attachments/' . $att)->assertStatus(200);

        $this->actingAs($user)->post('backend/news/' . $id . '/publish');
        $this->assertSame('published', NewsModel::displayStatus((new NewsModel())->find($id)));
        $this->withSession(['x' => 1])->get('news/attachments/' . $att)->assertStatus(200);

        // ไฟล์ที่ยังไม่ clean ห้ามดาวน์โหลด
        (new NewsAttachmentModel())->update($att, ['scan_status' => 'pending']);
        $this->assertNotFound(fn () => $this->actingAs($user)->get('news/attachments/' . $att));
    }

    public function testGalleryEditReorderAndDelete(): void
    {
        $admin = $this->admin();
        $id    = $this->news();
        $images = [];

        foreach ([1, 2] as $i) {
            $stored   = service('imageProcessor')->store($this->image(), 'news', true);
            $images[] = (int) (new NewsImageModel())->insert(['news_id' => $id, 'image_path' => $stored->path, 'thumb_path' => $stored->thumbPath, 'sort_order' => $i]);
        }

        $this->actingAs($admin)->post('backend/news/' . $id, $this->form([
            'images' => [
                (string) $images[0] => ['caption_th' => 'แรก', 'sort' => '2', 'delete' => '0'],
                (string) $images[1] => ['caption_th' => '', 'sort' => '1', 'delete' => '1'],
            ],
        ]));

        $rows = (new NewsImageModel())->where('news_id', $id)->findAll();
        $this->assertCount(1, $rows);
        $this->assertSame('แรก', $rows[0]['caption_th']);
        $this->assertSame('1', (string) $rows[0]['sort_order']);
        $this->assertCount(2, $this->storageFiles(), 'ลบไฟล์รูปที่ถูกลบ (รูป + thumbnail)');
        $this->assertSame('delete', db_connect()->table('activity_logs')->where('subject_type', 'news_images')->orderBy('id', 'desc')->get()->getRowArray()['action']);
    }

    public function testListFiltersTabsAndCategoryInUseCannotBeDeleted(): void
    {
        $admin = $this->admin();
        $this->news(['title_th' => 'ข่าวร่าง']);
        $this->news(['title_th' => 'ข่าวปักหมุด', 'status' => 'published', 'published_at' => date('Y-m-d'), 'is_pinned' => 1]);

        $res = $this->actingAs($admin)->get('backend/news?tab=draft');
        $res->assertSee('ข่าวร่าง');
        $res->assertDontSee('ข่าวปักหมุด');
        $this->actingAs($admin)->get('backend/news?pinned=1')->assertSee('ข่าวปักหมุด');
        $this->actingAs($admin)->get('backend/news?q=' . urlencode('ร่าง'))->assertDontSee('ข่าวปักหมุด');

        $this->actingAs($admin)->post('backend/news-categories/1/delete');
        $this->assertNotNull((new NewsCategoryModel())->find(1));
        $this->assertSame('หมวดนี้ยังมีข้อมูลอยู่ ลบไม่ได้', session('toast')['title']);

        // หมวดว่างลบได้ / slug ยาวไม่เกิน 100
        $this->actingAs($admin)->post('backend/news-categories', ['name_th' => ' หมวดใหม่ ', 'name_en' => str_repeat('a', 100), 'is_active' => '1']);
        $this->actingAs($admin)->post('backend/news-categories', ['name_th' => 'หมวดสอง', 'name_en' => str_repeat('a', 100), 'is_active' => '1']);
        $second = (new NewsCategoryModel())->where('name_th', 'หมวดสอง')->first();
        $this->assertLessThanOrEqual(100, strlen($second['slug']));
        $this->assertStringEndsWith('-2', $second['slug']);
        $this->assertNotNull((new NewsCategoryModel())->where('name_th', 'หมวดใหม่')->first());

        $this->actingAs($admin)->post('backend/news-categories/' . $second['id'] . '/delete');
        $this->assertNull((new NewsCategoryModel())->find($second['id']));
    }
}
