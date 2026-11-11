<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // บังคับ HTTPS ในโปรดักชัน
        URL::forceHttps($this->app->isProduction());

        // ช่วยจับบั๊กตอนพัฒนา (lazy loading / ตั้งค่าฟิลด์ที่ไม่มีใน fillable)
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
