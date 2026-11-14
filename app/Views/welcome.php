<?php

$siteName = 'ศูนย์เฝ้าระวังและควบคุมคุณภาพสิ่งแวดล้อม';
$phone    = $email = null;

try {
    $siteName = \App\Models\SiteSettingModel::value('site_name', $siteName, 'th');
    $phone    = \App\Models\SiteSettingModel::value('phone', null, 'th');
    $email    = \App\Models\SiteSettingModel::value('email', null, 'th');
} catch (\Throwable) {

}
?><!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($siteName) ?></title>
    <meta name="description" content="<?= esc($siteName, 'attr') ?> (EMCC) — เว็บไซต์กำลังปรับปรุง">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= base_url('fonts/prompt/prompt.css') ?>">
    <style>
        :root { --primary: #704a9f; --green: #0e975f; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px;
               font-family: 'Prompt', system-ui, sans-serif; color: #fff;
               background: radial-gradient(circle at 85% 15%, rgba(255,255,255,.12) 0 16%, transparent 17%),
                           radial-gradient(circle at 10% 95%, rgba(14,151,95,.45) 0 22%, transparent 23%),
                           linear-gradient(150deg, #2a1b45 0%, var(--primary) 75%, #8a5fbf 100%); }
        main { max-width: 640px; text-align: center; }
        .logo { width: 72px; height: 72px; border-radius: 18px; margin: 0 auto 24px; }
        h1 { font-size: clamp(1.5rem, 4vw, 2.2rem); font-weight: 700; line-height: 1.4; margin: 0 0 8px; }
        .en { color: rgba(255,255,255,.75); margin: 0 0 28px; }
        .badge { display: inline-block; background: var(--green); padding: 8px 18px; border-radius: 999px; font-weight: 500; }
        p { line-height: 1.8; color: rgba(255,255,255,.88); }
        .contact { margin-top: 28px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .contact a, .contact span { color: #fff; text-decoration: none; background: rgba(255,255,255,.14); padding: 8px 14px; border-radius: 10px; }
    </style>
</head>
<body>
<main>
    <img class="logo" src="<?= base_url('favicon.svg') ?>" alt="">
    <h1><?= esc($siteName) ?></h1>
    <p class="en">Environmental Monitoring &amp; Control Center (EMCC)</p>
    <span class="badge">เว็บไซต์กำลังปรับปรุง · Coming soon</span>
    <p>ขออภัยในความไม่สะดวก เว็บไซต์ใหม่จะเปิดให้บริการเร็ว ๆ นี้</p>
    <?php if ($phone || $email): ?>
        <div class="contact">
            <?php if ($phone): ?><span>โทร <?= esc($phone) ?></span><?php endif ?>
            <?php if ($email): ?><a href="mailto:<?= esc($email, 'attr') ?>"><?= esc($email) ?></a><?php endif ?>
        </div>
    <?php endif ?>
</main>
</body>
</html>
