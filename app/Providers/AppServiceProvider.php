<?php

namespace App\Providers;

use App\Services\VirusScan\VirusScanner;
use App\Services\VirusScan\VirusScannerFactory;
use App\Support\ActivityLogger;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // php artisan serve บน Windows: ส่ง TEMP/TMP ให้เซิร์ฟเวอร์ลูกด้วย ไม่งั้นเมื่อ php.ini ไม่ได้ตั้ง
        // upload_tmp_dir PHP จะหาโฟลเดอร์ชั่วคราวไม่เจอ และอัปโหลดไฟล์ไม่ได้ ("unable to create a temporary file")
        ServeCommand::$passthroughVariables = array_values(array_unique([
            ...ServeCommand::$passthroughVariables, 'TEMP', 'TMP', 'TMPDIR',
        ]));

        $this->app->bind(VirusScanner::class, fn () => VirusScannerFactory::make());
    }

    public function boot(): void
    {
        // Livewire เก็บไฟล์อัปโหลดชั่วคราวก่อน (ค่าเริ่มต้นจำกัด 12 MB) — ให้เท่ากับขนาดไฟล์สูงสุดของระบบ
        config([
            'livewire.temporary_file_upload.rules' => ['required', 'file', 'max:'.max(
                (int) config('emcc.files.max_upload_kb'),
                (int) config('emcc.images.max_upload_kb'),
            )],
        ]);

        // รูปแบบวันที่เดียวกันทั้งหลังบ้าน (ค.ศ.): วันที่ 25/11/2026 · วันเวลา 25/11/2026 14:30
        $date = (string) config('emcc.date_format');
        $dateTime = (string) config('emcc.datetime_format');
        Table::configureUsing(fn (Table $table) => $table->defaultDateDisplayFormat($date)->defaultDateTimeDisplayFormat($dateTime));
        Schema::configureUsing(fn (Schema $schema) => $schema->defaultDateDisplayFormat($date)->defaultDateTimeDisplayFormat($dateTime));
        DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker->defaultDateDisplayFormat($date)->defaultDateTimeDisplayFormat($dateTime)->native(false)->closeOnDateSelection()->firstDayOfWeek(1));

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
