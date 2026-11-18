<?php
$url     = news_url($news);
$cover   = media_url($news['cover_image']);
$title   = loc($news, 'title');
$excerpt = loc($news, 'excerpt');
$share   = rawurlencode($url);
$catUrl  = ! empty($news['category']['is_active']) ? site_url('news') . '?category=' . rawurlencode($news['category']['slug']) : null;
?>
<?= $this->extend('layouts/site') ?>

<?= $this->section('content') ?>
<?php if ($preview): ?>
<div class="preview-bar" role="status">
    <?= esc('ตัวอย่างสำหรับเจ้าหน้าที่ — ข่าวนี้ยังไม่แสดงต่อสาธารณะ (' . (\App\Models\NewsModel::DISPLAY_LABELS[\App\Models\NewsModel::displayStatus($news)] ?? $news['status']) . ')') ?>
</div>
<?php endif ?>

<section class="article-hero">
    <?php if ($cover): ?><img class="hero-bg" src="<?= esc($cover, 'attr') ?>" alt=""><?php endif ?>
    <div class="container">
        <nav class="breadcrumb" aria-label="<?= esc(t('ตำแหน่งหน้า', 'Breadcrumb'), 'attr') ?>">
            <a href="<?= site_url('/') ?>"><?= esc(t('หน้าหลัก', 'Home')) ?></a><span aria-hidden="true">›</span>
            <a href="<?= site_url('news') ?>"><?= esc(t('ข่าวสาร', 'News')) ?></a>
            <?php if ($catUrl): ?><span aria-hidden="true">›</span><a href="<?= esc($catUrl, 'attr') ?>"><?= esc($news['category_name']) ?></a><?php endif ?>
        </nav>
        <?php if ($news['category_name'] !== ''): ?><span class="chip"><?= esc($news['category_name']) ?></span><?php endif ?>
        <h1><?= esc($title) ?></h1>
        <div class="meta">
            <span>📅 <?= esc(site_date($news['published_at'], true)) ?></span>
            <span>👁 <?= esc(t(number_format((int) $news['view_count']) . ' ครั้ง', number_format((int) $news['view_count']) . ' views')) ?></span>
        </div>
    </div>
</section>

