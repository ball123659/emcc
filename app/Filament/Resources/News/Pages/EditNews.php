<?php

namespace App\Filament\Resources\News\Pages;

use App\Filament\Resources\News\NewsResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditNews extends EditRecord
{
    protected static string $resource = NewsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->modalDescription('ข่าวจะหายจากหน้าเว็บ และกู้คืนได้จากตัวกรอง "รายการที่ถูกลบ"'),
            RestoreAction::make(),
        ];
    }
}
