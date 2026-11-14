<?php

namespace App\Controllers;

/** หน้าแรกชั่วคราว (รอทำหน้าบ้านจาก template) */
class Home extends BaseController
{
    public function index(): string
    {
        return view('welcome');
    }
}
