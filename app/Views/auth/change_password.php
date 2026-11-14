<?php
$config = config(\Config\Emcc::class);
$this->setVar('title', 'ตั้งรหัสผ่านใหม่');
?>
<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<p class="auth-sub">นี่เป็นการเข้าสู่ระบบครั้งแรก (หรือรหัสผ่านถูกรีเซ็ต) กรุณาตั้งรหัสผ่านใหม่ก่อนใช้งาน</p>
<form method="post" action="<?= site_url('backend/change-password') ?>" data-validate>
    <?= csrf_field() ?>
    <?= text_field('password', [
        'label' => 'รหัสผ่านใหม่', 'type' => 'password', 'required' => true, 'minlength' => $config->passwordMinLength, 'maxlength' => 72,
        'autocomplete' => 'new-password', 'hint' => "อย่างน้อย {$config->passwordMinLength} ตัวอักษร ไม่เกิน 72 ไบต์ และห้ามใช้รหัสผ่านเริ่มต้น",
        'attrs' => ['data-max-bytes' => 72, 'data-not-in' => $config->defaultPassword, 'data-not-in-message' => 'ห้ามใช้รหัสผ่านเริ่มต้น กรุณาตั้งรหัสผ่านใหม่ที่ต่างออกไป', 'autofocus' => true],
    ]) ?>
    <?= text_field('password_confirmation', [
        'label' => 'ยืนยันรหัสผ่านใหม่', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password',
        'attrs' => ['data-same' => field_id('password')],
    ]) ?>
    <button type="submit" class="btn btn-primary">บันทึกรหัสผ่านใหม่</button>
</form>
<form method="post" action="<?= site_url('backend/logout') ?>" class="auth-logout">
    <?= csrf_field() ?>
    <button type="submit" class="link-btn is-gray">ออกจากระบบ</button>
</form>
<?= $this->endSection() ?>
