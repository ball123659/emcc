<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\User;
use App\Support\ActivityLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class UserActions
{
    /** รีเซ็ตรหัสผ่านกลับเป็นค่าเริ่มต้น + บังคับเปลี่ยนรหัส + ปลดล็อก */
    public static function resetPassword(): Action
    {
        return Action::make('resetPassword')
            ->label('รีเซ็ตรหัสผ่าน')
            ->icon(Heroicon::OutlinedKey)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(fn (User $record) => "รีเซ็ตรหัสผ่านของ {$record->username}?")
            ->modalDescription('รหัสผ่านจะกลับเป็นค่าเริ่มต้น "'.config('emcc.default_password').'" และผู้ใช้ต้องตั้งรหัสใหม่ตอนเข้าสู่ระบบครั้งถัดไป')
            ->modalSubmitActionLabel('รีเซ็ตรหัสผ่าน')
            ->visible(fn (User $record) => Gate::allows('resetPassword', $record))
            ->action(function (User $record): void {
                Gate::authorize('resetPassword', $record);

                $record->forceFill([
                    'password' => config('emcc.default_password'),
                    'must_change_password' => true,
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ])->save();

                ActivityLogger::log('password_reset', $record, "รีเซ็ตรหัสผ่านของ {$record->username}");

                Notification::make()->success()
                    ->title('รีเซ็ตรหัสผ่านแล้ว')
                    ->body('รหัสผ่านเริ่มต้นคือ "'.config('emcc.default_password').'" แจ้งผู้ใช้ให้เข้าสู่ระบบแล้วตั้งรหัสใหม่')
                    ->send();
            });
    }

    /** ปลดล็อกบัญชีที่ถูกล็อกจากการใส่รหัสผิด */
    public static function unlock(): Action
    {
        return Action::make('unlock')
            ->label('ปลดล็อก')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(fn (User $record) => "ปลดล็อกบัญชี {$record->username}?")
            ->visible(fn (User $record) => $record->isLocked() && Gate::allows('resetPassword', $record))
            ->action(function (User $record): void {
                Gate::authorize('resetPassword', $record);

                $record->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->saveQuietly();
                ActivityLogger::log('unlock', $record, "ปลดล็อกบัญชี {$record->username}");

                Notification::make()->success()->title('ปลดล็อกบัญชีแล้ว')->send();
            });
    }
}
