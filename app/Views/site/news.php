<?php
$heading = t('ข่าวสารและกิจกรรม', 'News & Activities');
$pageUrl = static function (int $p) use ($category): string {
    $query = array_filter(['category' => $category['slug'] ?? null, 'page' => $p > 1 ? $p : null]);

    return site_url('news') . ($query !== [] ? '?' . http_build_query($query) : '');
};
?>
<?= $this->extend('layouts/site') ?>

<?= $this->section('content') ?>
<?= view('site/partials/banner', [
    'heading' => $heading,
    'text'    => t('รวมบทความ ประกาศ ข่าวระบบ และกิจกรรมด้านการเฝ้าระวังและควบคุมคุณภาพสิ่งแวดล้อมจากโรงงานในนิคมอุตสาหกรรม', 'Articles, announcements, system news and activities on industrial environmental monitoring and control.'),
    'crumbs'  => [[$heading, null]],
]) ?>

<?php if (count($categories) > 1): ?>
<div class="cat-bar">
    <div class="container">
        <nav class="cat-tabs" aria-label="<?= esc(t('หมวดหมู่ข่าว', 'News categories'), 'attr') ?>">
            <a href="<?= site_url('news') ?>"<?= $category === null ? ' class="active" aria-current="page"' : '' ?>><?= esc(t('ทั้งหมด', 'All')) ?></a>
            <?php foreach ($categories as $c): ?>
            <a href="<?= site_url('news') . '?category=' . rawurlencode($c['slug']) ?>"<?= ($category['id'] ?? null) === $c['id'] ? ' class="active" aria-current="page"' : '' ?>><?= esc(loc($c, 'name')) ?></a>
            <?php endforeach ?>
        </nav>
    </div>
</div>
<?php endif ?>

<div class="page-wrap">
    <div class="container">
        <?php if ($featured === null && $rows === []): ?>
        <div class="empty-state">
            <span aria-hidden="true">📰</span>
            <p><?= esc(t('ยังไม่มีข่าวในหมวดนี้', 'No news in this category yet')) ?></p>
        </div>
        <?php endif ?>

        <?php if ($featured): $url = news_url($featured); $cover = media_url($featured['cover_image']); ?>
        <article class="featured">
            <a class="featured-img g1" href="<?= esc($url, 'attr') ?>" tabindex="-1" aria-hidden="true">
                <?php if ($cover): ?><img src="<?= esc($cover, 'attr') ?>" alt=""><?php endif ?>
            </a>
            <div class="featured-body">
                <span class="chip watch"><?= esc($featured['is_pinned'] ? t('📌 ข่าวปักหมุด', '📌 Pinned') : t('ข่าวล่าสุด', 'Latest')) ?><?= $featured['category_name'] !== '' ? ' · ' . esc($featured['category_name']) : '' ?></span>
                <h2 class="featured-title"><a href="<?= esc($url, 'attr') ?>"><?= esc(loc($featured, 'title')) ?></a></h2>
                <?php if (loc($featured, 'excerpt') !== ''): ?><p><?= esc(loc($featured, 'excerpt')) ?></p><?php endif ?>
                <div class="article-meta">
                    <span>📅 <?= esc(site_date($featured['published_at'])) ?></span>
                    <span>👁 <?= number_format((int) $featured['view_count']) ?></span>
                </div>
                <a class="btn btn-primary" href="<?= esc($url, 'attr') ?>"><?= esc(t('อ่านข่าว →', 'Read article →')) ?></a>
            </div>
        </article>
        <?php endif ?>

        <?php if ($rows !== []): ?>
        <div class="section-head">
            <h2><?= esc($category ? loc($category, 'name') : t('ล่าสุด', 'Latest')) ?></h2>
            <span class="muted"><?= esc(t('ทั้งหมด ' . number_format($total) . ' ข่าว', number_format($total) . ' articles')) ?></span>
        </div>
        <div class="article-grid">
            <?php foreach ($rows as $i => $item): ?>
            <?= view('site/partials/article_card', ['item' => $item, 'i' => $i]) ?>
            <?php endforeach ?>
        </div>
        <?php endif ?>

        <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="<?= esc(t('เลือกหน้า', 'Pagination'), 'attr') ?>">
            <?php if ($page > 1): ?><a class="nav-btn" href="<?= esc($pageUrl($page - 1), 'attr') ?>" rel="prev"><?= esc(t('‹ ก่อนหน้า', '‹ Prev')) ?></a><?php endif ?>
            <?php for ($p = 1; $p <= $pages; $p++):
                if ($p !== 1 && $p !== $pages && abs($p - $page) > 2) {
                    if ($p === 2 || $p === $pages - 1) { echo '<span class="gap">…</span>'; }
                    continue;
                } ?>
            <a href="<?= esc($pageUrl($p), 'attr') ?>"<?= $p === $page ? ' class="active" aria-current="page"' : '' ?>><?= $p ?></a>
            <?php endfor ?>
            <?php if ($page < $pages): ?><a class="nav-btn" href="<?= esc($pageUrl($page + 1), 'attr') ?>" rel="next"><?= esc(t('ถัดไป ›', 'Next ›')) ?></a><?php endif ?>
        </nav>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
