<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Emcc;

/**
 * Security headers ทุก response + บังคับ HTTPS ในโปรดักชัน
 * - ห้ามฝังหน้าเว็บใน iframe ของเว็บอื่น (clickjacking)
 * - ไม่บอกเวอร์ชัน PHP (X-Powered-By)
 * - หลังบ้านห้ามติดผลค้นหา (X-Robots-Tag)
 */
class SecurityHeaders implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (ENVIRONMENT !== 'production' || ! config(Emcc::class)->forceHttps || $request->isSecure()) {
            return null;
        }

        // อยู่หลัง proxy ที่ทำ HTTPS ให้แล้วแต่ยังไม่ได้ตั้ง app.proxyIPs -> ห้าม redirect (จะวนไม่จบ)
        if (strtolower($request->getHeaderLine('X-Forwarded-Proto')) === 'https') {
            log_message('warning', 'ได้รับคำขอผ่าน proxy ที่เป็น HTTPS แต่ยังไม่ได้ตั้ง app.proxyIPs — ข้ามการ redirect เพื่อกันวนไม่จบ (ตั้งค่าใน .env)');

            return null;
        }

        $uri = $request->getUri();

        return service('response')->redirect('https://' . $uri->getAuthority() . '/' . ltrim($uri->getPath(), '/') . ($uri->getQuery() !== '' ? '?' . $uri->getQuery() : ''), 'auto', 301);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (function_exists('header_remove') && ! headers_sent()) {
            header_remove('X-Powered-By');
        }

        $response->removeHeader('X-Powered-By');
        $response->setHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->setHeader('Cross-Origin-Opener-Policy', 'same-origin');

        if (! $response->hasHeader('Content-Security-Policy')) {
            $response->setHeader('Content-Security-Policy', "frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self'");
        }

        $path = trim($request->getUri()->getPath(), '/');

        if ($path === 'backend' || str_starts_with($path, 'backend/')) {
            $response->setHeader('X-Robots-Tag', 'noindex, nofollow');
            // หน้าหลังบ้านมีข้อมูลส่วนตัว ห้าม cache (กดย้อนกลับหลังออกจากระบบแล้วต้องไม่เห็นข้อมูล)
            $response->removeHeader('Cache-Control');
            $response->setHeader('Cache-Control', 'no-store, private');
        }

        if ($request->isSecure()) {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
