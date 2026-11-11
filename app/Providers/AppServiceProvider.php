<?php

namespace App\Providers;

use App\Services\VirusScan\VirusScanner;
use App\Services\VirusScan\VirusScannerFactory;
use App\Support\ActivityLogger;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VirusScanner::class, fn () => VirusScannerFactory::make());
    }

    public function boot(): void
    {
        // บังคับ HTTPS ในโปรดักชัน
        URL::forceHttps($this->app->isProduction());

        // ช่วยจับบั๊กตอนพัฒนา (lazy loading / ตั้งค่าฟิลด์ที่ไม่มีใน fillable)
        Model::shouldBeStrict(! $this->app->isProduction());

        // บันทึกการออกจากระบบ
        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user) {
                ActivityLogger::log('logout', $event->user, 'ออกจากระบบ', userId: $event->user->getAuthIdentifier());
            }
        });
    }
}
