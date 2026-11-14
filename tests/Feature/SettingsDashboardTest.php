<?php

namespace Tests\Feature;

use App\Models\SiteSettingModel;
use Tests\Support\TestCase;

final class SettingsDashboardTest extends TestCase
{
    public function testSettingsSaveAndValidation(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('backend/site-settings')->assertSee('ข้อมูลทั่วไป');

        $this->actingAs($admin)->post('backend/site-settings', ['settings' => [
            'email' => ['th' => 'not-email'], 'facebook_url' => ['th' => 'ftp://x'], 'phone' => ['th' => 'โทร 038'],
        ]]);
        $errors = session('errors');
        $this->assertStringContainsString('อีเมล', $errors['settings.email.th']);
        $this->assertStringContainsString('http://', $errors['settings.facebook_url.th']);
        $this->assertStringContainsString('ตัวเลข', $errors['settings.phone.th']);

        $this->actingAs($admin)->post('backend/site-settings', ['_tab' => 'contact', 'settings' => [
            'site_name' => ['th' => ' ศูนย์ EMCC ', 'en' => ''], 'email' => ['th' => ' info@emcc.example '], 'phone' => ['th' => '038-683933 / 081-7323485'],
        ]])->assertRedirectTo(site_url('backend/site-settings?tab=contact'));

        $this->assertSame('ศูนย์ EMCC', SiteSettingModel::value('site_name', null, 'th'));
        $this->assertSame('ศูนย์ EMCC', SiteSettingModel::value('site_name', null, 'en'), 'ภาษาอังกฤษว่าง = ใช้ไทย');
        $this->assertSame('info@emcc.example', SiteSettingModel::value('email'));
        $this->get('/')->assertSee('info@emcc.example');
    }

    public function testDashboardSummaryRequiresDashboardPermission(): void
    {
        $plain = $this->makeUser();
        $res   = $this->actingAs($plain)->get('backend');
        $res->assertOK();
        $res->assertSee('ยินดีต้อนรับ');
        $res->assertDontSee('ข่าวที่เผยแพร่แล้ว');

        $viewer = $this->makeUser([], ['dashboard', 'news']);
        $res    = $this->actingAs($viewer)->get('backend');
        $res->assertSee('ข่าวที่เผยแพร่แล้ว');
        $res->assertSee('เพิ่มข่าว');
        $res->assertDontSee('ความเคลื่อนไหวล่าสุด');

        $admin = $this->admin();
        $this->actingAs($admin)->get('backend')->assertSee('ความเคลื่อนไหวล่าสุด');
    }

    public function testGlobalSearchRespectsPermissions(): void
    {
        (new \App\Models\NewsModel())->insert(['category_id' => 1, 'title_th' => 'ข่าวฝุ่นละออง', 'content_th' => '<p>x</p>', 'cover_image' => 'news/x.webp']);
        $user = $this->makeUser([], ['downloads']);
        $this->actingAs($user)->get('backend/search?q=' . urlencode('ฝุ่น'))->assertJSONExact(['groups' => []]);

        $admin = $this->admin();
        $this->actingAs($admin)->get('backend/search?q=' . urlencode('ฝุ่น'))->assertSee('ข่าวฝุ่นละออง');
    }
}
