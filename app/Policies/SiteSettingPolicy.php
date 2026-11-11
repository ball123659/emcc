<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ตั้งค่าเว็บไซต์ — แก้ค่าได้อย่างเดียว (เพิ่ม/ลบคีย์ทำผ่าน seeder โดย developer)
 */
class SiteSettingPolicy extends PagePermissionPolicy
{
    protected string $pageCode = 'site_settings';

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
