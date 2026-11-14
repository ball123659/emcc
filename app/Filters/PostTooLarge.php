<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class PostTooLarge implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (strtolower($request->getMethod()) !== 'post') {
            return null;
        }

        $length = (int) $request->getServer('CONTENT_LENGTH');
        $limit  = ini_bytes((string) ini_get('post_max_size'));

        if ($limit <= 0 || $length <= $limit) {
            return null;
        }

        $message = 'ไฟล์ที่แนบรวมกันใหญ่เกิน ' . mb_label(intdiv($limit, 1024)) . ' ระบบจึงรับไม่ได้';
        $text    = 'กรุณาลดขนาดไฟล์ หรือแบ่งอัปโหลดทีละน้อยไฟล์ แล้วกรอกฟอร์มอีกครั้ง';

        if ($request->isAJAX() || str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return service('response')->setStatusCode(413)->setJSON(['message' => $message . ' ' . $text]);
        }

        session()->setFlashdata('toast', ['type' => 'danger', 'title' => $message, 'text' => $text]);

        return redirect()->to(site_url(Csrf::safeBackPath($request)));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
