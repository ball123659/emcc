<?php

namespace Tests\Support;

use App\Libraries\Auth;
use App\Libraries\Storage;
use App\Models\UserModel;
use App\Models\UserPagePermissionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Emcc;
use Config\Services;

/**
 * ฐานของเทสต์: ฐานข้อมูลสร้างใหม่ + ข้อมูลเริ่มต้น ทุกเทสต์, ไฟล์อัปโหลดเก็บในโฟลเดอร์ชั่วคราว
 */
abstract class TestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait {
        call as private featureCall;
    }

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = 'App\Database\Seeds\DatabaseSeeder';

    protected string $storageDir;

    private bool $sendCsrf = true;

    protected function withoutCsrf(): static
    {
        $this->sendCsrf = false;

        return $this;
    }

    /** status code ของ response */
    protected static function code(TestResponse $response): int
    {
        return $response->response()->getStatusCode();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageDir = sys_get_temp_dir() . '/emcc-test-' . bin2hex(random_bytes(4)) . '/';
        config(Emcc::class)->storagePath = $this->storageDir;
        config(Emcc::class)->virusScanner = 'none';
        cache()->clean();
        $_FILES = $_POST = $_GET = $_COOKIE = $_SESSION = [];
        $this->session = [];
        Services::resetSingle('auth');
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->storageDir);
        $_FILES = [];
        parent::tearDown();
    }

    /** ส่งคำขอแบบเบราว์เซอร์ (ล้างข้อมูลผู้ใช้ที่จำไว้ระหว่างคำขอ) */
    public function call(string $method, string $path, ?array $params = null): TestResponse
    {
        Services::resetSingle('auth');
        Services::resetSingle('throttler');
        Services::resetSingle('security');
        Services::resetSingle('superglobals');
        Services::resetSingle('response');

        // ฟอร์มจริงมี CSRF token เสมอ (ทดสอบการไม่มี token ได้ด้วย withoutCsrf())
        if (strtoupper($method) !== 'GET' && $this->sendCsrf) {
            $token                 = bin2hex(random_bytes(16));
            $this->session['_token'] = $token;
            $params                = ($params ?? []) + ['_token' => $token];
        }

        $this->sendCsrf = true;

        $response = $this->featureCall($method, $path, $params);

        Services::resetSingle('auth');

        return $response;
    }

    /** คาดว่าได้ 404 (CodeIgniter โยน PageNotFoundException ในเทสต์) */
    protected function assertNotFound(callable $request): void
    {
        try {
            $response = $request();
        } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertSame(404, self::code($response), 'ควรได้ 404');
    }

    protected function makeUser(array $data = [], array $pages = []): array
    {
        $model = new UserModel();
        $id    = $model->insert($data + [
            'username'             => 'user' . bin2hex(random_bytes(3)),
            'email'                => bin2hex(random_bytes(4)) . '@example.com',
            'password'             => 'secret123',
            'first_name'           => 'ทดสอบ',
            'last_name'            => 'ระบบ',
            'role'                 => UserModel::ROLE_USER,
            'is_active'            => 1,
            'must_change_password' => 0,
        ]);

        if ($pages !== []) {
            $this->grant((int) $id, $pages);
        }

        return $model->withDeleted()->find($id);
    }

    protected function admin(string $role = UserModel::ROLE_ADMINISTRATOR): array
    {
        return $this->makeUser(['role' => $role]);
    }

    protected function grant(int $userId, array $codes): void
    {
        UserPagePermissionModel::fillMissing();
        $ids = array_column(db_connect()->table('pages')->whereIn('code', $codes)->get()->getResultArray(), 'id');
        db_connect()->table('user_page_permissions')->where('user_id', $userId)->whereIn('page_id', $ids)->update(['can_view' => 1]);
    }

    /** เข้าสู่ระบบในฐานะผู้ใช้ (ใส่ข้อมูลใน session เหมือนล็อกอินจริง) */
    protected function actingAs(array $user): static
    {
        $user = (new UserModel())->withDeleted()->find($user['id']);
        $_COOKIE[config('Session')->cookieName] = 'test-session'; // เบราว์เซอร์ที่ล็อกอินอยู่ส่ง cookie session มาเสมอ

        return $this->withSession(['auth_user_id' => (int) $user['id'], 'auth_hash' => Auth::passwordFingerprint($user)]);
    }

    /** จำลองไฟล์อัปโหลด */
    protected function upload(string $field, string $path, ?string $name = null, ?string $key = null): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        copy($path, $tmp);
        $file = ['name' => $name ?? basename($path), 'type' => 'application/octet-stream', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)];

        if ($key === null) {
            $_FILES[$field] = $file;

            return;
        }

        foreach ($file as $attr => $value) {
            $_FILES[$field][$attr][$key] = $value;
        }
    }

    protected function image(int $width = 1200, int $height = 800, string $type = 'jpg'): string
    {
        $path = sys_get_temp_dir() . '/emcc-img-' . bin2hex(random_bytes(4)) . '.' . $type;
        $img  = imagecreatetruecolor($width, $height);
        imagefill($img, 0, 0, imagecolorallocate($img, 112, 74, 159));
        $type === 'png' ? imagepng($img, $path) : imagejpeg($img, $path, 90);

        return $path;
    }

    protected function pdf(): string
    {
        $path = sys_get_temp_dir() . '/emcc-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");

        return $path;
    }

    protected function storageFiles(): array
    {
        if (! is_dir(Storage::root())) {
            return [];
        }

        $files = [];
        $it    = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(Storage::root(), \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $file) {
            $files[] = substr(str_replace('\\', '/', $file->getPathname()), strlen(Storage::root()));
        }

        sort($files);

        return $files;
    }

    protected function lastLog(?string $action = null): ?array
    {
        $builder = db_connect()->table('activity_logs')->orderBy('id', 'desc');

        if ($action !== null) {
            $builder->where('action', $action);
        }

        return $builder->get(1)->getRowArray();
    }

    private function deleteDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }
}
