<?php

namespace Tests\Unit;

use App\Filters\SecurityHeaders;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;

final class HttpsAuditTest extends CIUnitTestCase
{
    public function testUntrustedForwardedProtoCannotBypassHttps(): void
    {
        $config = new \Config\App();
        $config->baseURL = 'http://localhost/';
        $config->proxyIPs = [];
        $request = new IncomingRequest($config, new SiteURI($config, 'backend/login'), null, new UserAgent());
        $request->setHeader('X-Forwarded-Proto', 'https');
        $request->setGlobal('server', ['REMOTE_ADDR' => '203.0.113.5', 'HTTPS' => 'off']);
        $filter = new class extends SecurityHeaders {
            protected function enforceHttps(): bool
            {
                return true;
            }
        };
        $response = $filter->before($request);
        $this->assertNotNull($response);
        $this->assertSame('https://localhost/backend/login', $response->getHeaderLine('Location'));
    }
    public function testTrustedProxyHttpsDoesNotRedirect(): void
    {
        $config = new \Config\App();
        $config->baseURL = 'http://localhost/';
        $config->proxyIPs = ['10.0.0.5' => 'X-Forwarded-For'];
        $request = new IncomingRequest($config, new SiteURI($config, 'backend/login'), null, new UserAgent());
        $request->setHeader('X-Forwarded-Proto', 'https');
        $request->setGlobal('server', ['REMOTE_ADDR' => '10.0.0.5', 'HTTPS' => 'off']);
        $filter = new class extends SecurityHeaders {
            protected function enforceHttps(): bool
            {
                return true;
            }
        };
        $this->assertTrue($request->isSecure());
        $this->assertNull($filter->before($request));
    }
}
