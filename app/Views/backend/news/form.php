<?php
use App\Libraries\Uploads\DocumentStorer;
use App\Models\NewsModel;

$n      = $news ?? [];
$isNew  = $news === null;
$path   = 'backend/news';
$locked = ! $isNew && NewsModel::isSlugLocked($n);
$cfg    = config(\Config\Emcc::class);
$status = (string) field_value('status', $n['status'] ?? 'draft');
?>
<?= $this->extend('layouts/backend') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/quill/quill.snow.css') ?>">
<?= $this->endSection() ?>
<?= $this->section('scripts_vendor') ?>
<script src="<?= base_url('assets/vendor/quill/quill.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->section('header_actions') ?>
<?php if (! $isNew): ?><?= partial('partials/delete_header_button', ['url' => site_url($path . '/' . $n['id'] . '/delete'), 'label' => mb_strimwidth($n['title_th'], 0, 60, '…')]) ?><?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<form method="post" enctype="multipart/form-data" action="<?= site_url($isNew ? $path : $path . '/' . $n['id']) ?>" data-validate>
    <?= csrf_field() ?>
    <div class="layout-main-aside">
        <div>
            <?= section_open('เนื้อหาข่าว') ?>
                <div class="inner-tabs">
                    <div class="tab-list" role="tablist">
                        <button type="button" class="tab is-active" role="tab" aria-selected="true" data-tab-target="tab-th">ภาษาไทย</button>
                        <button type="button" class="tab" role="tab" aria-selected="false" data-tab-target="tab-en">English</button>
                    </div>
                    <div class="tab-panel" id="tab-th" role="tabpanel">
                        <?= text_field('title_th', ['label' => 'หัวข้อข่าว', 'value' => $n['title_th'] ?? '', 'required' => true, 'maxlength' => 255]) ?>
                        <?= textarea_field('excerpt_th', ['label' => 'คำโปรย', 'value' => $n['excerpt_th'] ?? '', 'maxlength' => 500, 'rows' => 2, 'hint' => 'แสดงบนการ์ดข่าวหน้ารายการ ไม่เกิน 500 ตัวอักษร']) ?>
                        <?= field_wrap('content_th', 'เนื้อหา', '<textarea name="content_th" id="f-content-th" data-rich data-required="1">' . esc((string) field_value('content_th', $n['content_th'] ?? '')) . '</textarea>', ['required' => true, 'hint' => 'รูปภาพให้เพิ่มในส่วน "แกลเลอรีรูป" ด้านล่าง — รูปที่วางหรือลากลงในเนื้อหาจะถูกนำออกตอนบันทึก']) ?>
                    </div>
                    <div class="tab-panel" id="tab-en" role="tabpanel" hidden>
                        <?= text_field('title_en', ['label' => 'หัวข้อข่าว (อังกฤษ)', 'value' => $n['title_en'] ?? '', 'maxlength' => 255, 'hint' => 'เว้นว่างได้ — หน้าเว็บภาษาอังกฤษจะใช้ภาษาไทยแทน']) ?>
                        <?= textarea_field('excerpt_en', ['label' => 'คำโปรย (อังกฤษ)', 'value' => $n['excerpt_en'] ?? '', 'maxlength' => 500, 'rows' => 2]) ?>
                        <?= field_wrap('content_en', 'เนื้อหา (อังกฤษ)', '<textarea name="content_en" id="f-content-en" data-rich>' . esc((string) field_value('content_en', $n['content_en'] ?? '')) . '</textarea>', ['hint' => 'รูปภาพให้เพิ่มในส่วน "แกลเลอรีรูป" ด้านล่าง — รูปที่วางหรือลากลงในเนื้อหาจะถูกนำออกตอนบันทึก']) ?>
                    </div>
                </div>
            <?= section_close() ?>

            <?= section_open('รูปปก', null, ['grid' => 2]) ?>
                <div class="span-full"><?= image_field('cover_image', ['label' => 'รูปปก', 'current' => $n['cover_image'] ?? null, 'required' => true, 'hint' => 'ขนาดแนะนำ 1200 × 675 (16:9) px']) ?></div>
                <?= text_field('cover_alt_th', ['label' => 'คำอธิบายรูปปก (ไทย)', 'value' => $n['cover_alt_th'] ?? '', 'maxlength' => 255, 'hint' => 'เว้นว่าง = ใช้หัวข้อข่าว']) ?>
                <?= text_field('cover_alt_en', ['label' => 'คำอธิบายรูปปก (อังกฤษ)', 'value' => $n['cover_alt_en'] ?? '', 'maxlength' => 255]) ?>
            <?= section_close() ?>

            <?= section_open('แกลเลอรีรูป', 'ลากที่ไอคอน ☰ เพื่อเรียงลำดับ · ใส่คำบรรยายแต่ละรูปได้ · ขนาดแนะนำด้านยาว 1920 px, JPG/PNG/WebP ไม่เกิน ' . mb_label(upload_limit_kb($cfg->imageMaxKb)), ['collapsible' => true]) ?>
                <div class="repeater" data-repeater>
                    <?php if (field_error('gallery')): ?><p class="field-error"><?= esc(field_error('gallery')) ?></p><?php endif ?>
                    <div class="repeater" data-repeater-list>
                        <?php foreach ($images as $img): $key = 'images[' . $img['id'] . ']'; ?>
                        <div class="repeater-item" data-repeater-item>
                            <span class="handle" title="ลากเพื่อเรียงลำดับ"><?= icon('bars-3') ?></span>
                            <div class="repeater-head" style="align-items:flex-start">
                                <img src="<?= esc((string) media_url($img['thumb_path']), 'attr') ?>" alt="">
                                <div class="repeater-fields" style="flex:1">
                                    <?= text_field($key . '[caption_th]', ['label' => 'คำบรรยาย (ไทย)', 'value' => $img['caption_th'] ?? '', 'maxlength' => 255]) ?>
                                    <?= text_field($key . '[caption_en]', ['label' => 'คำบรรยาย (อังกฤษ)', 'value' => $img['caption_en'] ?? '', 'maxlength' => 255]) ?>
                                </div>
                            </div>
                            <div>
                                <input type="hidden" name="<?= $key ?>[sort]" value="<?= (int) $img['sort_order'] ?>" data-sort-input>
                                <input type="hidden" name="<?= $key ?>[delete]" value="0" data-delete-input>
                                <button type="button" class="link-btn is-danger" data-repeater-remove>ลบ</button>
                            </div>
                        </div>
                        <?php endforeach ?>
                    </div>
                    <p class="muted" data-repeater-empty<?= $images ? ' hidden' : '' ?>>ยังไม่มีรูปในแกลเลอรี</p>
                    <button type="button" class="btn btn-gray repeater-add" data-repeater-add><?= icon('plus') ?> เพิ่มรูป</button>
                    <template data-repeater-template>
                        <div class="repeater-item" data-repeater-item>
                            <span class="handle" title="ลากเพื่อเรียงลำดับ"><?= icon('bars-3') ?></span>
                            <div class="repeater-head" style="align-items:flex-start">
                                <img data-preview alt="" hidden>
                                <div class="repeater-fields" style="flex:1">
                                    <div class="field span-full"><label class="field-label">รูป <span class="muted" data-file-name></span></label>
                                        <input type="file" name="gallery_new[__INDEX__]" accept="image/jpeg,image/png,image/webp" required class="input"><p class="field-error" hidden></p></div>
                                    <div class="field"><label class="field-label">คำบรรยาย (ไทย)</label><input type="text" class="input" name="gallery_new_meta[__INDEX__][caption_th]" maxlength="255"><p class="field-error" hidden></p></div>
                                    <div class="field"><label class="field-label">คำบรรยาย (อังกฤษ)</label><input type="text" class="input" name="gallery_new_meta[__INDEX__][caption_en]" maxlength="255"><p class="field-error" hidden></p></div>
                                </div>
                            </div>
                            <div>
                                <input type="hidden" name="gallery_new_meta[__INDEX__][sort]" value="999" data-sort-input>
                                <button type="button" class="link-btn is-danger" data-repeater-remove>ลบ</button>
                            </div>
                        </div>
                    </template>
                </div>
            <?= section_close() ?>

            <?= section_open('ไฟล์แนบ', 'ไฟล์ผ่านการสแกนไวรัสก่อนบันทึก และดาวน์โหลดผ่านระบบเท่านั้น · รองรับ ' . DocumentStorer::extensionsLabel() . ' ไม่เกิน ' . mb_label(upload_limit_kb($cfg->fileMaxKb)), ['collapsible' => true]) ?>
                <div class="repeater" data-repeater>
                    <?php if (field_error('attachments')): ?><p class="field-error"><?= esc(field_error('attachments')) ?></p><?php endif ?>
                    <div class="repeater" data-repeater-list>
                        <?php foreach ($attachments as $att): $key = 'attachments[' . $att['id'] . ']'; ?>
                        <div class="repeater-item" data-repeater-item>
                            <span class="handle" title="ลากเพื่อเรียงลำดับ"><?= icon('bars-3') ?></span>
                            <div style="display:grid;gap:.75rem">
                                <div class="repeater-head">
                                    <?= icon('paper-clip', 'icon muted') ?>
                                    <a href="<?= site_url('news/attachments/' . $att['id']) ?>" target="_blank" rel="noopener"><?= esc($att['original_name']) ?></a>
                                    <span class="muted"><?= esc(human_size((int) $att['file_size'])) ?></span>
                                    <?= $att['scan_status'] === 'clean' ? badge('สแกนผ่าน', 'success') : badge('ยังไม่ผ่านการสแกน', 'danger') ?>
                                </div>
                                <div class="repeater-fields">
                                    <?= text_field($key . '[title_th]', ['label' => 'ชื่อไฟล์ที่แสดง (ไทย)', 'value' => $att['title_th'], 'required' => true, 'maxlength' => 255]) ?>
                                    <?= text_field($key . '[title_en]', ['label' => 'ชื่อไฟล์ที่แสดง (อังกฤษ)', 'value' => $att['title_en'] ?? '', 'maxlength' => 255]) ?>
                                </div>
                            </div>
                            <div>
                                <input type="hidden" name="<?= $key ?>[sort]" value="<?= (int) $att['sort_order'] ?>" data-sort-input>
                                <input type="hidden" name="<?= $key ?>[delete]" value="0" data-delete-input>
                                <button type="button" class="link-btn is-danger" data-repeater-remove>ลบ</button>
                            </div>
                        </div>
                        <?php endforeach ?>
                    </div>
                    <p class="muted" data-repeater-empty<?= $attachments ? ' hidden' : '' ?>>ยังไม่มีไฟล์แนบ</p>
                    <button type="button" class="btn btn-gray repeater-add" data-repeater-add><?= icon('plus') ?> เพิ่มไฟล์แนบ</button>
                    <template data-repeater-template>
                        <div class="repeater-item" data-repeater-item>
                            <span class="handle" title="ลากเพื่อเรียงลำดับ"><?= icon('bars-3') ?></span>
                            <div class="repeater-fields">
                                <div class="field span-full"><label class="field-label">ไฟล์ <span class="muted" data-file-name></span></label>
                                    <input type="file" name="attachment_new[__INDEX__]" accept="<?= esc(DocumentStorer::acceptAttribute(), 'attr') ?>" required class="input"><p class="field-error" hidden></p></div>
                                <div class="field"><label class="field-label">ชื่อไฟล์ที่แสดง (ไทย)<sup class="req">*</sup></label><input type="text" class="input" name="attachment_new_meta[__INDEX__][title_th]" maxlength="255" required data-default-title><p class="field-error" hidden></p></div>
                                <div class="field"><label class="field-label">ชื่อไฟล์ที่แสดง (อังกฤษ)</label><input type="text" class="input" name="attachment_new_meta[__INDEX__][title_en]" maxlength="255"><p class="field-error" hidden></p></div>
                            </div>
                            <div>
                                <input type="hidden" name="attachment_new_meta[__INDEX__][sort]" value="999" data-sort-input>
                                <button type="button" class="link-btn is-danger" data-repeater-remove>ลบ</button>
                            </div>
                        </div>
                    </template>
                </div>
            <?= section_close() ?>
        </div>

        <div>
            <?= section_open('การเผยแพร่') ?>
                <?= select_field('status', ['label' => 'สถานะ', 'options' => NewsModel::STATUSES, 'value' => $status, 'required' => true, 'hint' => 'ร่าง — ยังไม่แสดงหน้าเว็บ · เผยแพร่ — แสดงหน้าเว็บตั้งแต่วันที่เผยแพร่ · ซ่อน — เคยเผยแพร่แล้วแต่ไม่แสดงหน้าเว็บ']) ?>
                <?= date_field('published_at', ['label' => 'วันที่เผยแพร่', 'value' => $n['published_at'] ?? '', 'hint' => 'เว้นว่าง = เผยแพร่วันนี้ · เลือกวันในอนาคต = ตั้งเวลาเผยแพร่ (ขึ้นหน้าเว็บตั้งแต่ 00:00 น. ของวันนั้น)']) ?>
                <?= select_field('category_id', ['label' => 'หมวดหมู่', 'options' => $categories, 'placeholder' => 'เลือก', 'value' => $n['category_id'] ?? '', 'required' => true]) ?>
                <?= toggle_field('is_pinned', ['label' => 'ปักหมุดไว้บนสุด', 'value' => $n['is_pinned'] ?? 0]) ?>
                <?= text_field('slug', [
                    'label' => 'ชื่อใน URL (slug)', 'value' => $n['slug'] ?? '', 'maxlength' => 200, 'prefix' => '/news/', 'disabled' => $locked,
                    'pattern' => '[a-zA-Z0-9\-]*', 'pattern_message' => 'ใช้ได้เฉพาะ a-z, 0-9 และขีด (-) ห้ามมีช่องว่างหรือภาษาไทย',
                    'hint' => $locked ? 'ข่าวเผยแพร่แล้ว เปลี่ยนไม่ได้ เพื่อไม่ให้ลิงก์ที่แชร์ไปแล้วใช้ไม่ได้'
                        : 'เว้นว่างเพื่อสร้างอัตโนมัติ (ปี-เดือน-ชื่อภาษาอังกฤษ หรือ ปี-เดือน-เลขข่าว) · ใช้ได้เฉพาะ a-z, 0-9 และขีด (-) · เปลี่ยนได้จนกว่าจะเผยแพร่',
                ]) ?>
                <?php if (! $isNew): ?>
                <dl class="kv">
                    <dt>ยอดเข้าชม</dt><dd><?= number_format((int) $n['view_count']) ?></dd>
                    <dt>แก้ไขล่าสุด</dt><dd><?= esc(th_datetime($n['updated_at'])) ?></dd>
                </dl>
                <?php endif ?>
            <?= section_close() ?>
        </div>
    </div>
    <div style="margin-top:1.5rem"><?= partial('partials/form_buttons', ['isNew' => $isNew, 'cancel' => site_url($path)]) ?></div>
</form>
<?= $this->endSection() ?>
