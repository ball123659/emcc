<?php

namespace App\Controllers\Backend;

class Monitoring extends BackendController
{
    public function index()
    {
        return view('backend/monitoring', ['title' => 'จุดตรวจวัด']);
    }
}
