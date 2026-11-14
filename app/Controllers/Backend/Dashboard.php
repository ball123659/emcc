<?php

namespace App\Controllers\Backend;

use App\Models\DisplayWindowModel;
use App\Models\DownloadModel;
use App\Models\NewsModel;
use App\Models\PopupModel;
use App\Models\SlideModel;
use App\Models\UserModel;

/**
 * แดชบอร์ด — หน้าแรกหลังล็อกอิน ทุกคนเข้าได้ (มีกล่องต้อนรับ)
 * ข้อมูลสรุป (จำนวนข่าว, popup, ไฟล์ยอดนิยม) แสดงเฉพาะผู้มีสิทธิ์หน้า dashboard
 */
class Dashboard extends BackendController
{
    public const ACTIVITY_COLORS = [
        'login' => '#0e975f', 'create' => '#0e975f', 'restore' => '#0e975f',
        'update' => '#2563eb', 'permission_update' => '#2563eb', 'reorder' => '#2563eb',
        'delete' => '#dc2626', 'login_failed' => '#dc2626',
        'password_reset' => '#e0891b', 'password_change' => '#e0891b', 'unlock' => '#e0891b',
    ];

    public const SUBJECTS = [
        'news' => 'ข่าว', 'slides' => 'สไลด์', 'popups' => 'Popup', 'downloads' => 'ไฟล์ดาวน์โหลด',
        'users' => 'ผู้ใช้', 'site_settings' => 'ตั้งค่าเว็บไซต์', 'news_categories' => 'หมวดหมู่ข่าว',
        'download_categories' => 'หมวดหมู่ไฟล์', 'news_images' => 'รูปแกลเลอรี', 'news_attachments' => 'ไฟล์แนบข่าว',
    ];

    public function index()
    {
        $user = $this->user();
        $hour = (int) date('G');
        $can  = fn (string $code) => $this->auth->canViewPage($code);

        $actions = array_values(array_filter([
            $can('news') ? ['label' => 'เพิ่มข่าว', 'url' => site_url('backend/news/create'), 'icon' => 'newspaper', 'primary' => true] : null,
            $can('popups') ? ['label' => 'เพิ่ม Popup', 'url' => site_url('backend/popups/create'), 'icon' => 'window'] : null,
            $can('slides') ? ['label' => 'เพิ่มสไลด์', 'url' => site_url('backend/slides/create'), 'icon' => 'photo'] : null,
            $can('downloads') ? ['label' => 'อัปโหลดไฟล์', 'url' => site_url('backend/downloads/create'), 'icon' => 'arrow-up-tray'] : null,
        ]));

        if ($actions !== [] && ! in_array(true, array_column($actions, 'primary'), true)) {
            $actions[0]['primary'] = true;
        }

        $data = [
            'title'    => 'แดชบอร์ด',
            'greeting' => match (true) {
                $hour < 12 => 'สวัสดีตอนเช้า',
                $hour < 17 => 'สวัสดีตอนบ่าย',
                default    => 'สวัสดีตอนเย็น',
            },
            'name'        => trim((string) $user['first_name']) ?: $user['username'],
            'actions'     => $actions,
            'showSummary' => $can('dashboard'),
            'showLogs'    => $can('activity_logs'),
        ];

        if ($data['showSummary']) {
            $data += $this->summary();
            $data['todos'] = $this->todos();
        }

        if ($data['showLogs']) {
            $data['logs'] = db_connect()->table('activity_logs l')->select('l.*, u.username')
                ->join('users u', 'u.id = l.user_id', 'left')->where('l.action !=', 'logout')
                ->orderBy('l.id', 'desc')->limit(8)->get()->getResultArray();
        }

        return view('backend/dashboard', $data);
    }

