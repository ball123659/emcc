<?php
use App\Controllers\Backend\SiteSettings;
use App\Models\SiteSettingModel;
?>
<?= $this->extend('layouts/backend') ?>
<?= $this->section('content') ?>
<form method="post" enctype="multipart/form-data" action="<?= site_url('backend/site-settings') ?>" data-validate class="page-body">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" value="<?= esc($active, 'attr') ?>" id="settings-tab">
    <div class="card">
        <div class="tab-list" role="tablist">
            <?php foreach ($groups as $group => $items): ?>
            <button type="button" class="tab<?= $group === $active ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $group === $active ? 'true' : 'false' ?>" data-tab-target="settings-<?= esc($group, 'attr') ?>" onclick="document.getElementById('settings-tab').value='<?= esc($group, 'js') ?>'">
                <?= icon(SiteSettings::GROUP_ICONS[$group] ?? 'cog-6-tooth') ?> <?= esc(SiteSettingModel::GROUPS[$group] ?? $group) ?>
            </button>
            <?php endforeach ?>
        </div>
        <?php foreach ($groups as $group => $items): ?>
        <div class="tab-panel card-body grid-2" id="settings-<?= esc($group, 'attr') ?>" role="tabpanel"<?= $group === $active ? '' : ' hidden' ?>>
            <?php foreach ($items as $s):
                $k     = $s['key'];
                $label = $s['label_th'];
                $nTh   = "settings[{$k}][th]";
                $nEn   = "settings[{$k}][en]";
                $enHint = 'เว้นว่างได้ — หน้าเว็บภาษาอังกฤษจะใช้ภาษาไทยแทน';
            ?>
                <?php switch ($s['input_type']):
                    case 'textarea': ?>
                    <?= textarea_field($nTh, ['label' => "{$label} (ไทย)", 'value' => $s['value_th'], 'rows' => 3, 'maxlength' => 2000]) ?>
                    <?= textarea_field($nEn, ['label' => "{$label} (อังกฤษ)", 'value' => $s['value_en'], 'rows' => 3, 'maxlength' => 2000, 'hint' => $enHint]) ?>
                    <?php break; case 'url': ?>
                    <div class="span-full"><?= text_field($nTh, ['label' => $label, 'type' => 'url', 'value' => $s['value_th'], 'maxlength' => 500, 'placeholder' => 'https://']) ?></div>
                    <?php break; case 'email': ?>
                    <div class="span-full"><?= text_field($nTh, ['label' => $label, 'type' => 'email', 'value' => $s['value_th'], 'maxlength' => 191]) ?></div>
                    <?php break; case 'phone': ?>
                    <div class="span-full"><?= text_field($nTh, ['label' => $label, 'type' => 'tel', 'value' => $s['value_th'], 'maxlength' => 100, 'pattern' => '[0-9+\-\s\/,()]*', 'pattern_message' => 'ใส่ได้เฉพาะตัวเลข ขีด (-) ทับ (/) และช่องว่าง', 'hint' => 'ใส่ได้หลายเบอร์ คั่นด้วย / เช่น 038-683933 / 081-7323485']) ?></div>
                    <?php break; case 'image': ?>
                    <div class="span-full"><?= image_field('setting_image_' . $k, ['label' => $label, 'current' => $s['value_th'], 'removable' => true, 'hint' => 'ขนาดแนะนำตามการใช้งาน']) ?></div>
                    <?php break; default: ?>
                    <?= text_field($nTh, ['label' => "{$label} (ไทย)", 'value' => $s['value_th'], 'maxlength' => 500]) ?>
                    <?= text_field($nEn, ['label' => "{$label} (อังกฤษ)", 'value' => $s['value_en'], 'maxlength' => 500, 'hint' => $enHint]) ?>
                <?php endswitch ?>
            <?php endforeach ?>
        </div>
        <?php endforeach ?>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn-primary">บันทึก</button></div>
</form>
<?= $this->endSection() ?>
