<?php

namespace App\Controllers\Backend;

use App\Controllers\BaseController;
use App\Libraries\Auth;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Model;

/**
 * Controller พื้นฐานของหลังบ้าน — ตัวช่วยรับค่า (trim), แจ้งเตือน, แบ่งหน้า, ตอบ JSON
 */
abstract class BackendController extends BaseController
{
    protected Auth $auth;

    /** จำนวนรายการต่อหน้าที่เลือกได้ */
    protected const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    protected int $defaultPerPage = 10;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->auth = service('auth');
    }

    protected function user(): array
    {
        return $this->auth->user() ?? [];
    }

    /** รับค่าข้อความ: trim, ช่องว่าง = null */
    protected function input(string $key): ?string
    {
        $value = $this->request->getPost($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    protected function bool(string $key): int
    {
        return (string) $this->request->getPost($key) === '1' ? 1 : 0;
    }

    /** วันที่จากปฏิทิน (Y-m-d) — ค่าที่ไม่ใช่วันที่จริงถือว่าว่าง */
    protected function date(string $key): ?string
    {
        $value = $this->input($key);

        if ($value === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        [$y, $m, $d] = array_map('intval', explode('-', $value));

        return checkdate($m, $d, $y) ? $value : null;
    }

    protected function toast(string $title, string $type = 'success', ?string $text = null): void
    {
        session()->setFlashdata('toast', ['type' => $type, 'title' => $title, 'text' => $text]);
    }

    /** กลับไปฟอร์มพร้อมค่าที่กรอกและข้อความผิดพลาดใต้ช่อง */
    protected function backWithErrors(array $errors, ?string $toast = 'ยังบันทึกไม่ได้ กรุณาแก้ไขช่องที่มีข้อความสีแดง'): RedirectResponse
    {
        if ($toast !== null) {
            $this->toast($toast, 'danger', $this->hasChosenFiles() ? 'ไฟล์/รูปที่เลือกไว้ต้องเลือกใหม่อีกครั้ง (เบราว์เซอร์ไม่เก็บไฟล์ไว้ให้)' : null);
        }

        return redirect()->back()->withInput()->with('errors', $errors);
    }

    /** ฟอร์มนี้มีไฟล์ที่ผู้ใช้เลือกแนบมาหรือไม่ */
    protected function hasChosenFiles(): bool
    {
        $stack = $this->request->getFiles();

        while ($stack !== []) {
            $item = array_pop($stack);

            if (is_array($item)) {
                array_push($stack, ...array_values($item));
            } elseif ($item instanceof \CodeIgniter\HTTP\Files\UploadedFile && $item->getError() !== UPLOAD_ERR_NO_FILE) {
                return true;
            }
        }

        return false;
    }

    protected function json(array $data, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON($data);
    }

    protected function findOr404(Model $model, int|string $id, bool $withDeleted = false): array
    {
        $row = $withDeleted ? $model->withDeleted()->find($id) : $model->find($id);

        if (! is_array($row)) {
            throw PageNotFoundException::forPageNotFound('ไม่พบรายการนี้ (อาจถูกลบไปแล้ว)');
        }

        return $row;
    }

    protected function perPage(): int
    {
        $value = (int) $this->request->getGet('per_page');

        return in_array($value, self::PER_PAGE_OPTIONS, true) ? $value : $this->defaultPerPage;
    }

    /**
     * แบ่งหน้าจาก model ที่ตั้งเงื่อนไขไว้แล้ว
     *
     * @return array{rows: list<array>, total: int, page: int, perPage: int, pages: int}
     */
    protected function paginate(Model $model, ?int $perPage = null): array
    {
        $perPage = $perPage ?? $this->perPage();
        $total   = $model->countAllResults(false);
        $pages   = max(1, (int) ceil($total / $perPage));
        $page    = min(max(1, (int) $this->request->getGet('page')), $pages);
        $rows    = $model->findAll($perPage, ($page - 1) * $perPage);

        return compact('rows', 'total', 'page', 'perPage', 'pages');
    }

    /** ค่าคำค้น (ตัดช่องว่าง, จำกัดความยาว) */
    protected function searchTerm(): string
    {
        return mb_substr(trim((string) $this->request->getGet('q')), 0, 100);
    }

    /** ค่า query string ปัจจุบันที่อนุญาต (ใช้สร้างลิงก์แท็บ/หน้า) */
    protected function query(array $allowed): array
    {
        $result = [];

        foreach ($allowed as $key) {
            $value = $this->request->getGet($key);

            if (is_string($value) && $value !== '') {
                $result[$key] = mb_substr($value, 0, 100);
            }
        }

        return $result;
    }

    /** ตรวจข้อมูลด้วยกฎของ CodeIgniter (ข้อความภาษาไทย) */
    protected function check(array $data, array $rules, array $messages = []): array
    {
        $validation = service('validation', null, false);
        $validation->setRules($rules, $messages);

        return $validation->run($data) ? [] : $validation->getErrors();
    }

    /** ลิงก์ http/https เท่านั้น */
    protected static function isHttpUrl(?string $url): bool
    {
        if ($url === null) {
            return false;
        }

        return (bool) preg_match('~^https?://[^\s/$.?#][^\s]*$~i', $url) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
