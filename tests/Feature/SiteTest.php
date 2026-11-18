<?php

namespace Tests\Feature;

use App\Models\NewsModel;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class SiteTest extends TestCase
{
    private function news(array $data = []): array
    {
        $id = (new NewsModel())->insert($data + [
            'category_id' => 1, 'title_th' => 'ข่าวทดสอบหน้าบ้าน', 'title_en' => 'Public news', 'content_th' => '<p>เนื้อหา</p><script>alert(1)</script>',
            'cover_image' => 'news/x.webp', 'status' => NewsModel::STATUS_PUBLISHED, 'published_at' => date('Y-m-d'),
        ]);

        return (new NewsModel())->find($id);
    }

    public function testPublicPagesRenderWithoutSessionCookie(): void
    {
        foreach (['/', 'news', 'about', 'contact', 'downloads'] as $path) {
            $response = $this->get($path);
            $this->assertSame(200, self::code($response), $path);
            $this->assertStringNotContainsString('ci_session', (string) $response->response()->getHeaderLine('Set-Cookie'), $path);
        }
    }

    public function testPublishedNewsIsShownAndDraftIsHidden(): void
    {
        $news  = $this->news();
        $draft = $this->news(['title_th' => 'ข่าวร่างลับ', 'status' => NewsModel::STATUS_DRAFT]);

        $list = $this->get('news');
        $list->assertSee('ข่าวทดสอบหน้าบ้าน');
        $list->assertDontSee('ข่าวร่างลับ');

        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Firefox/130';
        $show = $this->get('news/' . $news['slug']);
        unset($_SERVER['HTTP_USER_AGENT']);
        $this->assertSame(200, self::code($show));
        $show->assertDontSee('<script>alert(1)</script>', null);
        $this->assertSame(1, (int) db_connect()->table('news')->where('id', $news['id'])->get()->getRow()->view_count);

        $this->assertNotFound(fn () => $this->get('news/' . $draft['slug']));
        $this->assertNotFound(fn () => $this->get('news?category=no-such-category'));
    }

    public function testDraftPreviewForStaffWithPermission(): void
    {
        $draft = $this->news(['title_th' => 'ข่าวร่างดูตัวอย่าง', 'status' => NewsModel::STATUS_DRAFT]);
        $staff = $this->makeUser([], ['news']);

        $response = $this->actingAs($staff)->get('news/' . $draft['slug']);
        $this->assertSame(200, self::code($response));
        $response->assertSee('ตัวอย่างสำหรับเจ้าหน้าที่');
    }

    public function testLanguageSwitchUsesCookieAndEnglishFallsBackToThai(): void
    {
        $this->news();
        $this->news(['title_th' => 'ข่าวไม่มีอังกฤษ', 'title_en' => null]);

        $response = $this->get('lang/en');
        $this->assertSame(302, self::code($response));
        $this->assertSame('en', $response->response()->getCookie('emcc_lang')->getValue());

        $_COOKIE['emcc_lang'] = 'en';
        $en = $this->get('news');
        $en->assertSee('News &amp; Activities', null);
        $en->assertSee('Public news');
        $en->assertSee('ข่าวไม่มีอังกฤษ');
    }

    public function testLanguageSwitchDoesNotRedirectOffSite(): void
    {
        $response = $this->withHeaders(['Referer' => 'https://evil.example/x'])->get('lang/th');
        $this->assertSame(site_url('/'), $response->response()->getHeaderLine('Location'));
    }
}
