<?= $this->extend('layouts/site') ?>

<?= $this->section('content') ?>

<?php if ($slides === []): ?>
<section class="slideshow">
    <div class="slide slide-fallback">
        <div class="slide-caption">
            <h1><?= esc(t('ศูนย์เฝ้าระวังและควบคุมคุณภาพสิ่งแวดล้อม', 'Environmental Monitoring & Control Center')) ?></h1>
            <p class="cap-en">Environmental Monitoring &amp; Control Center (EMCC)</p>
            <p><?= esc(t('นิคมอุตสาหกรรมในพื้นที่มาบตาพุดคอมเพล็กซ์', 'Industrial estates in the Map Ta Phut Complex')) ?><br><?= esc(t('การนิคมอุตสาหกรรมแห่งประเทศไทย', 'Industrial Estate Authority of Thailand')) ?></p>
        </div>
    </div>
</section>
<?php else: ?>
<section class="slideshow" data-slideshow aria-roledescription="carousel" aria-label="<?= esc(t('ภาพไฮไลต์', 'Highlights'), 'attr') ?>">
    <div class="slides" data-track>
        <?php foreach ($slides as $i => $slide):
            $img    = media_url(site_locale() === 'en' && $slide['image_en'] ? $slide['image_en'] : $slide['image_th']) ?? media_url($slide['image_th']);
            $mobile = media_url($slide['image_mobile']);
            $alt    = loc($slide, 'alt');
            $tag    = $slide['href'] ? 'a' : 'div';
            $attrs  = $slide['href'] ? ' href="' . esc($slide['href'], 'attr') . '"' . ($slide['link_type'] === 'url' && $slide['open_new_tab'] ? ' target="_blank" rel="noopener noreferrer"' : '') : '';
        ?>
        <<?= $tag ?> class="slide photo" role="group" aria-roledescription="slide" aria-label="<?= $i + 1 ?> / <?= count($slides) ?>"<?= $attrs ?><?= $i > 0 ? ' aria-hidden="true" tabindex="-1"' : '' ?>>
            <?php if ($img): ?>
            <picture class="slide-photo">
                <?php if ($mobile): ?><source media="(max-width: 720px)" srcset="<?= esc($mobile, 'attr') ?>"><?php endif ?>
                <img src="<?= esc($img, 'attr') ?>" alt="<?= esc($alt, 'attr') ?>"<?= $i > 0 ? ' loading="lazy"' : ' fetchpriority="high"' ?>>
            </picture>
            <?php endif ?>
        </<?= $tag ?>>
        <?php endforeach ?>
    </div>
    <?php if (count($slides) > 1): ?>
    <div class="slide-arrows">
        <button class="slide-arrow" type="button" data-prev aria-label="<?= esc(t('ภาพก่อนหน้า', 'Previous slide'), 'attr') ?>">‹</button>
        <button class="slide-arrow" type="button" data-next aria-label="<?= esc(t('ภาพถัดไป', 'Next slide'), 'attr') ?>">›</button>
    </div>
    <div class="slide-dots" data-dots>
        <?php foreach ($slides as $i => $slide): ?>
        <button type="button" class="slide-dot<?= $i === 0 ? ' active' : '' ?>" aria-label="<?= esc(t('ภาพที่ ', 'Slide ') . ($i + 1), 'attr') ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>></button>
        <?php endforeach ?>
    </div>
    <div class="slide-progress"><div class="slide-progress-bar" data-progress></div></div>
    <?php endif ?>
</section>
<?php endif ?>

<!-- แผนที่จุดตรวจวัด: พื้นที่ของระบบแผนที่ (dev อีกคน) — อย่าแก้ส่วนนี้ -->
<section class="map-section" id="map">
    <div class="container">
        <div class="section-title">
            <div>
                <h2><?= esc(t('แผนที่แสดงโรงงานและสถานะการระบายของเสีย', 'Factory Emission & Discharge Status Map')) ?></h2>
                <p><?= esc(t('แสดงพิกัดโรงงาน ประเภทระบบตรวจวัด และสถานะข้อมูลล่าสุด เช่น ปกติ เฝ้าระวัง เกินเกณฑ์ หรือไม่ส่งข้อมูล', 'Factory locations, monitoring system types and latest status — normal, watch, exceed or no data.')) ?></p>
            </div>
        </div>
    </div>
    <div class="map-fullbleed">
        <div class="map-box" id="emcc-map">
            <div class="map-placeholder">
                <span aria-hidden="true">🗺️</span>
                <p><?= esc(t('ระบบแผนที่จุดตรวจวัดกำลังจัดทำ', 'The monitoring map is coming soon')) ?></p>
            </div>
        </div>
    </div>
</section>

<?php if ($news !== []): ?>
<section class="section news-section" id="news">
    <div class="container">
        <div class="section-title">
            <div>
                <h2><?= esc(t('ข่าวสารและประกาศ', 'News & Announcements')) ?></h2>
                <p><?= esc(t('ประกาศ กิจกรรม และความเคลื่อนไหวล่าสุดของศูนย์ EMCC', 'Latest notices, activities and updates from EMCC')) ?></p>
            </div>
        </div>
        <div class="news-grid">
            <?php foreach ($news as $item): ?>
            <?= view('site/partials/news_card', ['item' => $item]) ?>
            <?php endforeach ?>
        </div>
        <div class="news-cta-wrap">
            <a class="news-cta" href="<?= site_url('news') ?>"><b><?= esc(t('อ่านเพิ่มเติม', 'Read more')) ?></b><span aria-hidden="true">↗</span></a>
        </div>
    </div>
</section>
<?php endif ?>

<?php if ($downloads !== []): ?>
<section class="data-strip" id="download">
    <div class="container">
        <div class="list-card">
            <div class="list-card-head">
                <h3><?= esc(t('ดาวน์โหลดข้อมูลและเอกสาร', 'Downloads & Documents')) ?></h3>
                <a class="see-all" href="<?= site_url('downloads') ?>"><?= esc(t('ดูทั้งหมด →', 'See all →')) ?></a>
            </div>
            <?php foreach ($downloads as $item):
                $isLink = $item['type'] === 'link';
            ?>
            <a class="list-item" href="<?= esc($item['url'], 'attr') ?>"<?= $isLink ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
                <div>
                    <strong><?= esc(loc($item, 'title')) ?></strong>
                    <small><?= esc(implode(' · ', array_filter([$item['category_name'], $isLink ? t('ลิงก์ภายนอก', 'External link') : strtoupper((string) $item['file_extension']) . ' ' . human_size((int) $item['file_size'])]))) ?></small>
                </div>
                <span class="download-btn" aria-hidden="true"><?= $isLink ? '↗' : '⬇' ?></span>
            </a>
            <?php endforeach ?>
        </div>
    </div>
</section>
<?php endif ?>

<?= $this->endSection() ?>

<?= $this->section('after') ?>
<?= view('site/partials/popups', ['popups' => $popups]) ?>
<?= $this->endSection() ?>
