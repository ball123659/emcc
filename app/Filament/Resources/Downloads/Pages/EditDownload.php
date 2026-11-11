<?php

namespace App\Filament\Resources\Downloads\Pages;

use App\Filament\Resources\Downloads\DownloadResource;
use App\Models\Download;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * @property Download $record
 */
class EditDownload extends EditRecord
{
    protected static string $resource = DownloadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')
                ->label(fn () => $this->record->isFile() ? 'ทดลองดาวน์โหลด' : 'เปิดลิงก์')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn () => $this->record->publicUrl(), shouldOpenInNewTab: true),
            DeleteAction::make()->modalDescription('รายการจะหายจากหน้าเว็บ และกู้คืนได้จากตัวกรอง "รายการที่ถูกลบ"'),
            RestoreAction::make(),
        ];
    }
}
