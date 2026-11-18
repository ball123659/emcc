<?php
$heading = t('ศูนย์ดาวน์โหลดข้อมูลและเอกสาร', 'Downloads & Documents Center');
$chips   = ['purple', 'green', 'watch'];
$catIdx  = array_flip(array_column($categories, 'slug'));
?>
<?= $this->extend('layouts/site') ?>

<?= $this->section('content') ?>
<?= view('site/partials/banner', [
    'heading' => $heading,
    'text'    => t('รวมข้อมูลตรวจวัดย้อนหลัง รายงานสรุปสถานะ คู่มือการใช้งาน แบบฟอร์ม และเอกสารอ้างอิงที่เกี่ยวข้องกับการรายงานข้อมูลของโรงงาน', 'Historical monitoring data, status reports, user manuals, forms and reference documents related to factory data reporting.'),
    'crumbs'  => [[t('ดาวน์โหลด', 'Downloads'), null]],
]) ?>

<div class="dl-toolbar">
    <div class="container dl-toolbar-inner">
        <div class="dl-tabs" role="group" aria-label="<?= esc(t('หมวดหมู่', 'Categories'), 'attr') ?>">
            <button type="button" class="active" data-cat="all" aria-pressed="true"><?= esc(t('ทั้งหมด', 'All')) ?></button>
            <?php foreach ($categories as $c): ?>
            <button type="button" data-cat="<?= esc($c['slug'], 'attr') ?>" aria-pressed="false"><?= esc(loc($c, 'name')) ?></button>
            <?php endforeach ?>
        </div>
        <label class="search-box">
            <span aria-hidden="true">🔍</span>
            <input type="search" data-dl-search placeholder="<?= esc(t('ค้นหาไฟล์...', 'Search files...'), 'attr') ?>" aria-label="<?= esc(t('ค้นหาไฟล์', 'Search files'), 'attr') ?>">
        </label>
    </div>
</div>

<div class="dl-wrap">
    <div class="container">
        <p class="dl-count" data-dl-count data-template="<?= esc(t('พบ {n} รายการ', '{n} files'), 'attr') ?>" aria-live="polite"><?= esc(str_replace('{n}', (string) count($items), t('พบ {n} รายการ', '{n} files'))) ?></p>
        <?php if ($items === []): ?>
        <div class="empty-state">
            <span aria-hidden="true">📂</span>
            <p><?= esc(t('ยังไม่มีไฟล์ให้ดาวน์โหลด', 'No files available yet')) ?></p>
        </div>
        <?php else: ?>
        <div class="dl-table">
            <div class="dl-row dl-head" aria-hidden="true">
                <div></div>
                <div><?= esc(t('ชื่อไฟล์', 'File name')) ?></div>
                <div><?= esc(t('ประเภท', 'Type')) ?></div>
                <div><?= esc(t('ขนาด', 'Size')) ?></div>
                <div><?= esc(t('วันที่', 'Date')) ?></div>
                <div></div>
            </div>
            <?php foreach ($items as $item):
                $isLink = $item['type'] === 'link';
                $badge  = file_badge($item['file_extension'], $isLink);
                $desc   = loc($item, 'description');
                $search = mb_strtolower(implode(' ', [$item['title_th'], $item['title_en'], $item['description_th'], $item['description_en'], $item['category_name']]));
            ?>
            <div class="dl-row" data-cat="<?= esc($item['category_slug'], 'attr') ?>" data-name="<?= esc($search, 'attr') ?>">
                <div class="file-ico <?= esc($badge['class'], 'attr') ?>" aria-hidden="true"><?= esc($badge['label']) ?></div>
                <div class="file-name">
                    <strong><?= esc(loc($item, 'title')) ?></strong>
                    <?php if ($desc !== ''): ?><span class="dl-desc"><?= esc($desc) ?></span><?php endif ?>
                    <?php if ($item['category_name'] !== ''): ?><span><span class="chip mini <?= $chips[($catIdx[$item['category_slug']] ?? 0) % 3] ?>"><?= esc($item['category_name']) ?></span></span><?php endif ?>
                </div>
                <div class="dl-meta dl-type"><?= $isLink ? esc(t('ลิงก์', 'Link')) : esc(strtoupper((string) $item['file_extension'])) ?></div>
                <div class="dl-meta dl-size"><?= $isLink ? '–' : esc(human_size((int) $item['file_size'])) ?></div>
                <div class="dl-meta dl-date"><?= esc(site_date($item['updated_at'] ?? $item['created_at'])) ?></div>
                <a class="dl-btn" href="<?= esc($item['url'], 'attr') ?>"<?= $isLink ? ' target="_blank" rel="noopener noreferrer"' : '' ?> aria-label="<?= esc(($isLink ? t('เปิดลิงก์: ', 'Open link: ') : t('ดาวน์โหลด: ', 'Download: ')) . loc($item, 'title'), 'attr') ?>">
                    <?= $isLink ? '↗ ' . esc(t('เปิดลิงก์', 'Open')) : '⬇ ' . esc(t('ดาวน์โหลด', 'Download')) ?>
                </a>
            </div>
            <?php endforeach ?>
            <div class="dl-empty" data-dl-empty hidden><?= esc(t('ไม่พบไฟล์ที่ตรงกับคำค้น ลองค้นหาด้วยคำอื่น', 'No matching files. Try another keyword.')) ?></div>
        </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
