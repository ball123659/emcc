<?php

namespace Tests\Feature;

use App\Libraries\Storage;
use App\Models\DownloadModel;
use Tests\Support\TestCase;

/**
 * ไฟล์ดาวน์โหลด + ลิงก์สาธารณะ /downloads/{id}
 */
final class DownloadTest extends TestCase
{
    private function form(array $override = []): array
    {
        return $override + ['title_th' => ' เอกสาร ', 'type' => 'file', 'category_id' => '1', 'is_active' => '1'];
    }

    private function docx(bool $macro = false): string
    {
        $path = sys_get_temp_dir() . '/emcc-' . bin2hex(random_bytes(4)) . '.docx';
        $zip  = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->addFromString('word/document.xml', '<w:document/>');

        if ($macro) {
            $zip->addFromString('word/vbaProject.bin', 'macro');
        }

        $zip->close();

        return $path;
    }

    public function testUploadAndOverwriteKeepsSameLink(): void
    {
        $admin = $this->admin();
        $this->upload('file', $this->pdf(), 'รายงานประจำปี.pdf');
        $this->actingAs($admin)->post('backend/downloads', $this->form());

        $item = (new DownloadModel())->first();
        $this->assertNotNull($item, json_encode(session('errors')));
        $this->assertSame(['เอกสาร', 'pdf', 'clean', 'รายงานประจำปี.pdf'], [$item['title_th'], $item['file_extension'], $item['scan_status'], $item['original_name']]);
        $this->assertSame(64, strlen($item['file_hash']));

        $this->upload('file', $this->docx(), 'new.docx');
        $this->actingAs($admin)->post('backend/downloads/' . $item['id'], $this->form());
        $new = (new DownloadModel())->find($item['id']);
        $this->assertSame('docx', $new['file_extension']);
        $this->assertSame([$new['file_path']], $this->storageFiles(), 'ไฟล์เก่าถูกลบ');
        $this->assertSame(site_url('downloads/' . $item['id']), DownloadModel::publicUrl($new));

        // เปลี่ยนเป็นลิงก์ -> ไฟล์ถูกลบ
        $this->actingAs($admin)->post('backend/downloads/' . $item['id'], $this->form(['type' => 'link', 'external_url' => 'https://drive.google.com/x']));
        $this->assertNull((new DownloadModel())->find($item['id'])['file_path']);
        $this->assertSame([], $this->storageFiles());
    }

    public function testRejectedFiles(): void
    {
        $admin = $this->admin();
        $cases = [
            [$this->docx(true), 'macro.docx', 'macro'],
            [$this->image(), 'photo.pdf', 'เนื้อไฟล์ไม่ตรงกับนามสกุล'],
            [$this->pdf(), 'script.php', 'ไม่รองรับไฟล์ชนิดนี้'],
            [$this->pdf(), 'macro.docm', 'ไม่รองรับไฟล์ชนิดนี้'],
        ];

        foreach ($cases as [$path, $name, $message]) {
            $this->upload('file', $path, $name);
            $this->actingAs($admin)->post('backend/downloads', $this->form());
            $this->assertStringContainsString($message, session('errors')['file'], $name);
        }

        $_FILES = [];
        $this->actingAs($admin)->post('backend/downloads', $this->form());
        $this->assertSame('กรุณาเลือกไฟล์ที่จะให้ดาวน์โหลด', session('errors')['file']);

        $this->actingAs($admin)->post('backend/downloads', $this->form(['type' => 'link', 'external_url' => 'file:///etc/passwd']));
        $this->assertStringContainsString('http://', session('errors')['external_url']);

        $this->assertSame(0, (new DownloadModel())->countAllResults());
        $this->assertSame([], $this->storageFiles());
    }

    public function testLongFileNameKeepsExtension(): void
    {
        $stored = service('documentStorer')->store($this->pdf(), 'รายงาน' . str_repeat('ก', 250) . '.pdf', 'downloads');
        $this->assertStringEndsWith('.pdf', $stored->originalName);
        $this->assertLessThanOrEqual(200, mb_strlen($stored->originalName));
    }

