<?php

namespace Tests\Unit;

use App\Libraries\Storage;
use App\Libraries\Uploads\UploadRejected;
use App\Libraries\VirusScan\ScanResult;
use App\Libraries\VirusScan\VirusScanner;
use App\Libraries\Uploads\DocumentStorer;
use App\Libraries\Uploads\ImageProcessor;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Emcc;
use RuntimeException;

/**
 * ประมวลผลรูป / ไฟล์ / สแกนไวรัส / ที่เก็บไฟล์
 */
final class UploadTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(Emcc::class)->storagePath = sys_get_temp_dir() . '/emcc-unit-' . bin2hex(random_bytes(4)) . '/';
    }

    private function jpegWithExif(): string
    {
        // JPEG ที่มี EXIF (APP1) + GPS ปลอม
        $img = imagecreatetruecolor(300, 200);
        ob_start();
        imagejpeg($img);
        $jpeg = (string) ob_get_clean();
        $exif = "Exif\0\0" . 'MM' . "\0\x2a\0\0\0\x08\0\0" . 'GPSLatitude secret';
        $app1 = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;
        $path = sys_get_temp_dir() . '/exif-' . bin2hex(random_bytes(3)) . '.jpg';
        file_put_contents($path, substr($jpeg, 0, 2) . $app1 . substr($jpeg, 2));

        return $path;
    }

    public function testImageIsReencodedWithoutMetadata(): void
    {
        $stored = service('imageProcessor')->store($this->jpegWithExif(), 'news', true);
        $binary = (string) file_get_contents(Storage::path($stored->path));

        $this->assertStringStartsWith('RIFF', $binary);
        $this->assertStringNotContainsString('GPSLatitude', $binary);
        $this->assertTrue(Storage::exists($stored->thumbPath));
        $this->assertSame([300, 200], [$stored->width, $stored->height]);
    }

    public function testInfectedOrFailedScanIsRejected(): void
    {
        $infected = new class () implements VirusScanner {
            public function scan(string $absolutePath): ScanResult
            {
                return ScanResult::Infected;
            }
        };

        $path = sys_get_temp_dir() . '/v.pdf';
        file_put_contents($path, "%PDF-1.4\n%%EOF");

        try {
            (new DocumentStorer($infected))->store($path, 'v.pdf', 'downloads');
            $this->fail('ควรปฏิเสธ');
        } catch (UploadRejected $e) {
            $this->assertStringContainsString('ตรวจพบไวรัส', $e->getMessage());
        }

        $img = sys_get_temp_dir() . '/v.png';
        imagepng(imagecreatetruecolor(10, 10), $img);
        $this->expectException(UploadRejected::class);
        (new ImageProcessor($infected))->store($img, 'slides');
    }

    public function testScannerSelectionAndCommands(): void
    {
        config(Emcc::class)->virusScanner = 'defender';
        $this->assertInstanceOf(\App\Libraries\VirusScan\DefenderScanner::class, \Config\Services::virusScanner(false));
        config(Emcc::class)->virusScanner = 'clamav';
        $this->assertInstanceOf(\App\Libraries\VirusScan\ClamAvScanner::class, \Config\Services::virusScanner(false));
        config(Emcc::class)->virusScanner = 'none';

        // ตัวสแกนที่หาโปรแกรมไม่เจอ = Failed (ไม่ใช่ Clean)
        config(Emcc::class)->clamavBinary = '/nonexistent/clamdscan';
        $path = sys_get_temp_dir() . '/c.txt';
        file_put_contents($path, 'x');
        $this->assertSame(ScanResult::Failed, (new \App\Libraries\VirusScan\ClamAvScanner())->scan($path));
    }

    public function testProductionWithoutScannerRejectsUploads(): void
    {
        \App\Libraries\VirusScan\NullScanner::$environment = 'production';
        $img = sys_get_temp_dir() . '/p.png';
        imagepng(imagecreatetruecolor(10, 10), $img);
        $pdf = sys_get_temp_dir() . '/p.pdf';
        file_put_contents($pdf, "%PDF-1.4\n%%EOF");

        try {
            foreach ([fn () => service('imageProcessor', false)->store($img, 'slides'), fn () => service('documentStorer', false)->store($pdf, 'p.pdf', 'downloads')] as $store) {
                try {
                    $store();
                    $this->fail('ควรปฏิเสธ');
                } catch (UploadRejected $e) {
                    $this->assertStringContainsString('ยังไม่ได้ตั้งค่าโปรแกรมสแกนไวรัส', $e->getMessage());
                }
            }
        } finally {
            \App\Libraries\VirusScan\NullScanner::$environment = ENVIRONMENT;
        }
    }

    public function testStorageRejectsPathTraversal(): void
    {
        foreach (['../.env', 'slides/../../x', '/etc/passwd', 'C:\\Windows\\x', "a\0b"] as $bad) {
            try {
                Storage::path($bad);
                $this->fail($bad);
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertFalse(Storage::exists('../.env'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', Storage::uuid());
    }
}
