<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Actions\UserActions;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\UserPermissions;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

/**
 * @property User $record
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected array $pagePermissions = [];

    protected function getHeaderActions(): array
    {
        return [
            UserActions::resetPassword(),
            UserActions::unlock(),
            DeleteAction::make()
                ->modalDescription('ผู้ใช้จะเข้าสู่ระบบไม่ได้ และกู้คืนได้ภายหลังจากตัวกรอง "รายการที่ถูกลบ"'),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['page_permissions'] = UserPermissions::selectedPageIds($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pagePermissions = $data['page_permissions'] ?? [];
        unset($data['page_permissions'], $data['password'], $data['must_change_password']);

        // ห้ามเปลี่ยนบทบาท / ปิดบัญชีของตัวเอง (ตรวจฝั่งเซิร์ฟเวอร์ ไม่ใช่แค่ปิดช่องในฟอร์ม)
        if ($this->record->is(Auth::user())) {
            unset($data['role'], $data['is_active']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        UserPermissions::sync($this->record->refresh(), $this->pagePermissions);
    }
}