    private function fileItem(array $data = []): array
    {
        $path = 'downloads/' . Storage::uuid() . '.pdf';
        Storage::copyFrom($this->pdf(), $path);
        $id = (new DownloadModel())->insert($data + ['category_id' => 1, 'title_th' => 'ไฟล์', 'type' => 'file', 'file_path' => $path,
            'original_name' => 'a.pdf', 'file_extension' => 'pdf', 'mime_type' => 'application/pdf', 'file_size' => 10, 'scan_status' => 'clean']);

        return (new DownloadModel())->find($id);
    }

    public function testPublicDownloadRulesAndCounting(): void
    {
        $item = $this->fileItem();
        $get  = function (string $agent, string $method = 'GET') use ($item) {
            $_SERVER['HTTP_USER_AGENT'] = $agent;
            \Config\Services::resetSingle('superglobals');

            return $this->withSession(['visitor' => bin2hex(random_bytes(3))])->call($method, 'downloads/' . $item['id']);
        };

        $res = $get('Mozilla/5.0 (Windows NT 10.0) Chrome/130');
        $res->assertStatus(200);
        $res->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('1', (string) (new DownloadModel())->find($item['id'])['download_count']);

        $get('Mozilla/5.0 (Windows NT 10.0) Chrome/130');   // session ใหม่ แต่ IP เดิม -> ไม่นับซ้ำ
        $get('Googlebot/2.1');                              // บอท
        $get('');                                           // ไม่มี user agent
        $get('Mozilla/5.0', 'HEAD');                        // HEAD
        $this->assertSame('1', (string) (new DownloadModel())->find($item['id'])['download_count']);
        unset($_SERVER['HTTP_USER_AGENT']);

        // ปิดอยู่ / ยังไม่สแกนผ่าน / ถูกลบ -> 404 สำหรับคนทั่วไป
        (new DownloadModel())->update($item['id'], ['is_active' => 0]);
        $this->assertNotFound(fn () => $this->withSession(['d' => 1])->get('downloads/' . $item['id']));
        $user = $this->makeUser([], ['downloads']);
        $this->actingAs($user)->get('downloads/' . $item['id'])->assertStatus(200); // ผู้มีสิทธิ์ดูตัวอย่างได้

        $pending = $this->fileItem(['scan_status' => 'pending']);
        $this->assertNotFound(fn () => $this->actingAs($user)->get('downloads/' . $pending['id']));
    }

    public function testLinkRedirectsOnlyToHttp(): void
    {
        $id = (new DownloadModel())->insert(['category_id' => 1, 'title_th' => 'ลิงก์', 'type' => 'link', 'external_url' => 'https://drive.google.com/x']);
        $this->withSession(['a' => 1])->get('downloads/' . $id)->assertRedirectTo('https://drive.google.com/x');

        db_connect()->table('downloads')->where('id', $id)->update(['external_url' => 'javascript:alert(1)']);
        $this->assertNotFound(fn () => $this->withSession(['b' => 1])->get('downloads/' . $id));
    }

    public function testToggleReorderDeleteRestore(): void
    {
        $admin = $this->admin();
        $a     = $this->fileItem(['title_th' => 'A']);
        $b     = $this->fileItem(['title_th' => 'B']);

        $this->actingAs($admin)->post('backend/downloads/' . $a['id'] . '/toggle', ['value' => '0'])->assertJSONFragment(['message' => 'ปิดแสดงแล้ว']);
        $this->actingAs($admin)->post('backend/downloads/reorder', ['ids' => [(string) $b['id'], (string) $a['id']]]);
        $this->assertSame(['B', 'A'], array_column((new DownloadModel())->orderBy('sort_order')->findAll(), 'title_th'));

        $this->actingAs($admin)->post('backend/downloads/' . $a['id'] . '/delete');
        $this->assertNull((new DownloadModel())->find($a['id']));
        $this->assertTrue(Storage::exists($a['file_path']), 'ลบแบบกู้คืนได้ ไฟล์ยังอยู่');
        $this->actingAs($admin)->post('backend/downloads/' . $a['id'] . '/restore');
        $this->assertNotNull((new DownloadModel())->find($a['id']));
    }
}
