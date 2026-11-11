<?php

namespace App\Policies;

use App\Models\DownloadCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * หมวดหมู่ไฟล์ดาวน์โหลด — ใช้สิทธิ์หน้า downloads; หมวดที่ยังมีไฟล์ลบไม่ได้
 */
class DownloadCategoryPolicy extends PagePermissionPolicy
{
    protected string $pageCode = 'downloads';

    public function delete(User $user, Model $model): bool
    {
        /** @var DownloadCategory $model */
        return $this->allowed($user) && ! $model->hasDownloads();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
