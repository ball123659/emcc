<?php

namespace Tests\Feature;

use App\Libraries\Storage;
use App\Models\PopupModel;
use App\Models\SlideModel;
use Tests\Support\TestCase;

final class HighlightTest extends TestCase
{
    private function slideForm(array $override = []): array
    {
        return $override + ['title' => ' สไลด์ ', 'alt_th' => ' คำอธิบาย ', 'link_type' => 'none', 'is_active' => '1', 'open_new_tab' => '1'];
    }

    public function testCreateSlideConvertsImageToWebpAndTrims(): void
    {
        $admin = $this->admin();
        $this->upload('image_th', $this->image(4000, 1000));
        $this->actingAs($admin)->post('backend/slides', $this->slideForm(['link_type' => 'url', 'link_url' => '  https://ieat.go.th  ']));

        $slide = (new SlideModel())->first();
        $this->assertNotNull($slide, json_encode(session('errors')));
        $this->assertSame(['สไลด์', 'คำอธิบาย', 'https://ieat.go.th'], [$slide['title'], $slide['alt_th'], $slide['link_url']]);
        $this->assertMatchesRegularExpression('~^slides/[0-9a-f\-]{36}\.webp$~', $slide['image_th']);

        $info = getimagesize(Storage::path($slide['image_th']));
        $this->assertSame([1920, 480, 'image/webp'], [$info[0], $info[1], $info['mime']], 'ย่อด้านยาวเหลือ 1920 คงสัดส่วน');
        $this->assertSame((int) $admin['id'], (int) $slide['created_by']);

        $res = $this->get('media/' . $slide['image_th']);
        $res->assertOK();
        $res->assertHeader('Content-Type', 'image/webp');
    }

    public function testSlideValidation(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('backend/slides', ['title' => '', 'alt_th' => '', 'link_type' => 'url', 'link_url' => 'javascript:alert(1)',
            'start_date' => '2026-12-10', 'end_date' => '2026-12-01']);
        $errors = session('errors');
        $this->assertSame('กรุณากรอกชื่อเรียก', $errors['title']);
        $this->assertStringContainsString('http://', $errors['link_url']);
        $this->assertStringContainsString('วันสิ้นสุดต้องไม่ก่อนวันเริ่ม', $errors['end_date']);

        $this->actingAs($admin)->post('backend/slides', $this->slideForm());
        $this->assertSame('กรุณาเลือกรูป', session('errors')['image_th']);

        $fake = sys_get_temp_dir() . '/fake.jpg';
        file_put_contents($fake, '<?php echo 1;');
        $this->upload('image_th', $fake);
        $this->actingAs($admin)->post('backend/slides', $this->slideForm());
        $this->assertStringContainsString('JPG, PNG หรือ WebP', session('errors')['image_th']);
        $this->assertSame([], $this->storageFiles());

        $this->upload('image_th', $this->image());
        $this->actingAs($admin)->post('backend/slides', $this->slideForm(['link_type' => 'news', 'news_id' => '999']));
        $this->assertStringContainsString('ข่าวที่เผยแพร่แล้ว', session('errors')['news_id']);
    }

    public function testReplacingImageDeletesOldFile(): void
    {
        $admin = $this->admin();
        $this->upload('image_th', $this->image());
        $this->actingAs($admin)->post('backend/slides', $this->slideForm());
        $slide = (new SlideModel())->first();

        $this->upload('image_th', $this->image(800, 600, 'png'));
        $this->actingAs($admin)->post('backend/slides/' . $slide['id'], $this->slideForm());
        $new = (new SlideModel())->find($slide['id']);

        $this->assertNotSame($slide['image_th'], $new['image_th']);
        $this->assertSame([$new['image_th']], $this->storageFiles());
    }

    private function popup(string $title, string $start, string $end, int $active = 1): int
    {
        return (int) (new PopupModel())->insert(['title' => $title, 'image_th' => 'popups/x.webp', 'alt_th' => 'a', 'start_date' => $start, 'end_date' => $end, 'is_active' => $active]);
    }

    public function testPopupStatusTabsToggleAndExtend(): void
    {
        $admin = $this->admin();
        $today = date('Y-m-d');
        $this->popup('กำลังแสดง', $today, $today);
        $this->popup('รอแสดง', date('Y-m-d', strtotime('+3 days')), date('Y-m-d', strtotime('+5 days')));
        $expired = $this->popup('หมดอายุ', '2020-01-01', '2020-01-05');
        $this->popup('ปิด', $today, $today, 0);

        foreach (['showing' => 'กำลังแสดง', 'scheduled' => 'รอแสดง', 'expired' => 'หมดอายุ', 'disabled' => 'ปิด'] as $tab => $title) {
            $res = $this->actingAs($admin)->get('backend/popups?tab=' . $tab);
            $res->assertSee('>' . $title . '</a>');
            $this->assertSame(1, (new PopupModel())->whereStatus($tab)->countAllResults());
        }

        $res = $this->actingAs($admin)->post('backend/popups/' . $expired . '/toggle', ['value' => '0']);
        $res->assertJSONFragment(['message' => 'ปิดแสดงแล้ว']);

        $this->actingAs($admin)->post('backend/popups/' . $expired . '/extend');
        $this->assertSame(date('Y-m-d', strtotime('+7 days')), (new PopupModel())->find($expired)['end_date']);
    }

    public function testPopupRequiresDates(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('backend/popups', ['title' => 'x', 'alt_th' => 'x', 'start_date' => 'not-a-date']);
        $this->assertStringContainsString('วันเริ่มแสดง', session('errors')['start_date']);
        $this->assertStringContainsString('วันสิ้นสุด', session('errors')['end_date']);
    }

    public function testReorderInsideTabKeepsUniqueOrderAndIsLogged(): void
    {
        $admin = $this->admin();
        $today = date('Y-m-d');
        $a = $this->popup('A', $today, $today);
        $this->popup('X', date('Y-m-d', strtotime('+2 days')), date('Y-m-d', strtotime('+5 days')));
        $c = $this->popup('C', $today, $today);

        $this->actingAs($admin)->post('backend/popups/reorder', ['ids' => [(string) $c, (string) $a, '999999']]);

        $order = array_column((new PopupModel())->orderBy('sort_order')->findAll(), 'sort_order', 'title');
        $this->assertSame(['C' => '1', 'X' => '2', 'A' => '3'], array_map('strval', $order));
        $this->assertSame('reorder', $this->lastLog()['action']);
    }

    public function testDeleteAndRestore(): void
    {
        $admin = $this->admin();
        $id    = $this->popup('ลบได้', date('Y-m-d'), date('Y-m-d'));

        $this->actingAs($admin)->post('backend/popups/' . $id . '/delete');
        $this->assertNull((new PopupModel())->find($id));
        $this->actingAs($admin)->get('backend/popups?tab=trashed')->assertSee('ลบได้');

        $this->actingAs($admin)->post('backend/popups/' . $id . '/restore');
        $this->assertNotNull((new PopupModel())->find($id));
        $this->assertSame(['create', 'delete', 'restore'], array_column(db_connect()->table('activity_logs')->where('subject_type', 'popups')->orderBy('id')->get()->getResultArray(), 'action'));
    }
}