<div class="article-wrap">
    <div class="container article-layout">
        <article class="article-body">
            <?php if ($excerpt !== ''): ?><p class="lead"><?= esc($excerpt) ?></p><?php endif ?>

            <?php if ($cover): ?>
            <figure class="figure">
                <img src="<?= esc($cover, 'attr') ?>" alt="<?= esc(loc($news, 'cover_alt') ?: $title, 'attr') ?>">
                <?php if (loc($news, 'cover_alt') !== ''): ?><figcaption><?= esc(loc($news, 'cover_alt')) ?></figcaption><?php endif ?>
            </figure>
            <?php endif ?>

            <div class="rich-content">
                <?= loc($news, 'content') /* กรอง HTML (allowlist) ตอนบันทึกแล้ว ใน NewsModel::prepare() */ ?>
            </div>

            <?php if ($gallery !== []): ?>
            <h2><?= esc(t('ภาพกิจกรรม', 'Gallery')) ?></h2>
            <div class="gallery" data-gallery>
                <?php foreach ($gallery as $img):
                    $full  = media_url($img['image_path']);
                    $thumb = media_url($img['thumb_path']) ?? $full;
                    if ($full === null) { continue; }
                    $caption = loc($img, 'caption');
                ?>
                <figure>
                    <a href="<?= esc($full, 'attr') ?>" data-lightbox<?= $caption !== '' ? ' data-caption="' . esc($caption, 'attr') . '"' : '' ?>>
                        <img src="<?= esc($thumb, 'attr') ?>" alt="<?= esc($caption !== '' ? $caption : t('ภาพกิจกรรม', 'Gallery image'), 'attr') ?>" loading="lazy">
                    </a>
                    <?php if ($caption !== ''): ?><figcaption><?= esc($caption) ?></figcaption><?php endif ?>
                </figure>
                <?php endforeach ?>
            </div>
            <?php endif ?>

            <?php if ($attachments !== []): ?>
            <h2><?= esc(t('ไฟล์แนบ', 'Attachments')) ?></h2>
            <div class="attach-list">
                <?php foreach ($attachments as $att):
                    $badge = file_badge(pathinfo($att['original_name'], PATHINFO_EXTENSION));
                ?>
                <a class="attach-item" href="<?= site_url('news/attachments/' . $att['id']) ?>">
                    <span class="file-ico <?= esc($badge['class'], 'attr') ?>"><?= esc($badge['label']) ?></span>
                    <span class="file-name"><strong><?= esc(loc($att, 'title')) ?></strong><span><?= esc(human_size((int) $att['file_size'])) ?></span></span>
                    <span class="dl-btn">⬇ <?= esc(t('ดาวน์โหลด', 'Download')) ?></span>
                </a>
                <?php endforeach ?>
            </div>
            <?php endif ?>

            <div class="share-row">
                <span class="lbl"><?= esc(t('แชร์ข่าว:', 'Share:')) ?></span>
                <a class="share-btn" href="https://www.facebook.com/sharer/sharer.php?u=<?= $share ?>" target="_blank" rel="noopener noreferrer" title="Facebook" aria-label="<?= esc(t('แชร์ไปที่ Facebook', 'Share on Facebook'), 'attr') ?>">f</a>
                <a class="share-btn" href="https://twitter.com/intent/tweet?url=<?= $share ?>&amp;text=<?= rawurlencode($title) ?>" target="_blank" rel="noopener noreferrer" title="X" aria-label="<?= esc(t('แชร์ไปที่ X', 'Share on X'), 'attr') ?>">𝕏</a>
                <a class="share-btn" href="https://social-plugins.line.me/lineit/share?url=<?= $share ?>" target="_blank" rel="noopener noreferrer" title="LINE" aria-label="<?= esc(t('แชร์ไปที่ LINE', 'Share on LINE'), 'attr') ?>">L</a>
                <button class="share-btn" type="button" data-copy="<?= esc($url, 'attr') ?>" data-copied="<?= esc(t('คัดลอกลิงก์แล้ว', 'Link copied'), 'attr') ?>" title="<?= esc(t('คัดลอกลิงก์', 'Copy link'), 'attr') ?>" aria-label="<?= esc(t('คัดลอกลิงก์', 'Copy link'), 'attr') ?>">🔗</button>
            </div>
        </article>

        <aside>
            <?php if ($latest !== []): ?>
            <div class="aside-card">
                <h3><?= esc(t('ข่าวล่าสุด', 'Latest news')) ?></h3>
                <div class="mini-list">
                    <?php foreach ($latest as $i => $item): $thumb = media_url($item['cover_thumb'] ?: $item['cover_image']); ?>
                    <a class="mini-item" href="<?= esc(news_url($item), 'attr') ?>">
                        <span class="mini-thumb g<?= $i % 3 + 1 ?>"><?php if ($thumb): ?><img src="<?= esc($thumb, 'attr') ?>" alt="" loading="lazy"><?php endif ?></span>
                        <span><h4><?= esc(loc($item, 'title')) ?></h4><span><?= esc(site_date($item['published_at'])) ?></span></span>
                    </a>
                    <?php endforeach ?>
                </div>
            </div>
            <?php endif ?>
            <?php if ($downloads !== []): ?>
            <div class="aside-card aside-tint">
                <h3><?= esc(t('ดาวน์โหลดเอกสาร', 'Downloads')) ?></h3>
                <div class="mini-list">
                    <?php foreach ($downloads as $item): $badge = file_badge($item['file_extension'], $item['type'] === 'link'); ?>
                    <a class="mini-item" href="<?= esc($item['url'], 'attr') ?>"<?= $item['type'] === 'link' ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
                        <span class="mini-thumb file-ico <?= esc($badge['class'], 'attr') ?>"><?= esc($badge['label']) ?></span>
                        <span><h4><?= esc(loc($item, 'title')) ?></h4><span><?= esc($item['category_name']) ?></span></span>
                    </a>
                    <?php endforeach ?>
                </div>
                <a class="see-all" href="<?= site_url('downloads') ?>"><?= esc(t('ดูทั้งหมด →', 'See all →')) ?></a>
            </div>
            <?php endif ?>
        </aside>
    </div>
</div>

<?php if ($related !== []): ?>
<section class="related">
    <div class="container">
        <div class="section-head">
            <h2><?= esc(t('ข่าวที่เกี่ยวข้อง', 'Related news')) ?></h2>
            <?php if ($catUrl): ?><a class="see-all" href="<?= esc($catUrl, 'attr') ?>"><?= esc(t('ดูทั้งหมด →', 'See all →')) ?></a><?php endif ?>
        </div>
        <div class="article-grid">
            <?php foreach ($related as $i => $item): ?>
            <?= view('site/partials/article_card', ['item' => $item, 'i' => $i + 1]) ?>
            <?php endforeach ?>
        </div>
    </div>
</section>
<?php endif ?>
<?= $this->endSection() ?>
