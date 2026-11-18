<?php
/** การ์ดข่าวแบบหน้ารวมข่าว — ตัวแปร: $item, $i (ลำดับ ใช้เลือกสีพื้นตอนไม่มีรูป) */
$thumb = media_url($item['cover_thumb'] ?: $item['cover_image']);
$url   = news_url($item);
$chips = ['purple', 'green'];
?>
<article class="article-card">
    <a class="card-img g<?= ((int) ($i ?? 0) % 7) + 1 ?>" href="<?= esc($url, 'attr') ?>" tabindex="-1" aria-hidden="true">
        <?php if ($thumb): ?><img src="<?= esc($thumb, 'attr') ?>" alt="" loading="lazy"><?php endif ?>
        <?php if ($item['category_name'] !== ''): ?><span class="chip <?= $chips[(int) $item['category_id'] % 2] ?>"><?= esc($item['category_name']) ?></span><?php endif ?>
    </a>
    <div class="card-body">
        <h3><a href="<?= esc($url, 'attr') ?>"><?= esc(loc($item, 'title')) ?></a></h3>
        <p><?= esc(mb_strimwidth(loc($item, 'excerpt'), 0, 180, '…')) ?></p>
        <div class="card-foot">
            <span><?= esc(site_date($item['published_at'])) ?></span>
            <a class="read" href="<?= esc($url, 'attr') ?>" aria-label="<?= esc(t('อ่านต่อ: ', 'Read more: ') . loc($item, 'title'), 'attr') ?>"><?= esc(t('อ่านต่อ →', 'Read →')) ?></a>
        </div>
    </div>
</article>
