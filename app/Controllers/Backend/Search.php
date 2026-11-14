<?php

namespace App\Controllers\Backend;

use App\Models\DownloadModel;
use App\Models\NewsModel;
use App\Models\PopupModel;
use App\Models\SlideModel;
use App\Models\UserModel;

/**
 * ค้นหาทั้งระบบ (ช่องค้นหาบนแถบบน) — แสดงเฉพาะส่วนที่ผู้ใช้มีสิทธิ์
 */
class Search extends BackendController
{
    public function index()
    {
        $q      = $this->searchTerm();
        $groups = [];

        if (mb_strlen($q) < 2) {
            return $this->json(['groups' => []]);
        }

        if ($this->auth->canViewPage('news')) {
            $rows = (new NewsModel())->groupStart()->like('title_th', $q)->orLike('title_en', $q)->groupEnd()->orderBy('id', 'desc')->findAll(5);
            $groups[] = ['label' => 'ข่าว', 'items' => array_map(static fn ($r) => ['title' => $r['title_th'], 'sub' => NewsModel::DISPLAY_LABELS[NewsModel::displayStatus($r)], 'url' => site_url('backend/news/' . $r['id'] . '/edit')], $rows)];
        }

        if ($this->auth->canViewPage('downloads')) {
            $rows = (new DownloadModel())->groupStart()->like('title_th', $q)->orLike('title_en', $q)->orLike('original_name', $q)->groupEnd()->orderBy('id', 'desc')->findAll(5);
            $groups[] = ['label' => 'ไฟล์ดาวน์โหลด', 'items' => array_map(static fn ($r) => ['title' => $r['title_th'], 'sub' => $r['original_name'] ?? $r['external_url'], 'url' => site_url('backend/downloads/' . $r['id'] . '/edit')], $rows)];
        }

        if ($this->auth->canViewPage('slides')) {
            $rows = (new SlideModel())->groupStart()->like('title', $q)->orLike('alt_th', $q)->groupEnd()->findAll(5);
            $groups[] = ['label' => 'Slide Show', 'items' => array_map(static fn ($r) => ['title' => $r['title'], 'sub' => $r['alt_th'], 'url' => site_url('backend/slides/' . $r['id'] . '/edit')], $rows)];
        }

        if ($this->auth->canViewPage('popups')) {
            $rows = (new PopupModel())->groupStart()->like('title', $q)->orLike('alt_th', $q)->groupEnd()->findAll(5);
            $groups[] = ['label' => 'Popup', 'items' => array_map(static fn ($r) => ['title' => $r['title'], 'sub' => $r['alt_th'], 'url' => site_url('backend/popups/' . $r['id'] . '/edit')], $rows)];
        }

        if ($this->auth->isAdmin()) {
            $rows = (new UserModel())->groupStart()->like('username', $q)->orLike('first_name', $q)->orLike('last_name', $q)->orLike('email', $q)->groupEnd()->findAll(5);
            $me   = $this->user();
            $rows = array_filter($rows, static fn ($r) => UserModel::canManage($me, $r));
            $groups[] = ['label' => 'ผู้ใช้งาน', 'items' => array_values(array_map(static fn ($r) => ['title' => UserModel::fullName($r), 'sub' => $r['username'], 'url' => site_url('backend/users/' . $r['id'] . '/edit')], $rows))];
        }

        return $this->json(['groups' => array_values(array_filter($groups, static fn ($g) => $g['items'] !== []))]);
    }
}
