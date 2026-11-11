<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * ตรวจความพร้อมของเครื่อง/เซิร์ฟเวอร์ (Windows + IIS หรือ Linux): php artisan emcc:check
 */
class CheckEnvironment extends Command
{
    protected $signature = 'emcc:check';

    protected $description = 'ตรวจการตั้งค่า PHP / โฟลเดอร์ / ฐานข้อมูล / สแกนไวรัส ที่ระบบ EMCC ต้องใช้';

    private int $failures = 0;

    public function handle(): int
    {
        $this->line('PHP '.PHP_VERSION.' · '.php_ini_loaded_file());
        $this->newLine();

        // อัปโหลดไฟล์
        $tmp = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
        $this->check(
            "โฟลเดอร์ไฟล์อัปโหลดชั่วคราว ({$tmp}) เขียนได้",
            $this->writable($tmp),
            'ตั้ง upload_tmp_dir ใน php.ini ให้ชี้ไปโฟลเดอร์ที่มีอยู่จริงและผู้ใช้ที่รัน PHP เขียนได้ เช่น upload_tmp_dir = "C:\php\tmp" (สร้างโฟลเดอร์ก่อน) แล้วรีสตาร์ต PHP/IIS',
        );
        $this->check('file_uploads = On', (bool) ini_get('file_uploads'), 'ตั้ง file_uploads = On ใน php.ini');

        $uploadMb = (int) config('emcc.php_upload_max_mb');
        $postMb = (int) config('emcc.php_post_max_mb');
        $this->check(
            "upload_max_filesize ≥ {$uploadMb}M (ตอนนี้ ".ini_get('upload_max_filesize').')',
            $this->bytes(ini_get('upload_max_filesize')) >= $uploadMb * 1024 * 1024,
            "ตั้ง upload_max_filesize = {$uploadMb}M ใน php.ini",
        );
        $this->check(
            "post_max_size ≥ {$postMb}M (ตอนนี้ ".ini_get('post_max_size').')',
            $this->bytes(ini_get('post_max_size')) >= $postMb * 1024 * 1024,
            "ตั้ง post_max_size = {$postMb}M ใน php.ini (ต้องมากกว่า upload_max_filesize)",
        );
        $this->check(
            'post_max_size มากกว่า upload_max_filesize',
            $this->bytes(ini_get('post_max_size')) > $this->bytes(ini_get('upload_max_filesize')),
            'ตั้ง post_max_size ให้มากกว่า upload_max_filesize ไม่งั้นฟอร์มที่แนบไฟล์ขนาดเต็มจะส่งไม่ผ่าน',
        );

        // extension
        foreach (['gd', 'fileinfo', 'pdo_mysql', 'mbstring', 'intl', 'exif'] as $ext) {
            $this->check("extension {$ext}", extension_loaded($ext), "เปิด extension={$ext} ใน php.ini");
        }
        $this->check('GD รองรับ WebP', function_exists('imagewebp'), 'ใช้ PHP ที่ GD คอมไพล์พร้อม WebP (PHP บน Windows รุ่นทางการรองรับอยู่แล้ว)');

        // โฟลเดอร์ของ Laravel
        foreach ([storage_path('app/private'), storage_path('framework/sessions'), storage_path('logs'), base_path('bootstrap/cache')] as $dir) {
            $this->check("เขียนโฟลเดอร์ {$dir} ได้", $this->writable($dir), 'ให้สิทธิ์ Modify กับผู้ใช้ที่รัน PHP (IIS: IIS_IUSRS / App Pool identity)');
        }

        try {
            Storage::disk('local')->put('.emcc-check', 'ok');
            Storage::disk('local')->delete('.emcc-check');
            $this->check('เขียน storage disk local ได้', true);
        } catch (\Throwable $e) {
            $this->check('เขียน storage disk local ได้', false, $e->getMessage());
        }

        // ฐานข้อมูล
        try {
            $collation = DB::selectOne('SELECT @@collation_database AS c')->c ?? '?';
            $this->check("เชื่อมต่อฐานข้อมูลได้ (collation {$collation})", true);
        } catch (\Throwable $e) {
            $this->check('เชื่อมต่อฐานข้อมูลได้', false, 'ตรวจ DB_* ใน .env: '.$e->getMessage());
        }

        // สแกนไวรัส
        $driver = config('emcc.virus_scanner.driver');
        if ($driver === 'none') {
            $this->warn('  ! VIRUS_SCANNER=none — ไม่สแกนไวรัส (ใช้ได้เฉพาะเครื่องพัฒนา)');
        } else {
            $binary = config("emcc.virus_scanner.{$driver}_binary");
            $found = $driver === 'clamav' ? $this->commandExists($binary) : is_file((string) $binary);
            $this->check("โปรแกรมสแกนไวรัส ({$driver}: {$binary})", $found, 'ตรวจ path ใน .env (DEFENDER_BINARY / CLAMAV_BINARY)');
        }

        $this->newLine();

        if ($this->failures > 0) {
            $this->error("พบปัญหา {$this->failures} ข้อ — แก้ตามคำแนะนำด้านบน แล้วรันคำสั่งนี้อีกครั้ง");

            return self::FAILURE;
        }

        $this->info('พร้อมใช้งาน');

        return self::SUCCESS;
    }

    private function check(string $label, bool $ok, string $fix = ''): void
    {
        if ($ok) {
            $this->line("  <fg=green>✓</> {$label}");

            return;
        }

        $this->failures++;
        $this->line("  <fg=red>✗</> {$label}");

        if ($fix !== '') {
            $this->line("      <fg=yellow>วิธีแก้:</> {$fix}");
        }
    }

    private function writable(string $dir): bool
    {
        if (! is_dir($dir)) {
            return false;
        }

        $file = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.'emcc-check-'.bin2hex(random_bytes(4));

        if (@file_put_contents($file, 'ok') === false) {
            return false;
        }

        @unlink($file);

        return true;
    }

    private function bytes(string|false $value): int
    {
        $value = trim((string) $value);
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function commandExists(string $command): bool
    {
        $finder = PHP_OS_FAMILY === 'Windows' ? 'where' : 'command -v';

        return trim((string) @shell_exec($finder.' '.escapeshellarg($command).' 2>'.(PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'))) !== '';
    }
}
