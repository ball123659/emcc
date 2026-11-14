<?php

namespace Tests\Feature;

use App\Libraries\HtmlSanitizer;
use App\Models\NewsModel;
use Config\Emcc;
use Tests\Support\TestCase;

final class SecurityTest extends TestCase
{
    public function testSecurityHeadersOnEveryResponse(): void
    {
        $res = $this->get('backend/login');
        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $res->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringContainsString("frame-ancestors 'self'", $res->response()->getHeaderLine('Content-Security-Policy'));

        $home = $this->get('/');
        $home->assertOK();
        $home->assertSee('เว็บไซต์กำลังปรับปรุง');
        $this->assertFalse($home->response()->hasHeader('X-Robots-Tag'));
        $this->assertStringContainsString('Disallow: /backend', (string) file_get_contents(FCPATH . 'robots.txt'));
    }

    public function testSessionCookieIsHttpOnlyAndSecureInProduction(): void
    {
        $this->assertTrue(config('Cookie')->httponly);
        $this->assertSame(ENVIRONMENT === 'production', config('Cookie')->secure);
        $this->assertStringContainsString("ENVIRONMENT === 'production'", (string) file_get_contents(APPPATH . 'Config/Cookie.php'));
        $this->assertSame('session', config('Security')->csrfProtection);
    }

    public function testRichTextIsSanitized(): void
    {
        $dirty = '<p onclick="x()">ข้อความ<script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">ลิงก์</a><a href="https://ieat.go.th">ok</a></p>';
        $clean = HtmlSanitizer::clean($dirty);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<img', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
        $this->assertTrue(HtmlSanitizer::containsImage($dirty));
    }

    public function testOutputIsEscaped(): void
    {
        $admin = $this->admin();
        (new NewsModel())->insert(['category_id' => 1, 'title_th' => '<script>alert("x")</script>', 'content_th' => '<p>x</p>', 'cover_image' => 'news/x.webp']);
        $res = $this->actingAs($admin)->get('backend/news');
        $res->assertDontSee('<script>alert("x")</script>');
        $res->assertSee('&lt;script&gt;');
    }

    public function testMediaRouteOnlyServesProcessedImages(): void
    {
        $this->assertNotFound(fn () => $this->get('media/slides/../../.env'));
        $this->assertNotFound(fn () => $this->get('media/slides/11111111-1111-4111-8111-111111111111.webp'));

        $this->assertNotFound(fn () => $this->get('downloads/99999999999999999999'));
        $this->assertNotFound(fn () => $this->get('downloads/0'));
        $this->assertNotFound(fn () => $this->get('downloads/abc'));
        $this->assertNotFound(fn () => $this->get('news/attachments/99999999999999999999'));
    }

    public function testNonProductionRequestsDoNotForceHttps(): void
    {
        $filter = new \App\Filters\SecurityHeaders();
        $this->assertNull($filter->before(service('request')));
    }
}
