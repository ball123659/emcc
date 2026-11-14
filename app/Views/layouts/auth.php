<?php
$toast   = session('toast');
$version = static fn (string $file) => base_url($file) . '?v=' . @filemtime(FCPATH . $file);
?><!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($title ?? 'เข้าสู่ระบบ') ?> - EMCC Backend</title>
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <link rel="stylesheet" href="<?= $version('fonts/prompt/prompt.css') ?>">
    <link rel="stylesheet" href="<?= $version('assets/css/admin.css') ?>">
    <script>try { var t = localStorage.getItem('emcc.theme') || 'system'; if (t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}</script>
</head>
<body>
<div class="auth">
    <aside class="auth-brand" aria-hidden="true">
        <div class="auth-brand-logo"><img src="<?= base_url('favicon.svg') ?>" alt=""><span>EMCC</span></div>
        <div>
            <h2>ศูนย์เฝ้าระวังและควบคุม<br>คุณภาพสิ่งแวดล้อม</h2>
            <p>ระบบจัดการเนื้อหาเว็บไซต์ — ข่าวประชาสัมพันธ์ ไฮไลต์หน้าแรก และไฟล์เผยแพร่</p>
            <div class="auth-points">
                <div class="auth-point"><span>📰</span> ประกาศข่าวและกิจกรรมได้ในไม่กี่ขั้นตอน</div>
                <div class="auth-point"><span>🛡️</span> ไฟล์ทุกไฟล์ผ่านการตรวจความปลอดภัยก่อนเผยแพร่</div>
                <div class="auth-point"><span>🌿</span> Environmental Monitoring &amp; Control Center</div>
            </div>
        </div>
        <div class="auth-foot">© <?= date('Y') ?> EMCC · สำหรับเจ้าหน้าที่เท่านั้น</div>
    </aside>
    <main class="auth-main">
        <div class="auth-card">
            <div class="brand-name">EMCC Backend</div>
            <h1><?= esc($title ?? 'เข้าสู่ระบบ') ?></h1>
            <?= $this->renderSection('content') ?>
        </div>
    </main>
</div>
<div class="toasts" aria-live="polite" data-toasts></div>
<?php if (is_array($toast)): ?>
<script type="application/json" data-initial-toast><?= json_encode($toast, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif ?>
<script src="<?= $version('assets/js/admin.js') ?>"></script>
</body>
</html>
