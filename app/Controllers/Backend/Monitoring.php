<?php

namespace App\Controllers\Backend;

/**
 * จุดตรวจวัด (pages.code = monitoring) — ระบบแผนที่/จุดตรวจวัดเป็นงานของ dev อีกคน
 * หน้านี้เว้นเมนูไว้ก่อน (ตาราง map_* ห้ามแก้ในโปรเจกต์นี้)
 */
class Monitoring extends BackendController
{
    public function index()
    {
        return view('backend/monitoring', ['title' => 'จุดตรวจวัด']);
    }
}
