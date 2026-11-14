<?php
$p     = $popup ?? [];
$isNew = $popup === null;
$path  = 'backend/popups';
?>
<?= $this->extend('layouts/backend') ?>

<?= $this->section('header_actions') ?>
<?php if (! $isNew): ?><?= partial('partials/delete_header_button', ['url' => site_url($path . '/' . $p['id'] . '/delete'), 'label' => $p['title']]) ?><?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<form method="post" enctype="multipart/form-data" action="<?= site_url($isNew ? $path : $path . '/' . $p['id']) ?>" data-validate class="page-body">
    <?= csrf_field() ?>

    <?= section_open('ข้อมูล Popup', 'หน้าเว็บแสดง Popup ที่อยู่ในช่วงวันที่ต่อกันทีละอันตามลำดับ ผู้ชมติ๊ก "ไม่ต้องแสดงอีกวันนี้" ได้') ?>
        <?= text_field('title', ['label' => 'ชื่อเรียก (ใช้ภายในระบบ ไม่แสดงบนหน้าเว็บ)', 'value' => $p['title'] ?? '', 'required' => true, 'maxlength' => 200, 'placeholder' => 'เช่น ประกาศวันหยุด ธ.ค. 69']) ?>
    <?= section_close() ?>

    <?= section_open('รูปภาพ', 'รูปภาษาไทยบังคับ — ถ้าไม่ใส่รูปภาษาอังกฤษ ระบบจะใช้รูปภาษาไทยแทน', ['grid' => 2]) ?>
        <?= image_field('image_th', ['label' => 'รูปภาษาไทย', 'current' => $p['image_th'] ?? null, 'required' => true, 'hint' => 'ขนาดแนะนำ 800 × 800 px']) ?>
        <?= image_field('image_en', ['label' => 'รูปภาษาอังกฤษ', 'current' => $p['image_en'] ?? null, 'removable' => true, 'hint' => 'ขนาดแนะนำ 800 × 800 px']) ?>
        <?= text_field('alt_th', ['label' => 'คำอธิบายรูป (ภาษาไทย)', 'value' => $p['alt_th'] ?? '', 'required' => true, 'maxlength' => 255, 'hint' => 'ข้อความแทนรูปสำหรับผู้พิการทางสายตา/โปรแกรมอ่านหน้าจอ อธิบายสั้น ๆ ว่ารูปสื่ออะไร']) ?>
        <?= text_field('alt_en', ['label' => 'คำอธิบายรูป (ภาษาอังกฤษ)', 'value' => $p['alt_en'] ?? '', 'maxlength' => 255, 'hint' => 'เว้นว่างได้ — หน้าเว็บภาษาอังกฤษจะใช้ภาษาไทยแทน']) ?>
    <?= section_close() ?>

    <?= section_open('ลิงก์เมื่อคลิกรูป (ไม่บังคับ)', null, ['grid' => 2]) ?>
        <?= text_field('link_url', ['label' => 'ลิงก์ URL', 'type' => 'url', 'value' => $p['link_url'] ?? '', 'maxlength' => 500, 'placeholder' => 'https://']) ?>
        <?= toggle_field('open_new_tab', ['label' => 'เปิดลิงก์ในแท็บใหม่', 'value' => $p['open_new_tab'] ?? 1]) ?>
    <?= section_close() ?>

    <?= section_open('การแสดงผล', null, ['grid' => 3]) ?>
        <?= toggle_field('is_active', ['label' => 'เปิดแสดง', 'value' => $p['is_active'] ?? 1, 'hint' => 'ปิดไว้ = ไม่แสดงหน้าเว็บ แม้อยู่ในช่วงวันที่']) ?>
        <?= date_field('start_date', ['label' => 'วันเริ่มแสดง', 'value' => $p['start_date'] ?? '', 'required' => true]) ?>
        <?= date_field('end_date', ['label' => 'วันสิ้นสุด', 'value' => $p['end_date'] ?? '', 'required' => true, 'after' => 'start_date', 'hint' => 'แสดงถึงสิ้นวันนี้']) ?>
    <?= section_close() ?>

    <?= partial('partials/form_buttons', ['isNew' => $isNew, 'cancel' => site_url($path)]) ?>
</form>
<?= $this->endSection() ?>
