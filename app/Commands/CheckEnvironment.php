<?php

namespace App\Commands;

use App\Libraries\Storage;
use App\Libraries\Uploads\ImageProcessor;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Emcc;
use Throwable;

/**
 * php spark emcc:check — ตรวจการตั้งค่า PHP / โฟลเดอร์ / ฐานข้อมูล / สแกนไวรัส ที่ระบบ EMCC ต้องใช้
 */
class CheckEnvironment extends BaseCommand
{
    protected $group       = 'EMCC';
    protected $name        = 'emcc:check';
    protected $description = 'ตรวจการตั้งค่า PHP / โฟลเดอร์ / ฐานข้อมูล / สแกนไวรัส ที่ระบบ EMCC ต้องใช้';

    private int $failures = 0;

    public function run(array $params)
    {
        $config = config(Emcc::class);
        CLI::write('PHP ' . PHP_VERSION . ' · ' . (php_ini_loaded_file() ?: 'ไม่มี php.ini') . ' · ' . ENVIRONMENT);

        $this->check('PHP 8.3 ขึ้นไป', version_compare(PHP_VERSION, '8.3.0', '>='), 'อัปเกรด PHP เป็น 8.3+');

        $tmp = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
        $this->check("โฟลเดอร์ไฟล์อัปโหลดชั่วคราวเขียนได้ ({$tmp})", $this->writable($tmp),
            'ตั้ง upload_tmp_dir ใน php.ini ให้ชี้ไปโฟลเดอร์ที่มีอยู่จริงและผู้ใช้ที่รัน PHP เขียนได้ เช่น upload_tmp_dir = "C:\php\tmp" แล้วรีสตาร์ต PHP/IIS');
        $this->check('file_uploads = On', (bool) ini_get('file_uploads'), 'ตั้ง file_uploads = On ใน php.ini');

        $upload = ImageProcessor::iniBytes((string) ini_get('upload_max_filesize'));
        $post   = ImageProcessor::iniBytes((string) ini_get('post_max_size'));
        $this->check("upload_max_filesize ≥ {$config->phpUploadMaxMb}M (ตอนนี้ " . ini_get('upload_max_filesize') . ')', $upload >= $config->phpUploadMaxMb * 1024 ** 2,
            "ตั้ง upload_max_filesize = {$config->phpUploadMaxMb}M ใน php.ini (หรือ public/.user.ini)");
        $this->check("post_max_size ≥ {$config->phpPostMaxMb}M (ตอนนี้ " . ini_get('post_max_size') . ')', $post >= $config->phpPostMaxMb * 1024 ** 2,
            "ตั้ง post_max_size = {$config->phpPostMaxMb}M ใน php.ini (หรือ public/.user.ini)");
        $this->check('post_max_size มากกว่า upload_max_filesize', $post > $upload, 'ตั้ง post_max_size ให้มากกว่า upload_max_filesize ไม่งั้นฟอร์มที่แนบไฟล์ขนาดเต็มจะส่งไม่ผ่าน');

        $memory = (string) ini_get('memory_limit');
        $this->check("memory_limit ≥ 256M (ตอนนี้ {$memory})", $memory === '-1' || ImageProcessor::iniBytes($memory) >= 256 * 1024 ** 2,
            'ตั้ง memory_limit = 256M ใน php.ini (ประมวลผลรูปจากกล้องความละเอียดสูง)');

        foreach (['gd', 'fileinfo', 'mysqli', 'mbstring', 'intl', 'exif', 'zip'] as $ext) {
            $this->check("extension {$ext}", extension_loaded($ext), "เปิด extension={$ext} ใน php.ini");
        }

        $this->check('GD รองรับ WebP', function_exists('imagewebp'), 'ใช้ PHP ที่ GD คอมไพล์พร้อม WebP (PHP บน Windows รุ่นทางการรองรับอยู่แล้ว)');

        foreach ([WRITEPATH . 'session', WRITEPATH . 'cache', WRITEPATH . 'logs', Storage::root()] as $dir) {
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $this->check("เขียนโฟลเดอร์ {$dir} ได้", $this->writable($dir), 'ให้สิทธิ์ Modify กับผู้ใช้ที่รัน PHP (IIS: IIS_IUSRS / App Pool identity)');
        }

        try {
            $row = db_connect()->query('SELECT @@collation_database AS c')->getRowArray();
            $this->check('เชื่อมต่อฐานข้อมูลได้ (collation ' . ($row['c'] ?? '?') . ')', true);
            $this->check('มีตารางของระบบ (รัน php spark migrate แล้ว)', db_connect()->tableExists('users'), 'รัน php spark migrate แล้ว php spark db:seed DatabaseSeeder');
        } catch (Throwable $e) {
            $this->check('เชื่อมต่อฐานข้อมูลได้', false, 'ตรวจ database.default.* ใน .env: ' . $e->getMessage());
        }

        if (ENVIRONMENT !== 'production') {
            CLI::write('  ! CI_ENVIRONMENT = ' . ENVIRONMENT . ' — ใช้ได้เฉพาะเครื่องพัฒนา บนเซิร์ฟเวอร์จริงต้องเป็น production (ไม่งั้นแถบ debug จะแสดงข้อมูลระบบ/ฐานข้อมูล)', 'yellow');
        }

        $proxies = array_keys(config('App')->proxyIPs);
        CLI::write('  · proxy ที่เชื่อถือ (TRUSTED_PROXIES): ' . ($proxies === [] ? 'ไม่ได้ตั้ง — ถ้าเว็บอยู่หลัง reverse proxy / load balancer ต้องตั้ง ไม่งั้นทุกคนจะมี IP เดียวกัน' : implode(', ', $proxies)));

        try {
            $this->check('ตาราง migrations เป็นของเวอร์ชันนี้', ! FromLaravel::hasLaravelMigrations(), 'ฐานข้อมูลเคยใช้กับเวอร์ชัน Laravel — รัน php spark emcc:from-laravel แล้ว php spark migrate --all');
        } catch (Throwable) {
            // เชื่อมต่อฐานข้อมูลไม่ได้ (แจ้งไปแล้วด้านบน)
        }

        $this->check('ตั้งกุญแจเข้ารหัส (encryption.key)', ENVIRONMENT !== 'production' || (string) config('Encryption')->key !== '', 'รัน php spark key:generate');

        $driver = strtolower($config->virusScanner);

        if ($driver === 'none' && ENVIRONMENT === 'production') {
            $this->check('ตั้งค่าโปรแกรมสแกนไวรัส (emcc.virusScanner)', false, 'โปรดักชันต้องตั้ง emcc.virusScanner = defender (Windows) หรือ clamav (Linux) — ตอนนี้ระบบจะไม่รับไฟล์อัปโหลดเลย');
        } elseif ($driver === 'none') {
            CLI::write('  ! emcc.virusScanner = none — ไม่สแกนไวรัส (ใช้ได้เฉพาะเครื่องพัฒนา โปรดักชันจะไม่รับไฟล์)', 'yellow');
        } else {
            $binary = $driver === 'clamav' ? $config->clamavBinary : $config->defenderBinary;
            $found  = $driver === 'clamav' ? $this->commandExists($binary) : is_file($binary);
            $this->check("โปรแกรมสแกนไวรัส ({$driver}: {$binary})", $found, 'ตรวจ path ใน .env (emcc.defenderBinary / emcc.clamavBinary)');
        }

        CLI::newLine();

        if ($this->failures > 0) {
            CLI::error("พบปัญหา {$this->failures} ข้อ — แก้ตามคำแนะนำด้านบน แล้วรันคำสั่งนี้อีกครั้ง");

            return EXIT_ERROR;
        }

        CLI::write('พร้อมใช้งาน', 'green');

        return EXIT_SUCCESS;
    }

    private function check(string $label, bool $ok, string $fix = ''): void
    {
        if ($ok) {
            CLI::write('  ' . CLI::color('✓', 'green') . " {$label}");

            return;
        }

        $this->failures++;
        CLI::write('  ' . CLI::color('✗', 'red') . " {$label}");

        if ($fix !== '') {
            CLI::write('      ' . CLI::color('วิธีแก้:', 'yellow') . " {$fix}");
        }
    }

    private function writable(string $dir): bool
    {
        if (! is_dir($dir)) {
            return false;
        }

        $file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'emcc-check-' . bin2hex(random_bytes(4));

        if (@file_put_contents($file, 'ok') === false) {
            return false;
        }

        @unlink($file);

        return true;
    }

    private function commandExists(string $binary): bool
    {
        if (is_file($binary)) {
            return true;
        }

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
            if (is_file($dir . DIRECTORY_SEPARATOR . $binary) || is_file($dir . DIRECTORY_SEPARATOR . $binary . '.exe')) {
                return true;
            }
        }

        return false;
    }
}
