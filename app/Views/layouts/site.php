<?php
/**
 * Layout หน้าบ้าน
 *
 * ตัวแปร: $title (ไม่บังคับ), $description, $active (เมนูที่เลือก), $ogImage
 */
$lang     = site_locale();
$siteName = setting('site_name', t('ศูนย์เฝ้าระวังและควบคุมคุณภาพสิ่งแวดล้อม', 'Environmental Monitoring & Control Center'));
$title    = isset($title) && $title !== '' ? $title . ' — EMCC' : 'EMCC — ' . $siteName;
$footerText = setting('footer_text', t(
    'เว็บไซต์แสดงผลข้อมูล CEMS, WPMS และ POMS พร้อมแผนที่โรงงานและข้อมูลย้อนหลังจากฐานข้อมูลกลาง',
    'Portal for CEMS, WPMS and POMS data with factory map and historical records from the central database.',
));
$desc     = isset($description) && trim((string) $description) !== '' ? $description : $footerText;
$active   = $active ?? '';
$phones   = tel_links(setting('phone'));
$email    = setting('email');
$address  = setting('address');
$version  = static fn (string $file) => base_url($file) . '?v=' . @filemtime(FCPATH . $file);

$menu = [
    'home'      => [site_url('/'), t('หน้าหลัก', 'Home')],
    'about'     => [site_url('about'), t('เกี่ยวกับ', 'About')],
    'news'      => [site_url('news'), t('ข่าวสาร', 'News')],
    'downloads' => [site_url('downloads'), t('ดาวน์โหลด', 'Downloads')],
    'contact'   => [site_url('contact'), t('ติดต่อเรา', 'Contact')],
];

$socials = array_filter([
    'Facebook' => safe_url(setting('facebook_url')),
    'LINE'     => safe_url(setting('line_url')),
    'YouTube'  => safe_url(setting('youtube_url')),
]);
?><!DOCTYPE html>
<html lang="<?= esc($lang, 'attr') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?></title>
    <meta name="description" content="<?= esc(mb_strimwidth(strip_tags((string) $desc), 0, 200, '…'), 'attr') ?>">
    <meta property="og:site_name" content="EMCC">
    <meta property="og:title" content="<?= esc($title, 'attr') ?>">
    <meta property="og:description" content="<?= esc(mb_strimwidth(strip_tags((string) $desc), 0, 200, '…'), 'attr') ?>">
    <?php if (! empty($ogImage)): ?><meta property="og:image" content="<?= esc($ogImage, 'attr') ?>"><?php endif ?>
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <link rel="stylesheet" href="<?= $version('fonts/prompt/prompt.css') ?>">
    <link rel="stylesheet" href="<?= $version('assets/css/site.css') ?>">
</head>
<body>
<a class="skip-link" href="#main"><?= esc(t('ข้ามไปยังเนื้อหา', 'Skip to content')) ?></a>

<div class="topbar">
    <div class="container topbar-inner">
        <div class="top-left">
            <?php if ($phones !== []): ?>
            <span>📞 <?= esc(t('เบอร์โทรศัพท์', 'Tel.')) ?>
                <?php foreach ($phones as $i => $p): ?><?= $i > 0 ? ' / ' : ' ' ?><a href="<?= esc($p['href'], 'attr') ?>"><?= esc($p['label']) ?></a><?php endforeach ?>
            </span>
            <?php endif ?>
            <?php if ($email): ?><span class="top-email">✉ <a href="mailto:<?= esc($email, 'attr') ?>"><?= esc($email) ?></a></span><?php endif ?>
        </div>
        <div class="top-right">
            <div class="lang" role="group" aria-label="<?= esc(t('เลือกภาษา', 'Language'), 'attr') ?>">
                <span><?= esc(t('ภาษา', 'Language')) ?></span>
                <a class="<?= $lang === 'th' ? 'active' : '' ?>" href="<?= site_url('lang/th') ?>" hreflang="th" lang="th"<?= $lang === 'th' ? ' aria-current="true"' : '' ?>>TH</a>
                <a class="<?= $lang === 'en' ? 'active' : '' ?>" href="<?= site_url('lang/en') ?>" hreflang="en" lang="en"<?= $lang === 'en' ? ' aria-current="true"' : '' ?>>EN</a>
            </div>
        </div>
    </div>
</div>

