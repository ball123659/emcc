<?= $this->extend('layouts/backend') ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-body error-page">
        <div>
            <div class="code"><?= (int) $code ?></div>
            <h2 class="card-title" style="font-size:1.25rem;margin:.5rem 0"><?= esc($title) ?></h2>
            <p class="muted"><?= esc($message) ?></p>
            <p style="margin-top:1.5rem"><a class="btn btn-primary" href="<?= site_url('backend') ?>">กลับหน้าแดชบอร์ด</a></p>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