    private function summary(): array
    {
        $now        = date('Y-m-d H:i:s');
        $monthStart = date('Y-m-01 00:00:00');
        $news       = static fn () => new NewsModel();

        $popupsShowing  = (new PopupModel())->whereStatus(DisplayWindowModel::STATUS_SHOWING)->countAllResults();
        $popupsExpiring = (new PopupModel())->whereStatus(DisplayWindowModel::STATUS_SHOWING)->where('end_date <=', date('Y-m-d', strtotime('+7 days')))->countAllResults();

        return [
            'stats' => [
                'published'      => $news()->where('status', 'published')->where('published_at <=', $now)->countAllResults(),
                'publishedMonth' => $news()->where('status', 'published')->where('published_at <=', $now)->where('published_at >=', $monthStart)->countAllResults(),
                'scheduled'      => $news()->where('status', 'published')->where('published_at >', $now)->countAllResults(),
                'drafts'         => $news()->where('status', 'draft')->countAllResults(),
                'popupsShowing'  => $popupsShowing,
                'popupsExpiring' => $popupsExpiring,
                'slidesShowing'  => (new SlideModel())->whereStatus(DisplayWindowModel::STATUS_SHOWING)->countAllResults(),
                'downloads'      => (new DownloadModel())->visible()->countAllResults(),
                'downloadTotal'  => (int) ((new DownloadModel())->selectSum('download_count')->first()['download_count'] ?? 0),
            ],
            'activePopups' => (new PopupModel())->whereStatus(DisplayWindowModel::STATUS_SHOWING)->orderBy('end_date')->orderBy('sort_order')->findAll(5),
            'popularDownloads' => (new DownloadModel())->where('download_count >', 0)->orderBy('download_count', 'desc')->findAll(5),
            'popularNews'      => (new NewsModel())->where('view_count >', 0)->orderBy('view_count', 'desc')->findAll(5),
        ];
    }

    /** "งานที่ต้องทำ" — รายการเตือนที่ระบบสร้างให้อัตโนมัติ (แสดงเฉพาะเรื่องที่ผู้ใช้มีสิทธิ์จัดการ) */
    private function todos(): array
    {
        $today = date('Y-m-d');
        $in3   = date('Y-m-d', strtotime('+3 days'));
        $items = [];

        if ($this->auth->canViewPage('popups')) {
            $items[] = [(new PopupModel())->whereStatus(DisplayWindowModel::STATUS_SHOWING)->where('end_date <=', $in3)->countAllResults(),
                'Popup ใกล้หมดอายุ', 'ภายใน 3 วัน — ต่ออายุหรือเตรียมรายการใหม่', '#e0891b', site_url('backend/popups?tab=showing')];
        }

        if ($this->auth->canViewPage('slides')) {
            $items[] = [(new SlideModel())->where('is_active', 1)->where('end_date >=', $today)->where('end_date <=', $in3)->countAllResults(),
                'สไลด์ใกล้หมดอายุ', 'ภายใน 3 วัน', '#e0891b', site_url('backend/slides')];
        }

        if ($this->auth->canViewPage('news')) {
            $items[] = [(new NewsModel())->where('status', 'published')->where('DATE(published_at)', $today)->countAllResults(),
                'ข่าวที่เผยแพร่วันนี้', 'รวมข่าวที่ตั้งเวลาไว้สำหรับวันนี้', '#0e975f', site_url('backend/news?tab=published')];
            $items[] = [(new NewsModel())->where('status', 'draft')->where('updated_at <', date('Y-m-d H:i:s', strtotime('-7 days')))->countAllResults(),
                'ข่าวร่างค้างเกิน 7 วัน', 'ตรวจทานแล้วเผยแพร่ หรือลบทิ้ง', '#704a9f', site_url('backend/news?tab=draft')];
        }

        if ($this->auth->canViewPage('downloads')) {
            $items[] = [(new DownloadModel())->where('type', 'file')->groupStart()->where('scan_status !=', 'clean')->orWhere('scan_status', null)->groupEnd()->countAllResults(),
                'ไฟล์ที่สแกนไม่ผ่าน', 'ไม่แสดงบนหน้าเว็บจนกว่าจะอัปไฟล์ใหม่', '#dc2626', site_url('backend/downloads')];
        }

        if ($this->auth->isAdmin()) {
            $items[] = [(new UserModel())->where('locked_until >', date('Y-m-d H:i:s'))->countAllResults(),
                'บัญชีที่ถูกล็อก', 'ใส่รหัสผิดเกินกำหนด — ปลดล็อกหรือรีเซ็ตรหัสได้', '#dc2626', site_url('backend/users')];
        }

        return array_values(array_map(
            static fn (array $i) => ['count' => $i[0], 'title' => $i[1], 'sub' => $i[2], 'color' => $i[3], 'url' => $i[4]],
            array_filter($items, static fn (array $i) => $i[0] > 0),
        ));
    }
}
