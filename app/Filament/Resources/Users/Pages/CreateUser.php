<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\UserPermissions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected array $pagePermissions = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pagePermissions = $data['page_permissions'] ?? [];
        unset($data['page_permissions']);

        // ผู้ใช้ใหม่ได้รหัสเริ่มต้น และต้องเปลี่ยนตอนล็อกอินครั้งแรก
        $data['password'] = config('emcc.default_password');
        $data['must_change_password'] = true;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->record;

        UserPermissions::sync($user, $this->pagePermissions);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'เพิ่มผู้ใช้งานแล้ว — รหัสผ่านเริ่มต้นคือ "'.config('emcc.default_password').'"';
    }
}
