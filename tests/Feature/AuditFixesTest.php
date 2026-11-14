<?php

namespace Tests\Feature;

use App\Commands\FromLaravel;
use App\Filters\CleanInput;
use App\Filters\Csrf;
use App\HTTP\RedirectResponse;
use App\Models\NewsCategoryModel;
use App\Models\UserModel;
use Config\App;
use Tests\Support\TestCase;

/**
 * แก้จาก audit รอบแรกของเวอร์ชัน CodeIgniter
 */
final class AuditFixesTest extends TestCase
{
    // 1: รหัสผ่านไม่ถูกเก็บลง session ตอนส่งฟอร์มไม่ผ่าน
    public function testPasswordsAreNeverKeptInSessionOldInput(): void
    {
        $this->makeUser(['username' => 'staff']);
        $this->post('backend/login', ['username' => 'staff', 'password' => 'MySecretPw99']);

        $old = session('_ci_old_input');
        $this->assertSame('staff', $old['post']['username']);
        $this->assertArrayNotHasKey('password', $old['post']);
        $this->assertStringNotContainsString('MySecretPw99', serialize($_SESSION));

        $this->assertSame(['a' => ['b' => 1]], RedirectResponse::strip(['password' => 'x', '_token' => 't', 'a' => ['b' => 1, 'current_password' => 'y']]));
    }

    // 2: migration ข้ามตารางที่มีอยู่แล้ว + ตรวจตาราง migrations ของ Laravel
    public function testMigrationsCanRunAgainOnExistingTables(): void
    {
        foreach (glob(APPPATH . 'Database/Migrations/*.php') as $file) {
            require_once $file;
            $class = 'App\\Database\\Migrations\\' . preg_replace('/^[\d-]+_/', '', basename($file, '.php'));
            (new $class(\Config\Database::forge('tests')))->up(); // ห้าม error "already exists"
        }

        $this->assertFalse(FromLaravel::hasLaravelMigrations());
        $this->assertSame(1, db_connect()->query("SELECT COUNT(*) AS n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_slides_news'")->getRowArray()['n'] + 0);
    }

    // 3: TRUSTED_PROXIES จาก .env ใช้ได้จริง
    public function testTrustedProxiesFromEnvironment(): void
    {
        putenv('TRUSTED_PROXIES=10.0.0.5,10.0.1.0/24');
        $_ENV['TRUSTED_PROXIES'] = $_SERVER['TRUSTED_PROXIES'] = '10.0.0.5,10.0.1.0/24';

        try {
            $this->assertSame(['10.0.0.5' => 'X-Forwarded-For', '10.0.1.0/24' => 'X-Forwarded-For'], (new App())->proxyIPs);
        } finally {
            putenv('TRUSTED_PROXIES');
            unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
        }
    }

    // 4: หน้าหมดอายุ -> แจ้งเตือน + เก็บค่าที่กรอกไว้ แล้วนำกลับมาใส่ฟอร์ม
    public function testExpiredFormShowsMessageAndRestoresInput(): void
    {
        $admin = $this->admin();
        $_SERVER['HTTP_REFERER'] = base_url('backend/news-categories?create=1');

        $res = $this->actingAs($admin)->withoutCsrf()->post('backend/news-categories', ['name_th' => 'หมวดที่พิมพ์ไว้', 'password' => 'x']);
        unset($_SERVER['HTTP_REFERER']);

        $res->assertRedirectTo(site_url('backend/news-categories?create=1'));
        $this->assertSame('หน้านี้หมดอายุแล้ว (เปิดค้างไว้นานเกินไป)', session('toast')['title']);
        $restore = session(Csrf::RESTORE_KEY);
        $this->assertSame('backend/news-categories', $restore['path']);
        $this->assertArrayNotHasKey('password', $restore['input']);
        $this->assertNull((new NewsCategoryModel())->where('name_th', 'หมวดที่พิมพ์ไว้')->first());

        // โหลดหน้าเดิม -> ค่ากลับมาในฟอร์ม
        $session = $_SESSION;
        $page = $this->withSession($session)->get('backend/news-categories?create=1');
        $page->assertSee('value="หมวดที่พิมพ์ไว้"');
        $this->assertNull(session(Csrf::RESTORE_KEY));

        // AJAX ได้ข้อความภาษาไทย
        $ajax = $this->actingAs($admin)->withoutCsrf()->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->post('backend/slides/reorder', ['ids' => ['1']]);
        $ajax->assertStatus(403);
        $this->assertStringContainsString('หน้านี้หมดอายุ', json_decode($ajax->response()->getBody(), true)['message']);
    }

