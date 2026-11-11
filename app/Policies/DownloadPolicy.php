<?php

namespace App\Policies;

class DownloadPolicy extends PagePermissionPolicy
{
    protected string $pageCode = 'downloads';
}
