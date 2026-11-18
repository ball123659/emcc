<?php
/** การ์ดข่าวแบบหน้าแรก — ตัวแปร: $item */
$thumb = media_url($item['cover_thumb'] ?: $item['cover_image']);
$url   = news_url($item);
?>
<article class="news-card">
    <div class="news-meta">
        <?php if ($item['category_name'] !== ''): ?><span class="news-tag"><?= esc($item['category_name']) ?></span><?php endif ?>
        <span class="news-by"><?= esc(site_date($item['published_at'])) ?></span>
    </div>
    <a class="news-thumb" href="<?= esc($url, 'attr') ?>" tabindex="-1" aria-hidden="true">
        <?php if ($thumb): ?><img src="<?= esc($thumb, 'attr') ?>" alt="" loading="lazy"><?php endif ?>
        <?php if ($item['is_pinned']): ?><span class="pin-badge">📌</span><?php endif ?>
        <span class="news-ribbon">EMCC NEWS</span>
    </a>
    <h3><a href="<?= esc($url, 'attr') ?>"><?= esc(loc($item, 'title')) ?></a></h3>
    <a class="news-more" href="<?= esc($url, 'attr') ?>" aria-label="<?= esc(t('อ่านต่อ: ', 'Read more: ') . loc($item, 'title'), 'attr') ?>"><?= esc(t('อ่านต่อ', 'Read More')) ?> <span aria-hidden="true">→</span></a>
</article>
