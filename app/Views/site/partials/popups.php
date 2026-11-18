<?php
/**
 * Popup หน้าแรก — แสดงต่อกันทีละอันตามลำดับ (ปิดอันแรก → อันถัดไป)
 * ติ๊ก "ไม่ต้องแสดงอีกวันนี้" แล้วปิด = ไม่แสดง popup ชุดนี้อีกจนจบวัน (เก็บใน localStorage)
 */
$items = [];

foreach ($popups as $popup) {
    $img = media_url(site_locale() === 'en' && $popup['image_en'] ? $popup['image_en'] : $popup['image_th']) ?? media_url($popup['image_th']);

    if ($img !== null) {
        $items[] = $popup + ['img' => $img, 'href' => safe_url($popup['link_url'])];
    }
}

if ($items === []) {
    return;
}
?>
<div class="popup-layer" data-popups hidden>
    <div class="popup-backdrop" data-popup-close></div>
    <?php foreach ($items as $i => $popup): ?>
    <div class="popup" role="dialog" aria-modal="true" aria-label="<?= esc(loc($popup, 'alt'), 'attr') ?>" data-popup="<?= (int) $popup['id'] ?>" hidden>
        <button type="button" class="popup-close" data-popup-close aria-label="<?= esc(t('ปิด', 'Close'), 'attr') ?>">✕</button>
        <?php if ($popup['href']): ?>
        <a href="<?= esc($popup['href'], 'attr') ?>"<?= $popup['open_new_tab'] ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><img src="<?= esc($popup['img'], 'attr') ?>" alt="<?= esc(loc($popup, 'alt'), 'attr') ?>"></a>
        <?php else: ?>
        <img src="<?= esc($popup['img'], 'attr') ?>" alt="<?= esc(loc($popup, 'alt'), 'attr') ?>">
        <?php endif ?>
        <div class="popup-foot">
            <label><input type="checkbox" data-popup-skip> <?= esc(t('ไม่ต้องแสดงอีกวันนี้', "Don't show again today")) ?></label>
            <span class="popup-count"><?= count($items) > 1 ? ($i + 1) . ' / ' . count($items) : '' ?></span>
            <button type="button" class="popup-btn" data-popup-close><?= esc($i + 1 < count($items) ? t('ถัดไป', 'Next') : t('ปิด', 'Close')) ?></button>
        </div>
    </div>
    <?php endforeach ?>
</div>
