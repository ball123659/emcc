<?php
use App\Models\UserModel;

$u      = $user ?? [];
$isNew  = $user === null;
$path   = 'backend/users';
$isSelf = ! $isNew && (int) $u['id'] === (int) $me['id'];
$role   = (string) field_value('role', $u['role'] ?? 'user');
$picked = array_map('intval', (array) (old('pages') ?? $selectedPages));
$locked = ! $isNew && UserModel::isLocked($u);
?>
<?= $this->extend('layouts/backend') ?>

<?= $this->section('header_actions') ?>
<?php if (! $isNew && ! $isSelf): ?>
    <?php if ($locked): ?>
    <form method="post" action="<?= site_url($path . '/' . $u['id'] . '/unlock') ?>"><?= csrf_field() ?>
        <button type="submit" class="btn btn-gray" data-confirm="ปลดล็อกบัญชี <?= esc($u['username'], 'attr') ?>?" data-confirm-title="ปลดล็อก" data-confirm-ok="ปลดล็อก" data-confirm-variant="warning"><?= icon('lock-open') ?> ปลดล็อก</button></form>
    <?php endif ?>
    <form method="post" action="<?= site_url($path . '/' . $u['id'] . '/reset-password') ?>"><?= csrf_field() ?>
        <button type="submit" class="btn btn-gray" data-confirm="รหัสผ่านจะกลับเป็นค่าเริ่มต้น &quot;<?= esc($defaultPassword, 'attr') ?>&quot; และผู้ใช้ต้องตั้งรหัสใหม่ตอนเข้าสู่ระบบครั้งถัดไป" data-confirm-title="รีเซ็ตรหัสผ่านของ <?= esc($u['username'], 'attr') ?>?" data-confirm-ok="รีเซ็ตรหัสผ่าน" data-confirm-variant="warning"><?= icon('key') ?> รีเซ็ตรหัสผ่าน</button></form>
    <?= partial('partials/delete_header_button', ['url' => site_url($path . '/' . $u['id'] . '/delete'), 'label' => $u['username']]) ?>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="alert alert-warning">
    <?= icon('exclamation-circle') ?>
    <div>
        <div class="alert-title">รหัสผ่านเริ่มต้นคือ "<?= esc($defaultPassword) ?>"</div>
        <div class="alert-text">ผู้ใช้ใหม่และผู้ใช้ที่ถูกรีเซ็ตรหัสผ่านจะใช้รหัสนี้เข้าสู่ระบบครั้งแรก แล้วระบบจะบังคับให้ตั้งรหัสใหม่ (อย่างน้อย <?= (int) config(\Config\Emcc::class)->passwordMinLength ?> ตัวอักษร) ก่อนใช้งานเมนูอื่น</div>
    </div>
</div>

