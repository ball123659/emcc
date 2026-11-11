<?php

namespace App\Filament\Resources\Concerns;

use App\Models\News;
use App\Models\Popup;
use App\Services\Images\ImageProcessor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ปุ่มลัดในแถวตาราง — ทำงานบ่อยได้ในคลิกเดียว ไม่ต้องเข้าหน้าแก้ไข
 */
class QuickActions
{
    /** ข่าว: เผยแพร่ทันที (ร่าง / ซ่อน / ตั้งเวลา) */
    public static function publishNow(): Action
    {
        return Action::make('publishNow')
            ->label('เผยแพร่ทันที')
            ->icon(Heroicon::OutlinedRocketLaunch)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(fn (News $record) => 'เผยแพร่ "'.Str::limit($record->title_th, 60).'" ตอนนี้?')
            ->modalDescription('ข่าวจะแสดงบนหน้าเว็บทันที (วันที่เผยแพร่ = วันนี้ ถ้ายังไม่เคยกำหนดหรือกำหนดเป็นอนาคต)')
            ->modalSubmitActionLabel('เผยแพร่')
            ->visible(fn (News $record) => ! $record->trashed() && $record->displayStatus() !== News::STATUS_PUBLISHED && Gate::allows('update', $record))
            ->action(function (News $record): void {
                Gate::authorize('update', $record);

                $record->update([
                    'status' => News::STATUS_PUBLISHED,
                    'published_at' => ($record->published_at === null || $record->published_at->isFuture()) ? today() : $record->published_at,
                ]);

                Notification::make()->success()->title('เผยแพร่ข่าวแล้ว')->send();
            });
    }

    /** ข่าว: ซ่อนจากหน้าเว็บ */
    public static function hide(): Action
    {
        return Action::make('hide')
            ->label('ซ่อนจากหน้าเว็บ')
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('ข่าวจะไม่แสดงบนหน้าเว็บ เปิดกลับได้ด้วยปุ่ม "เผยแพร่ทันที"')
            ->modalSubmitActionLabel('ซ่อน')
            ->visible(fn (News $record) => ! $record->trashed() && $record->status === News::STATUS_PUBLISHED && Gate::allows('update', $record))
            ->action(function (News $record): void {
                Gate::authorize('update', $record);
                $record->update(['status' => News::STATUS_HIDDEN]);
                Notification::make()->success()->title('ซ่อนข่าวแล้ว')->send();
            });
    }

    /** Popup: ต่ออายุ +7 วัน (นับจากวันสิ้นสุดเดิม หรือวันนี้ถ้าหมดอายุไปแล้ว) */
    public static function extendPopup(int $days = 7): Action
    {
        return Action::make('extend')
            ->label("ต่ออายุ +{$days} วัน")
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('info')
            ->visible(fn (Popup $record) => ! $record->trashed() && Gate::allows('update', $record))
            ->action(function (Popup $record) use ($days): void {
                Gate::authorize('update', $record);

                $from = $record->end_date->lt(today()) ? today() : $record->end_date;
                $record->update(['end_date' => $from->copy()->addDays($days)]);

                Notification::make()->success()
                    ->title('ต่ออายุแล้ว')
                    ->body('แสดงถึงวันที่ '.$record->end_date->format(config('emcc.date_format')))
                    ->send();
            });
    }

    /**
     * ทำสำเนา Slide / Popup — คัดลอกไฟล์รูปเป็นไฟล์ใหม่ (ไม่ใช้ไฟล์ร่วมกัน เพราะลบ/เปลี่ยนรูปของอันหนึ่งจะลบไฟล์)
     * สำเนาถูกปิดไว้ก่อน และต่อท้ายลำดับ
     */
    public static function duplicate(): Action
    {
        return Action::make('duplicate')
            ->label('ทำสำเนา')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('สร้างรายการใหม่จากรายการนี้ (สำเนาจะปิดไว้ก่อน แก้ไขแล้วค่อยเปิดแสดง)')
            ->modalSubmitActionLabel('ทำสำเนา')
            ->visible(fn (Model $record) => ! $record->trashed() && Gate::allows('create', $record::class))
            ->action(function (Model $record, Action $action): void {
                Gate::authorize('create', $record::class);

                $copy = $record->replicate(['created_by', 'updated_by', 'sort_order', 'created_at', 'updated_at', 'deleted_at']);
                $copy->title = Str::limit('สำเนา - '.$record->title, 200, '');
                $copy->is_active = false;
                $copy->sort_order = null;

                foreach ($record::imageFields() as $field) {
                    if ($path = $record->getAttribute($field)) {
                        $copy->setAttribute($field, self::copyImage($path));
                    }
                }

                $copy->save();

                Notification::make()->success()->title('ทำสำเนาแล้ว')->body('สำเนาถูกปิดไว้ แก้ไขแล้วเปิดแสดงได้เลย')->send();
            });
    }

    private static function copyImage(string $path): string
    {
        $disk = Storage::disk(ImageProcessor::DISK);
        $directory = Str::beforeLast($path, '/');
        $newPath = $directory.'/'.Str::uuid()->toString().'.webp';

        if ($disk->exists($path)) {
            $disk->copy($path, $newPath);
        }

        return $newPath;
    }
}
