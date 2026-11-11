<?php

namespace App\View\Components\Site;

use App\Models\Popup;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

/**
 * Popup หน้าเว็บ: <x-site.popups />
 * แสดง popup ที่อยู่ในช่วงวันที่ต่อกันทีละอันตามลำดับ (ปิดอันแรก -> อันถัดไป)
 * ติ๊ก "ไม่ต้องแสดงอีกวันนี้" เก็บใน localStorage ของเบราว์เซอร์ (ไม่ลงฐานข้อมูล)
 */
class Popups extends Component
{
    public Collection $popups;

    public function __construct()
    {
        $this->popups = Popup::query()->visible()->get();
    }

    public function shouldRender(): bool
    {
        return $this->popups->isNotEmpty();
    }

    public function render(): View
    {
        return view('components.site.popups');
    }
}
