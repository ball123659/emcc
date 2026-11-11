<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ChecksPagePermission;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * จุดตรวจวัด (pages.code = monitoring) — ระบบแผนที่/จุดตรวจวัดเป็นงานของ dev อีกคน
 * หน้านี้เว้นเมนูไว้ก่อน (ตาราง map_* ห้ามแก้ในโปรเจกต์นี้)
 */
class MonitoringPoints extends Page
{
    use ChecksPagePermission;

    protected static string $pageCode = 'monitoring';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'จุดตรวจวัด';

    protected static ?string $title = 'จุดตรวจวัด';

    protected static ?string $slug = 'monitoring';

    protected static ?int $navigationSort = 10;

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make('อยู่ระหว่างพัฒนา')
                ->description('ระบบจัดการจุดตรวจวัดและแผนที่กำลังพัฒนาโดยทีมระบบแผนที่ จะเปิดใช้งานในเมนูนี้เมื่อพร้อม')
                ->info(),
        ]);
    }
}
