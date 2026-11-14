<?php
use App\Models\UserModel;

$cfg         = config(\Config\Emcc::class);
$canUsername = UserModel::isAdmin($me);
?>
<?= $this->extend('layouts/backend') ?>
<?= $this->section('content') ?>
<form method="post" action="<?= site_url('backend/profile') ?>" data-validate class="page-body">
    <?= csrf_field() ?>
    <?= section_open('ข้อมูลส่วนตัว', null, ['grid' => 2]) ?>
        <?= text_field('username', $canUsername
            ? ['label' => 'ชื่อผู้ใช้ (Username)', 'value' => $me['username'], 'required' => true, 'maxlength' => 50, 'pattern' => '[A-Za-z0-9._\-]+',
               'pattern_message' => 'ชื่อผู้ใช้ใช้ได้เฉพาะ a-z, 0-9, จุด, ขีดล่าง และขีด (ห้ามมีช่องว่างหรือภาษาไทย)', 'hint' => 'ใช้ได้เฉพาะ a-z, 0-9, จุด (.), ขีดล่าง (_) และขีด (-) ระบบจะแปลงเป็นตัวพิมพ์เล็กให้']
            : ['label' => 'ชื่อผู้ใช้ (Username)', 'value' => $me['username'], 'disabled' => true, 'hint' => 'ต้องการเปลี่ยนชื่อผู้ใช้ กรุณาติดต่อผู้ดูแลระบบ']) ?>
        <?= text_field('email', ['label' => 'อีเมล', 'type' => 'email', 'value' => $me['email'], 'required' => true, 'maxlength' => 191]) ?>
        <div class="span-full" data-self-email="<?= esc($me['email'], 'attr') ?>" <?= field_error('current_password') ? '' : 'hidden' ?>>
            <?= text_field('current_password', ['label' => 'รหัสผ่านปัจจุบัน', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password', 'hint' => 'เปลี่ยนอีเมลต้องยืนยันรหัสผ่าน']) ?>
        </div>
        <?= text_field('first_name', ['label' => 'ชื่อ', 'value' => $me['first_name'], 'required' => true, 'maxlength' => 100]) ?>
        <?= text_field('last_name', ['label' => 'นามสกุล', 'value' => $me['last_name'], 'required' => true, 'maxlength' => 100]) ?>
        <?= text_field('phone', ['label' => 'เบอร์โทรศัพท์', 'type' => 'tel', 'value' => $me['phone'] ?? '', 'maxlength' => 30, 'pattern' => '[0-9\-\s]*', 'pattern_message' => 'เบอร์โทรศัพท์ใส่ได้เฉพาะตัวเลขและขีด (-)', 'hint' => 'ตัวอย่าง 081-234-5678 (ระบบเก็บเฉพาะตัวเลข)']) ?>
        <?= text_field('position', ['label' => 'ตำแหน่ง', 'value' => $me['position'] ?? '', 'maxlength' => 150]) ?>
    <?= section_close() ?>
    <div class="form-actions"><button type="submit" class="btn btn-primary">บันทึกข้อมูลส่วนตัว</button></div>
</form>

<form method="post" action="<?= site_url('backend/profile/password') ?>" data-validate class="page-body" style="margin-top:1.5rem">
    <?= csrf_field() ?>
    <?= section_open('เปลี่ยนรหัสผ่าน', 'เปลี่ยนแล้วอุปกรณ์อื่นที่ล็อกอินค้างไว้จะต้องเข้าสู่ระบบใหม่', ['grid' => 3]) ?>
        <?= text_field('current_password_pw', ['label' => 'รหัสผ่านปัจจุบัน', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
        <?= text_field('password', ['label' => 'รหัสผ่านใหม่', 'type' => 'password', 'required' => true, 'minlength' => $cfg->passwordMinLength, 'maxlength' => 100, 'autocomplete' => 'new-password',
            'hint' => "อย่างน้อย {$cfg->passwordMinLength} ตัวอักษร และห้ามใช้รหัสผ่านเริ่มต้น",
            'attrs' => ['data-not-in' => $cfg->defaultPassword, 'data-not-in-message' => 'ห้ามใช้รหัสผ่านเริ่มต้น กรุณาตั้งรหัสผ่านใหม่ที่ต่างออกไป']]) ?>
        <?= text_field('password_confirmation', ['label' => 'ยืนยันรหัสผ่านใหม่', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'attrs' => ['data-same' => field_id('password')]]) ?>
    <?= section_close() ?>
    <div class="form-actions"><button type="submit" class="btn btn-primary">เปลี่ยนรหัสผ่าน</button></div>
</form>
<?= $this->endSection() ?>
