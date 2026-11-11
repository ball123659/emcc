<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Support\InitialsAvatarProvider;
use App\Http\Middleware\EnsurePasswordIsChanged;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('backend')
            ->login(Login::class)
            ->profile(EditProfile::class, isSimple: false)
            ->revealablePasswords()
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => view('filament.filepond-thai')->render())
            // ธีม EMCC (CSS ล้วน ไม่ต้อง build) — ใส่ ?v= ตามเวลาแก้ไฟล์เพื่อไม่ให้ติด cache
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): string => '<link rel="stylesheet" href="'.asset('css/emcc/admin.css').'?v='.@filemtime(public_path('css/emcc/admin.css')).'">')
            ->renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, fn (): string => view('filament.auth-brand')->render(), scopes: Login::class)
            ->maxContentWidth(Width::Full) // ใช้พื้นที่จอเต็ม (ซ่อนเมนูข้างแล้วเนื้อหากว้างขึ้นจริง)
            ->sidebarFullyCollapsibleOnDesktop() // ซ่อนเมนูข้างได้ทั้งหมดเพื่อขยายพื้นที่เนื้อหา (ระบบจำสถานะไว้)
            ->navigationGroups([
                'จัดการ Highlight',
                'ข่าวประชาสัมพันธ์',
                'ไฟล์ดาวน์โหลด',
                'ตั้งค่า',
                'ผู้ดูแลระบบ',
            ])
            ->brandName('EMCC Backend')
            ->font('Prompt', url: asset('fonts/prompt/prompt.css'), provider: LocalFontProvider::class)
            ->favicon(asset('favicon.svg'))
            ->colors([
                'primary' => Color::hex('#704a9f'),
                'success' => Color::hex('#0e975f'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsurePasswordIsChanged::class,
            ]);
    }
}