<form method="post" action="<?= site_url($isNew ? $path : $path . '/' . $u['id']) ?>" data-validate class="page-body">
    <?= csrf_field() ?>
    <?= section_open('ข้อมูลผู้ใช้งาน', null, ['grid' => 2]) ?>
        <?= text_field('username', ['label' => 'ชื่อผู้ใช้ (Username)', 'value' => $u['username'] ?? '', 'required' => true, 'maxlength' => 50, 'autocomplete' => 'off',
            'pattern' => '[A-Za-z0-9._\-]+', 'pattern_message' => 'ชื่อผู้ใช้ใช้ได้เฉพาะ a-z, 0-9, จุด, ขีดล่าง และขีด (ห้ามมีช่องว่างหรือภาษาไทย)',
            'hint' => 'ใช้ได้เฉพาะ a-z, 0-9, จุด (.), ขีดล่าง (_) และขีด (-) ระบบจะแปลงเป็นตัวพิมพ์เล็กให้', 'attrs' => ['autocapitalize' => 'none']]) ?>
        <?= text_field('email', ['label' => 'อีเมล', 'type' => 'email', 'value' => $u['email'] ?? '', 'required' => true, 'maxlength' => 191, 'autocomplete' => 'off']) ?>
        <?php if ($isSelf): ?>
        <div class="span-full" data-self-email="<?= esc($u['email'], 'attr') ?>" <?= field_error('current_password') ? '' : 'hidden' ?>>
            <?= text_field('current_password', ['label' => 'รหัสผ่านปัจจุบันของคุณ', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password', 'hint' => 'เปลี่ยนอีเมลของตัวเองต้องยืนยันรหัสผ่าน']) ?>
        </div>
        <?php endif ?>
        <?= text_field('first_name', ['label' => 'ชื่อ', 'value' => $u['first_name'] ?? '', 'required' => true, 'maxlength' => 100]) ?>
        <?= text_field('last_name', ['label' => 'นามสกุล', 'value' => $u['last_name'] ?? '', 'required' => true, 'maxlength' => 100]) ?>
        <?= text_field('phone', ['label' => 'เบอร์โทรศัพท์', 'type' => 'tel', 'value' => $u['phone'] ?? '', 'maxlength' => 30, 'pattern' => '[0-9\-\s]*', 'pattern_message' => 'เบอร์โทรศัพท์ใส่ได้เฉพาะตัวเลขและขีด (-)', 'hint' => 'ตัวอย่าง 081-234-5678 (ระบบเก็บเฉพาะตัวเลข)', 'inputmode' => 'tel']) ?>
        <?= text_field('position', ['label' => 'ตำแหน่ง', 'value' => $u['position'] ?? '', 'maxlength' => 150]) ?>
        <?= select_field('role', ['label' => 'บทบาท', 'options' => $isSelf ? [$u['role'] => UserModel::ROLES[$u['role']]] : $roles, 'value' => $role, 'required' => true, 'disabled' => $isSelf,
            'hint' => $isSelf ? 'เปลี่ยนบทบาทของตัวเองไม่ได้' : 'Developer / Administrator เข้าได้ทุกหน้าและจัดการผู้ใช้ได้, User เห็นเฉพาะหน้าที่ให้สิทธิ์']) ?>
        <?= toggle_field('is_active', ['label' => 'เปิดใช้งาน', 'value' => $u['is_active'] ?? 1, 'disabled' => $isSelf, 'hint' => $isSelf ? 'ปิดบัญชีของตัวเองไม่ได้' : 'ปิดแล้วผู้ใช้จะเข้าสู่ระบบไม่ได้']) ?>
    <?= section_close() ?>

    <?= section_open('สิทธิ์การเข้าถึงหน้า', 'เลือกหน้าที่ผู้ใช้คนนี้เห็นและใช้งานได้ (ค่าเริ่มต้นคือไม่เห็นทุกหน้า)') ?>
        <?php if (! $isSelf): ?>
        <div data-show-when="role=user" class="field">
            <div><button type="button" class="link-btn" data-check-all="pages[]">เลือกทั้งหมด</button></div>
            <div class="checkbox-grid">
                <?php foreach ($pageOptions as $id => $label): ?>
                <label class="check-inline"><input type="checkbox" name="pages[]" value="<?= (int) $id ?>"<?= in_array((int) $id, $picked, true) ? ' checked' : '' ?>> <?= esc($label) ?></label>
                <?php endforeach ?>
            </div>
            <p class="field-hint">หน้า "จัดการผู้ใช้งาน" และ "บันทึกการใช้งาน" ให้สิทธิ์ได้เฉพาะ Developer / Administrator</p>
        </div>
        <p data-show-when="role=developer|administrator" class="muted">Developer และ Administrator เข้าได้ทุกหน้าเสมอ ไม่ต้องกำหนดสิทธิ์</p>
        <?php else: ?>
        <p class="muted"><?= UserModel::isAdmin($u) ? 'Developer และ Administrator เข้าได้ทุกหน้าเสมอ ไม่ต้องกำหนดสิทธิ์' : 'แก้สิทธิ์ของตัวเองไม่ได้' ?></p>
        <?php endif ?>
    <?= section_close() ?>

    <?= partial('partials/form_buttons', ['isNew' => $isNew, 'cancel' => site_url($path)]) ?>
</form>
<?= $this->endSection() ?>
