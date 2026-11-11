<?php

namespace App\Policies;

class NewsPolicy extends PagePermissionPolicy
{
    protected string $pageCode = 'news';
}
