<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * สิทธิ์รายหน้า (เห็น / ไม่เห็น) ตรวจฝั่งเซิร์ฟเวอร์ทุก request — ใช้ในเส้นทาง เช่น ['filter' => 'page:news']
 */
class PagePermission implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        foreach ((array) $arguments as $code) {
            if (! service('auth')->canViewPage($code)) {
                return Forbidden::response($request);
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
