<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<form method="post" action="<?= site_url('backend/login') ?>" data-validate>
    <?= csrf_field() ?>
    <?= text_field('username', ['label' => 'ชื่อผู้ใช้ (Username)', 'required' => true, 'maxlength' => 50, 'autocomplete' => 'username', 'attrs' => ['autofocus' => true, 'autocapitalize' => 'none']]) ?>
    <?= text_field('password', ['label' => 'รหัสผ่าน', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
    <label class="check-inline"><input type="checkbox" name="remember" value="1"<?= old('remember') === '1' ? ' checked' : '' ?>> จดจำฉัน</label>
    <button type="submit" class="btn btn-primary">เข้าสู่ระบบ</button>
</form>
<?= $this->endSection() ?>
