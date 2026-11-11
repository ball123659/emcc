<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * รูปโปรไฟล์ตัวอักษรย่อ สร้างในเครื่อง (แทน ui-avatars.com — ไม่ส่งชื่อผู้ใช้ออกนอกระบบ และใช้ได้ในเครือข่ายปิด)
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $name = trim(Filament::getNameForDefaultAvatar($record));
        $initial = mb_strtoupper(mb_substr($name !== '' ? $name : '?', 0, 1));

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#704a9f"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#ffffff" font-family="Prompt, sans-serif" font-size="30">'
            .htmlspecialchars($initial, ENT_QUOTES | ENT_XML1)
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
