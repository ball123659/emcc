<?php
/** แถบหัวหน้า — ตัวแปร: $heading, $text (ไม่บังคับ), $crumbs = [[label, url|null], ...] */
?>
<section class="page-banner">
    <div class="container banner-inner">
        <nav class="breadcrumb" aria-label="<?= esc(t('ตำแหน่งหน้า', 'Breadcrumb'), 'attr') ?>">
            <a href="<?= site_url('/') ?>"><?= esc(t('หน้าหลัก', 'Home')) ?></a>
            <?php foreach ($crumbs ?? [] as [$label, $url]): ?>
            <span aria-hidden="true">›</span>
            <?php if ($url): ?><a href="<?= esc($url, 'attr') ?>"><?= esc($label) ?></a><?php else: ?><span aria-current="page"><?= esc($label) ?></span><?php endif ?>
            <?php endforeach ?>
        </nav>
        <h1><?= esc($heading) ?></h1>
        <?php if (! empty($text)): ?><p><?= esc($text) ?></p><?php endif ?>
    </div>
</section>
