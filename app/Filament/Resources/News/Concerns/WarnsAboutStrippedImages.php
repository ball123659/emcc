<?php

namespace App\Filament\Resources\News\Concerns;

use Filament\Notifications\Notification;

/**
 * เนื้อหาข่าวที่มีรูปวาง/ลากลงมา จะถูกตัวกรอง HTML นำรูปออก — แจ้งผู้ใช้ให้ใช้แกลเลอรีแทน (ไม่ให้หายเงียบ)
 */
trait WarnsAboutStrippedImages
{
    protected function afterValidate(): void
    {
        $content = json_encode([$this->data['content_th'] ?? null, $this->data['content_en'] ?? null]);

        if (str_contains((string) $content, '<img') || str_contains((string) $content, '"type":"image"')) {
            Notification::make()
                ->warning()
                ->title('รูปในเนื้อหาข่าวถูกนำออก')
                ->body('ระบบไม่รับรูปที่วางในเนื้อหา กรุณาเพิ่มรูปในส่วน "แกลเลอรีรูป" แทน')
                ->persistent()
                ->send();
        }
    }
}
