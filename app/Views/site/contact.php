<?php
$phones  = tel_links(setting('phone'));
$email   = setting('email');
$address = setting('address');
$mapUrl  = safe_url(setting('map_embed_url'));
// ฝังแผนที่ได้เฉพาะลิงก์ "ฝังแผนที่" ของ Google Maps (https://www.google.com/maps/embed?...)
$embed   = $mapUrl !== null && str_starts_with($mapUrl, 'https://www.google.com/maps/embed?') ? $mapUrl : null;
$openMap = $embed === null && $mapUrl !== null
    ? $mapUrl
    : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address ?: 'สำนักงานนิคมอุตสาหกรรมมาบตาพุด');
$office  = t('สำนักงานนิคมอุตสาหกรรมมาบตาพุด', 'Map Ta Phut Industrial Estate Office');
?>
<?= $this->extend('layouts/site') ?>

<?= $this->section('content') ?>
<?= view('site/partials/banner', [
    'heading' => t('ติดต่อเรา', 'Contact Us'),
    'text'    => t('ช่องทางติดต่อสำนักงานนิคมอุตสาหกรรมมาบตาพุด และศูนย์ EMCC', 'Contact channels for Map Ta Phut Industrial Estate Office and EMCC'),
    'crumbs'  => [[t('ติดต่อเรา', 'Contact'), null]],
]) ?>

<section class="contact-section">
    <div class="container contact-layout">
        <div>
            <h2 class="contact-title"><?= esc(t('ช่องทางการติดต่อ', 'Get in touch')) ?></h2>
            <div class="contact-block">
                <h3><?= esc(t('ศูนย์เฝ้าระวังและควบคุมคุณภาพสิ่งแวดล้อม (EMCC)', 'Environmental Monitoring & Control Center (EMCC)')) ?></h3>
                <?php if ($phones !== []): ?>
                <p class="contact-line">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.6a1 1 0 0 1-.25 1z"/></svg>
                    <span><span class="sr-only"><?= esc(t('โทรศัพท์', 'Phone')) ?>: </span><?php foreach ($phones as $i => $p): ?><?= $i > 0 ? ', ' : '' ?><a href="<?= esc($p['href'], 'attr') ?>"><?= esc($p['label']) ?></a><?php endforeach ?></span>
                </p>
                <?php endif ?>
                <?php if ($email): ?>
                <p class="contact-line">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4-8 5-8-5V6l8 5 8-5z"/></svg>
                    <span><span class="sr-only"><?= esc(t('อีเมล', 'Email')) ?>: </span><a href="mailto:<?= esc($email, 'attr') ?>"><?= esc($email) ?></a></span>
                </p>
                <?php endif ?>
                <?php if ($address): ?>
                <p class="contact-line">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>
                    <span><?= nl2br(esc($address)) ?></span>
                </p>
                <?php endif ?>
                <?php if ($phones === [] && ! $email && ! $address): ?>
                <p class="contact-line"><?= esc(t('ยังไม่ได้ระบุข้อมูลติดต่อ', 'Contact details are not available yet.')) ?></p>
                <?php endif ?>
            </div>
        </div>
        <div class="contact-map-wrap">
            <?php if ($embed): ?>
            <iframe class="contact-map" src="<?= esc($embed, 'attr') ?>" title="<?= esc(t('แผนที่: ', 'Map: ') . $office, 'attr') ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            <?php else: ?>
            <div class="contact-map contact-map-static" aria-hidden="true">📍</div>
            <?php endif ?>
            <div class="contact-map-card">
                <strong><?= esc($office) ?></strong>
                <small><?= esc($address ? strtok($address, "\n") : t('ต.มาบตาพุด อ.เมืองระยอง จ.ระยอง', 'Map Ta Phut, Mueang Rayong, Rayong')) ?></small>
                <a href="<?= esc($openMap, 'attr') ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">🧭</span> <?= esc(t('เปิดใน Google Maps', 'Open in Google Maps')) ?></a>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