    public function testSessionExpiredRedirectExplainsWhy(): void
    {
        $_COOKIE['emcc_session'] = 'expired-session-id';
        $this->withSession(['x' => 1])->get('backend/news')->assertRedirectTo(site_url('backend/login'));
        $this->assertSame('กรุณาเข้าสู่ระบบอีกครั้ง', session('toast')['title']);
        unset($_COOKIE['emcc_session']);
    }

    // 5: ข้อมูลเกิน post_max_size -> แจ้งภาษาไทย
    public function testPostLargerThanPhpLimitIsExplained(): void
    {
        $admin = $this->admin();
        $_SERVER['CONTENT_LENGTH'] = (string) (ini_bytes((string) ini_get('post_max_size')) + 1);

        try {
            $res = $this->actingAs($admin)->withoutCsrf()->post('backend/downloads', []);
        } finally {
            unset($_SERVER['CONTENT_LENGTH']);
        }

        $res->assertRedirect();
        $this->assertStringContainsString('ไฟล์ที่แนบรวมกันใหญ่เกิน', session('toast')['title']);
    }

    // 6: ข้อความขนาดไฟล์ใช้ค่าจริงของ php.ini
    public function testUploadLimitUsesPhpIniWhenSmaller(): void
    {
        $ini = ini_bytes((string) ini_get('upload_max_filesize'));
        $this->assertSame($ini > 0 ? min(20480, intdiv($ini, 1024)) : 20480, upload_limit_kb(20480));
        $this->assertSame('2 MB', mb_label(2048));
    }

    // 7: อักขระควบคุมจากการคัดลอก Word/PDF ถูกตัดทิ้ง ไม่ error
    public function testControlCharactersAreStrippedInsteadOfRejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('backend/news-categories', ['name_th' => "ข่าว\x0Cหน้า\x0B2", 'is_active' => '1']);
        $this->assertNotNull((new NewsCategoryModel())->where('name_th', 'ข่าวหน้า2')->first());

        $this->assertSame(['a' => "บรรทัด1\nบรรทัด2\tแท็บ"], CleanInput::clean(['a' => "บรรทัด1\nบรรทัด2\tแท็บ\x00\x7F"]));
        $this->assertSame('ab', CleanInput::clean("a\xC3b"), 'UTF-8 เสียถูกตัด');
    }

    // 8: ไม่พบ username ก็ยังตรวจ bcrypt (เวลาตอบใกล้กัน)
    public function testUnknownUsernameStillRunsPasswordHash(): void
    {
        $this->makeUser(['username' => 'staff']);
        $time = static function (callable $fn): float {
            $start = microtime(true);
            $fn();

            return microtime(true) - $start;
        };

        $known   = $time(fn () => service('auth', false)->attempt('staff', 'wrong'));
        $unknown = $time(fn () => service('auth', false)->attempt('nobody', 'wrong'));
        $this->assertGreaterThan($known * 0.5, $unknown);
    }

    // 9: เดารหัสผ่านปัจจุบันได้ไม่เกิน 5 ครั้ง
    public function testCurrentPasswordGuessingIsLimited(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->post('backend/profile/password', ['current_password_pw' => 'wrong' . $i, 'password' => 'newpass1', 'password_confirmation' => 'newpass1']);
        }

        $this->actingAs($user)->post('backend/profile/password', ['current_password_pw' => 'secret123', 'password' => 'newpass1', 'password_confirmation' => 'newpass1']);
        $this->assertStringContainsString('ผิดหลายครั้ง', session('errors')['current_password_pw']);
        $this->assertTrue(password_verify('secret123', (new UserModel())->find($user['id'])['password']));
    }

    // 11: หน้าดาวน์โหลดสาธารณะไม่เริ่ม session
    public function testPublicPagesDoNotStartSessions(): void
    {
        $filters = config('Filters')->globals['before'];
        $this->assertSame(['/', 'media/*', 'downloads/*', 'news/attachments/*'], $filters['csrf']['except']);
    }

    // 15: เมนูดูบันทึกการใช้งานของผู้ใช้ + แจ้งให้เลือกไฟล์ใหม่
    public function testUserRowLinksToActivityLogAndFileReselectNotice(): void
    {
        $admin = $this->admin();
        $user  = $this->makeUser();
        $this->actingAs($admin)->get('backend/users')->assertSee('activity-logs?user=' . $user['id']);

        $this->upload('image_th', $this->image());
        $this->actingAs($admin)->post('backend/slides', ['title' => '', 'alt_th' => 'x', 'link_type' => 'none']);
        $this->assertStringContainsString('ต้องเลือกใหม่', (string) session('toast')['text']);
    }
}
