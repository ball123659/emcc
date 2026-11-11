<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers ทุก response + บังคับ HTTPS ในโปรดักชัน
 * - ห้ามฝังหน้าเว็บใน iframe ของเว็บอื่น (clickjacking)
 * - ไม่บอกเวอร์ชัน PHP (X-Powered-By)
 * - CSP ไม่จำกัด script/style เพราะ Filament/Livewire/Alpine ต้องใช้ inline (ยังคุม frame/object/base/form ได้)
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction() && ! $request->isSecure() && config('emcc.force_https')) {
            return redirect()->to('https://'.$request->getHttpHost().$request->getRequestUri(), 301);
        }

        $response = $next($request);

        if (function_exists('header_remove') && ! headers_sent()) {
            header_remove('X-Powered-By');
        }

        $headers = $response->headers;
        $headers->remove('X-Powered-By');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', "frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self'");
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
