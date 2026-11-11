<?php

namespace App\Policies;

use App\Models\NewsCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * หมวดหมู่ข่าว — ใช้สิทธิ์หน้า news; หมวดที่ยังมีข่าวลบไม่ได้
 */
class NewsCategoryPolicy extends PagePermissionPolicy
{
    protected string $pageCode = 'news';

    public function delete(User $user, Model $model): bool
    {
        /** @var NewsCategory $model */
        return $this->allowed($user) && ! $model->hasNews();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
