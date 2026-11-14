<?php
use App\Controllers\Backend\Downloads;
use App\Libraries\Uploads\DocumentStorer;
use App\Models\DownloadModel;

$d     = $download ?? [];
$isNew = $download === null;
$path  = 'backend/downloads';
$cfg   = config(\Config\Emcc::class);
$scan  = Downloads::SCAN_LABELS[$d['scan_status'] ?? ''] ?? null;
?>
<?= $this->extend('layouts/backend') ?>

<?= $this->section('header_actions') ?>
<?php if (! $isNew): ?><?= partial('partials/delete_header_button', ['url' => site_url($path . '/' . $d['id'] . '/delete'), 'label' => $d['title_th']]) ?><?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<form method="post" enctype="multipart/form-data" action="<?= site_url($isNew ? $path : $path . '/' . $d['id']) ?>" data-validate>
    <?= csrf_field() ?>
    <div class="layout-main-aside">
        <div>
            <?= section_open('ข้อมูลไฟล์', null, ['grid' => 2]) ?>
                <?= text_field('title_th', ['label' => 'ชื่อ (ภาษาไทย)', 'value' => $d['title_th'] ?? '', 'required' => true, 'maxlength' => 255]) ?>
                <?= text_field('title_en', ['label' => 'ชื่อ (ภาษาอังกฤษ)', 'value' => $d['title_en'] ?? '', 'maxlength' => 255, 'hint' => 'เว้นว่างได้ — หน้าเว็บภาษาอังกฤษจะใช้ภาษาไทยแทน']) ?>
                <?= textarea_field('description_th', ['label' => 'คำอธิบาย (ภาษาไทย)', 'value' => $d['description_th'] ?? '', 'maxlength' => 2000]) ?>
                <?= textarea_field('description_en', ['label' => 'คำอธิบาย (ภาษาอังกฤษ)', 'value' => $d['description_en'] ?? '', 'maxlength' => 2000]) ?>
            <?= section_close() ?>

            <?= section_open('ไฟล์หรือลิงก์') ?>
                <?= radio_field('type', ['label' => 'ประเภท', 'options' => [DownloadModel::TYPE_FILE => 'อัปโหลดไฟล์', DownloadModel::TYPE_LINK => 'ลิงก์ภายนอก (เช่น Google Drive, YouTube)'], 'value' => $d['type'] ?? 'file', 'required' => true]) ?>
                <div data-show-when="type=file">
                    <?= file_field('file', [
                        'label'   => 'ไฟล์', 'required' => true, 'current' => $d['original_name'] ?? null, 'accept' => DocumentStorer::acceptAttribute(), 'max_kb' => $cfg->fileMaxKb,
                        'hint'    => 'รองรับ ' . DocumentStorer::extensionsLabel() . ' ไม่เกิน ' . ($cfg->fileMaxKb / 1024) . ' MB (ไม่รับไฟล์ที่มี macro) · ไฟล์ผ่านการสแกนไวรัสก่อนบันทึก' . ($isNew ? '' : ' · อัปไฟล์ใหม่ทับได้ ลิงก์ดาวน์โหลดเดิมใช้ต่อได้'),
                    ]) ?>
                </div>
                <div data-show-when="type=link">
                    <?= text_field('external_url', ['label' => 'ลิงก์ URL', 'type' => 'url', 'value' => $d['external_url'] ?? '', 'required' => true, 'maxlength' => 500, 'placeholder' => 'https://', 'hint' => 'ระบบจะพาผู้ชมไปที่ลิงก์นี้ (ไม่ดาวน์โหลดไฟล์มาเก็บ)']) ?>
                </div>
            <?= section_close() ?>
        </div>
        <div>
            <?= section_open('การแสดงผล') ?>
                <?= select_field('category_id', ['label' => 'หมวดหมู่', 'options' => $categories, 'placeholder' => 'เลือก', 'value' => $d['category_id'] ?? '', 'required' => true]) ?>
                <?= toggle_field('is_active', ['label' => 'เปิดแสดงบนหน้าเว็บ', 'value' => $d['is_active'] ?? 1]) ?>
                <?php if (! $isNew): ?>
                <dl class="kv">
                    <dt>ลิงก์ดาวน์โหลด</dt><dd><a href="<?= esc(DownloadModel::publicUrl($d), 'attr') ?>" target="_blank" rel="noopener"><?= esc(DownloadModel::publicUrl($d)) ?></a><br><span class="muted">ลิงก์นี้คงเดิมแม้อัปไฟล์ใหม่ทับ</span></dd>
                    <dt>ยอดดาวน์โหลด</dt><dd><?= number_format((int) $d['download_count']) ?></dd>
                    <?php if ($d['type'] === 'file'): ?>
                    <dt>ไฟล์ปัจจุบัน</dt><dd><?= esc($d['original_name'] ?? '-') ?> (<?= esc(human_size($d['file_size'] === null ? null : (int) $d['file_size'])) ?>) <?= $scan ? badge($scan[0], $scan[1]) : '' ?></dd>
                    <?php endif ?>
                </dl>
                <?php endif ?>
            <?= section_close() ?>
        </div>
    </div>
    <div style="margin-top:1.5rem"><?= partial('partials/form_buttons', ['isNew' => $isNew, 'cancel' => site_url($path)]) ?></div>
</form>
<?= $this->endSection() ?>
