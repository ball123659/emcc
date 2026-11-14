<?php
use App\Models\SlideModel;

$s     = $slide ?? [];
$isNew = $slide === null;
$path  = 'backend/slides';
?>
<?= $this->extend('layouts/backend') ?>

<?= $this->section('header_actions') ?>
<?php if (! $isNew): ?><?= partial('partials/delete_header_button', ['url' => site_url($path . '/' . $s['id'] . '/delete'), 'label' => $s['title']]) ?><?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<form method="post" enctype="multipart/form-data" action="<?= site_url($isNew ? $path : $path . '/' . $s['id']) ?>" data-validate class="page-body">
    <?= csrf_field() ?>

    <?= section_open('ข้อมูลสไลด์') ?>
        <?= text_field('title', ['label' => 'ชื่อเรียก (ใช้ภายในระบบ ไม่แสดงบนหน้าเว็บ)', 'value' => $s['title'] ?? '', 'required' => true, 'maxlength' => 200, 'placeholder' => 'เช่น รณรงค์ลด PM2.5 พ.ย. 69']) ?>
    <?= section_close() ?>

    <?= section_open('รูปภาพ', 'รูปภาษาไทยบังคับ — ถ้าไม่ใส่รูปภาษาอังกฤษ ระบบจะใช้รูปภาษาไทยแทน', ['grid' => 3]) ?>
        <?= image_field('image_th', ['label' => 'รูปภาษาไทย', 'current' => $s['image_th'] ?? null, 'required' => true, 'hint' => 'ขนาดแนะนำ 1920 × 600 px']) ?>
        <?= image_field('image_en', ['label' => 'รูปภาษาอังกฤษ', 'current' => $s['image_en'] ?? null, 'removable' => true, 'hint' => 'ขนาดแนะนำ 1920 × 600 px']) ?>
        <?= image_field('image_mobile', ['label' => 'รูปสำหรับมือถือ', 'current' => $s['image_mobile'] ?? null, 'removable' => true, 'hint' => 'ขนาดแนะนำ 800 × 800 px']) ?>
        <?= text_field('alt_th', ['label' => 'คำอธิบายรูป (ภาษาไทย)', 'value' => $s['alt_th'] ?? '', 'required' => true, 'maxlength' => 255, 'hint' => 'ข้อความแทนรูปสำหรับผู้พิการทางสายตา/โปรแกรมอ่านหน้าจอ อธิบายสั้น ๆ ว่ารูปสื่ออะไร']) ?>
        <?= text_field('alt_en', ['label' => 'คำอธิบายรูป (ภาษาอังกฤษ)', 'value' => $s['alt_en'] ?? '', 'maxlength' => 255, 'hint' => 'เว้นว่างได้ — หน้าเว็บภาษาอังกฤษจะใช้ภาษาไทยแทน']) ?>
    <?= section_close() ?>

    <?= section_open('ลิงก์เมื่อคลิกสไลด์', null, ['grid' => 2]) ?>
        <div class="span-full"><?= radio_field('link_type', ['label' => 'ประเภทลิงก์', 'options' => SlideModel::LINK_TYPES, 'value' => $s['link_type'] ?? 'none', 'required' => true]) ?></div>
        <div data-show-when="link_type=news">
            <?= select_field('news_id', ['label' => 'เลือกข่าว', 'options' => $newsOptions, 'placeholder' => 'เลือกข่าว', 'value' => $s['news_id'] ?? '', 'required' => true, 'hint' => 'เลือกได้เฉพาะข่าวที่เผยแพร่แล้ว · ถ้าข่าวถูกลบภายหลัง สไลด์จะแสดงแบบไม่มีลิงก์']) ?>
        </div>
        <div data-show-when="link_type=url">
            <?= text_field('link_url', ['label' => 'ลิงก์ URL', 'type' => 'url', 'value' => $s['link_url'] ?? '', 'required' => true, 'maxlength' => 500, 'placeholder' => 'https://']) ?>
        </div>
        <div data-show-when="link_type=news|url">
            <?= toggle_field('open_new_tab', ['label' => 'เปิดลิงก์ในแท็บใหม่', 'value' => $s['open_new_tab'] ?? 1]) ?>
        </div>
    <?= section_close() ?>

    <?= section_open('การแสดงผล', null, ['grid' => 3]) ?>
        <?= toggle_field('is_active', ['label' => 'เปิดแสดง', 'value' => $s['is_active'] ?? 1, 'hint' => 'ปิดไว้ = ไม่แสดงหน้าเว็บ แม้อยู่ในช่วงวันที่']) ?>
        <?= date_field('start_date', ['label' => 'วันเริ่มแสดง', 'value' => $s['start_date'] ?? '', 'hint' => 'เว้นว่าง = แสดงทันที']) ?>
        <?= date_field('end_date', ['label' => 'วันสิ้นสุด', 'value' => $s['end_date'] ?? '', 'after' => 'start_date', 'hint' => 'เว้นว่าง = ไม่มีวันหมดอายุ']) ?>
    <?= section_close() ?>

    <?= partial('partials/form_buttons', ['isNew' => $isNew, 'cancel' => site_url($path)]) ?>
</form>
<?= $this->endSection() ?>
