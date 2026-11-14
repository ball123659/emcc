<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Forbidden
{
    public const MESSAGE = 'คุณไม่มีสิทธิ์เข้าใช้งานหน้านี้ หากต้องการใช้งาน กรุณาติดต่อผู้ดูแลระบบ';

    public static function response(RequestInterface $request): ResponseInterface
    {
        $response = service('response')->setStatusCode(403);

        if ($request->isAJAX() || str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return $response->setJSON(['message' => self::MESSAGE]);
        }

        return $response->setBody(view('errors/backend', ['code' => 403, 'title' => 'ไม่มีสิทธิ์เข้าถึง', 'message' => self::MESSAGE]));
    }
}
