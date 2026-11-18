<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ตั้งภาษาหน้าบ้านจาก cookie (ไม่ใช้ session — หน้าบ้านไม่สร้าง session cookie)
 */
class SiteLocale implements FilterInterface
{
    public const COOKIE = 'emcc_lang';

    public function before(RequestInterface $request, $arguments = null)
    {
        if ($request instanceof IncomingRequest) {
            $lang = $request->getCookie(self::COOKIE);
            $request->setLocale($lang === 'en' ? 'en' : 'th');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
