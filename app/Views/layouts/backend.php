<?php
/**
 * โครงหน้าหลังบ้าน: แถบบน + เมนูข้าง + แถบหัวสีม่วง (การ์ดซ้อนทับแถบ)
 * ตัวแปร: $title, $breadcrumbs [label => url|null], $subheading
 * section: header_actions, header_extra (เช่น แท็บ), content
 */
$user        = service('auth')->user();
$title       = $title ?? 'EMCC Backend';
$breadcrumbs = $breadcrumbs ?? [];
$toast       = session('toast');
$version     = static fn (string $file) => base_url($file) . '?v=' . @filemtime(FCPATH . $file);
$initial     = mb_substr(trim((string) ($user['first_name'] ?? '')) ?: (string) ($user['username'] ?? '?'), 0, 1);
?><!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-name" content="<?= esc(csrf_token(), 'attr') ?>">
    <meta name="csrf-token" content="<?= esc(csrf_hash(), 'attr') ?>">
    <meta name="upload-max" content="<?= (int) ini_bytes((string) ini_get('upload_max_filesize')) ?>">
    <meta name="post-max" content="<?= (int) ini_bytes((string) ini_get('post_max_size')) ?>">
    <title><?= esc($title) ?> - EMCC Backend</title>
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <link rel="stylesheet" href="<?= $version('fonts/prompt/prompt.css') ?>">
    <link rel="stylesheet" href="<?= $version('assets/vendor/flatpickr/flatpickr.min.css') ?>">
    <?= $this->renderSection('styles') ?>
    <link rel="stylesheet" href="<?= $version('assets/css/admin.css') ?>">
    <script>try { if (localStorage.getItem('emcc.sidebar') === 'collapsed') document.documentElement.classList.add('sidebar-collapsed'); } catch (e) {}</script>
    <script>try { var t = localStorage.getItem('emcc.theme') || 'system'; if (t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}</script>
</head>
<body>
<a class="skip-link" href="#main">ข้ามไปยังเนื้อหา</a>
<header class="topbar">
    <button type="button" class="icon-btn sidebar-toggle" data-sidebar-toggle aria-label="ซ่อน/แสดงเมนู" title="ซ่อน/แสดงเมนู">
        <?= icon('chevron-left', 'icon icon-collapse') ?><?= icon('bars-3', 'icon icon-expand') ?>
    </button>
    <a class="brand" href="<?= site_url('backend') ?>">EMCC Backend</a>
    <div class="topbar-spacer"></div>
    <?php if ($user && ! $user['must_change_password']): ?>
    <button type="button" class="icon-btn search-toggle" data-search-toggle aria-label="ค้นหา" title="ค้นหา"><?= icon('magnifying-glass') ?></button>
    <div class="global-search" data-global-search>
        <?= icon('magnifying-glass', 'icon search-icon') ?>
        <input type="search" placeholder="ค้นหา..." aria-label="ค้นหาทั้งระบบ" autocomplete="off" data-url="<?= site_url('backend/search') ?>">
        <div class="global-search-results" hidden></div>
    </div>
    <?php endif ?>
    <div class="user-menu" data-dropdown>
        <button type="button" class="avatar" data-dropdown-toggle aria-haspopup="true" aria-expanded="false" title="<?= esc(\App\Models\UserModel::fullName($user ?? [])) ?>"><?= esc($initial) ?></button>
        <div class="dropdown-panel" hidden>
            <div class="dropdown-head">
                <strong><?= esc(\App\Models\UserModel::fullName($user ?? [])) ?></strong>
                <span><?= esc($user['username'] ?? '') ?> · <?= esc(\App\Models\UserModel::ROLES[$user['role'] ?? 'user'] ?? '') ?></span>
            </div>
            <div class="theme-switch" role="group" aria-label="ธีม">
                <button type="button" data-theme="light" title="สว่าง" aria-label="ธีมสว่าง"><?= icon('sun') ?></button>
                <button type="button" data-theme="dark" title="มืด" aria-label="ธีมมืด"><?= icon('moon') ?></button>
                <button type="button" data-theme="system" title="ตามระบบ" aria-label="ธีมตามระบบ"><?= icon('computer-desktop') ?></button>
            </div>
            <?php if ($user && ! $user['must_change_password']): ?>
            <a class="dropdown-item" href="<?= site_url('backend/profile') ?>"><?= icon('user-circle') ?> โปรไฟล์ของฉัน</a>
            <?php endif ?>
            <form method="post" action="<?= site_url('backend/logout') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="dropdown-item"><?= icon('arrow-left-start-on-rectangle') ?> ออกจากระบบ</button>
            </form>
        </div>
    </div>
</header>

<div class="shell">
    <aside class="sidebar" aria-label="เมนูหลัก">
        <nav class="sidebar-nav">
            <?php foreach (backend_nav() as $i => $group): ?>
            <div class="nav-group<?= $group['label'] ? ' has-label' : '' ?>" data-nav-group="<?= $i ?>">
                <?php if ($group['label']): ?>
                <button type="button" class="nav-group-label" data-nav-group-toggle aria-expanded="true">
                    <span><?= esc($group['label']) ?></span><?= icon('chevron-up', 'icon chevron') ?>
                </button>
                <?php endif ?>
                <ul class="nav-items">
                    <?php foreach ($group['items'] as $item): ?>
                    <li><a class="nav-item<?= $item['active'] ? ' is-active' : '' ?>" href="<?= esc($item['url'], 'attr') ?>"<?= $item['active'] ? ' aria-current="page"' : '' ?>><?= icon($item['icon']) ?><span><?= esc($item['label']) ?></span></a></li>
                    <?php endforeach ?>
                </ul>
            </div>
            <?php endforeach ?>
        </nav>
    </aside>
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <main class="main" id="main">
        <div class="page-header">
            <div class="page-header-row">
                <div class="page-header-text">
                    <?php if ($breadcrumbs !== []): ?>
                    <nav class="breadcrumbs" aria-label="breadcrumb">
                        <?php $last = array_key_last($breadcrumbs); foreach ($breadcrumbs as $label => $url): ?>
                            <?php if ($url): ?><a href="<?= esc($url, 'attr') ?>"><?= esc($label) ?></a><?php else: ?><span><?= esc($label) ?></span><?php endif ?>
                            <?php if ($label !== $last): ?><?= icon('chevron-right', 'icon sep') ?><?php endif ?>
                        <?php endforeach ?>
                    </nav>
                    <?php endif ?>
                    <h1 class="page-title"><?= esc($title) ?></h1>
                    <?php if (! empty($subheading)): ?><p class="page-subheading"><?= esc($subheading) ?></p><?php endif ?>
                </div>
                <div class="page-actions"><?= $this->renderSection('header_actions') ?></div>
            </div>
            <?= $this->renderSection('header_extra') ?>
        </div>
        <div class="page-body">
            <?= $this->renderSection('content') ?>
        </div>
    </main>
</div>

<div class="toasts" aria-live="polite" data-toasts></div>
<?php if (is_array($toast)): ?>
<script type="application/json" data-initial-toast><?= json_encode($toast, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif ?>
<?= $this->include('partials/confirm_modal') ?>
<script src="<?= $version('assets/vendor/flatpickr/flatpickr.min.js') ?>"></script>
<script src="<?= $version('assets/vendor/flatpickr/th.js') ?>"></script>
<script src="<?= $version('assets/vendor/sortable/Sortable.min.js') ?>"></script>
<?= $this->renderSection('scripts_vendor') ?>
<script src="<?= $version('assets/js/admin.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
