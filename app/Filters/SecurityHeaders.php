<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Emcc;

class SecurityHeaders implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $this->enforceHttps() || $request->isSecure()) {
            return null;
        }

        $uri = $request->getUri();

        return service('response')->redirect('https://' . $uri->getAuthority() . '/' . ltrim($uri->getPath(), '/') . ($uri->getQuery() !== '' ? '?' . $uri->getQuery() : ''), 'auto', 301);
    }

    protected function enforceHttps(): bool
    {
        return ENVIRONMENT === 'production' && config(Emcc::class)->forceHttps;
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

        $path = trim($request->getUri()->getRoutePath(), '/');

        if ($path === 'backend' || str_starts_with($path, 'backend/')) {
            $response->setHeader('X-Robots-Tag', 'noindex, nofollow');

            $response->removeHeader('Cache-Control');
            $response->setHeader('Cache-Control', 'no-store, private');
        }

        if ($request->isSecure()) {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
