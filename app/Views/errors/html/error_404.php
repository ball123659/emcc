<?php
$path      = trim(service('request')->getUri()->getRoutePath(), '/');
$isBackend = $path === 'backend' || str_starts_with($path, 'backend/');
?><!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>ไม่พบหน้าที่ต้องการ</title>
    <link rel="stylesheet" href="<?= base_url('fonts/prompt/prompt.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>">
</head>
<body>
<main class="auth-main" style="min-height:100vh">
    <div class="auth-card" style="text-align:center">
        <div class="error-page" >
            <div>
                <div class="code">404</div>
                <h1 style="margin:.5rem 0 1rem">ไม่พบหน้าที่ต้องการ</h1>
                <p class="muted">ลิงก์อาจไม่ถูกต้อง หรือข้อมูลถูกลบไปแล้ว</p>
                <p style="margin-top:1.5rem"><a class="btn btn-primary" href="<?= site_url($isBackend ? 'backend' : '/') ?>"><?= $isBackend ? 'กลับหน้าแดชบอร์ด' : 'กลับหน้าแรก' ?></a></p>
            </div>
        </div>
    </div>
</main>
</body>
</html>