<header class="header">
    <div class="container header-inner">
        <a class="brand" href="<?= site_url('/') ?>">
            <div class="logo"><img src="<?= base_url('assets/img/ieat-logo.jpg') ?>" alt="<?= esc(t('ตราการนิคมอุตสาหกรรมแห่งประเทศไทย', 'Industrial Estate Authority of Thailand logo'), 'attr') ?>" width="62" height="62"></div>
            <div class="brand-text">
                <strong><?= esc($siteName) ?></strong>
                <span>Industrial Emission &amp; Wastewater Monitoring</span>
            </div>
        </a>
        <nav class="nav" aria-label="<?= esc(t('เมนูหลัก', 'Main menu'), 'attr') ?>">
            <?php foreach ($menu as $key => [$href, $label]): ?>
            <a href="<?= esc($href, 'attr') ?>"<?= $active === $key ? ' class="active" aria-current="page"' : '' ?>><?= esc($label) ?></a>
            <?php endforeach ?>
            <a class="login-btn" href="<?= site_url('backend/login') ?>"><?= esc(t('เข้าสู่ระบบ', 'Login')) ?></a>
        </nav>
        <button class="mobile-toggle" type="button" data-drawer-toggle aria-controls="drawer" aria-expanded="false" aria-label="<?= esc(t('เปิดเมนู', 'Open menu'), 'attr') ?>">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>

<div class="drawer-backdrop" data-drawer-close></div>
<aside class="drawer" id="drawer" aria-label="<?= esc(t('เมนู', 'Menu'), 'attr') ?>" inert>
    <div class="drawer-head">
        <strong><?= esc(t('เมนู', 'Menu')) ?></strong>
        <button class="drawer-close" type="button" data-drawer-close aria-label="<?= esc(t('ปิดเมนู', 'Close menu'), 'attr') ?>">✕</button>
    </div>
    <nav class="drawer-nav">
        <?php foreach ($menu as $key => [$href, $label]): ?>
        <a href="<?= esc($href, 'attr') ?>"<?= $active === $key ? ' class="active" aria-current="page"' : '' ?>><?= esc($label) ?></a>
        <?php endforeach ?>
        <a class="login-btn" href="<?= site_url('backend/login') ?>"><?= esc(t('เข้าสู่ระบบ', 'Login')) ?></a>
    </nav>
</aside>

<main id="main">
<?= $this->renderSection('content') ?>
</main>

<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <h4><?= esc($siteName) ?></h4>
                <p><?= nl2br(esc((string) $footerText)) ?></p>
                <?php if ($socials !== []): ?>
                <div class="footer-social">
                    <?php foreach ($socials as $name => $url): ?>
                    <a href="<?= esc($url, 'attr') ?>" target="_blank" rel="noopener noreferrer"><?= esc($name) ?></a>
                    <?php endforeach ?>
                </div>
                <?php endif ?>
            </div>
            <div>
                <h4><?= esc(t('เมนูหลัก', 'Main Menu')) ?></h4>
                <a href="<?= site_url('/') ?>#map"><?= esc(t('แผนที่โรงงาน', 'Factory Map')) ?></a>
                <a href="<?= site_url('news') ?>"><?= esc(t('ข่าวสารและกิจกรรม', 'News & Activities')) ?></a>
                <a href="<?= site_url('downloads') ?>"><?= esc(t('ดาวน์โหลด', 'Downloads')) ?></a>
                <a href="<?= site_url('about') ?>"><?= esc(t('เกี่ยวกับ', 'About')) ?></a>
                <a href="<?= site_url('contact') ?>"><?= esc(t('ติดต่อเรา', 'Contact')) ?></a>
            </div>
            <div>
                <h4><?= esc(t('ติดต่อ', 'Contact')) ?></h4>
                <?php if ($phones !== []): ?>
                <p>📞 <?php foreach ($phones as $i => $p): ?><?= $i > 0 ? ' / ' : '' ?><a class="inline" href="<?= esc($p['href'], 'attr') ?>"><?= esc($p['label']) ?></a><?php endforeach ?></p>
                <?php endif ?>
                <?php if ($email): ?><p>✉ <a class="inline" href="mailto:<?= esc($email, 'attr') ?>"><?= esc($email) ?></a></p><?php endif ?>
                <?php if ($address): ?><p><?= nl2br(esc($address)) ?></p><?php endif ?>
            </div>
        </div>
        <div class="footer-bottom">© <?= date('Y') ?> EMCC · Environmental Monitoring &amp; Control Center</div>
    </div>
</footer>

<?= $this->renderSection('after') ?>
<script src="<?= $version('assets/js/site.js') ?>" defer></script>
</body>
</html>
