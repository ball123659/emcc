<?php

namespace App\Controllers\Backend;

use App\Libraries\Reorder;
use App\Libraries\Storage;
use App\Libraries\Uploads\UploadCheck;
use App\Libraries\Uploads\UploadRejected;
use App\Models\DisplayWindowModel;

abstract class HighlightController extends BackendController
{
    protected string $path;
    protected string $label;
    protected string $directory;

    abstract protected function model(): DisplayWindowModel;

    public function index()
    {
        $tab   = (string) $this->request->getGet('tab');
        $tab   = in_array($tab, ['showing', 'scheduled', 'expired', 'disabled', 'trashed'], true) ? $tab : 'all';
        $q     = $this->searchTerm();
        $model = $this->model();

        $counts = ['all' => $this->model()->countAllResults()];

        foreach (array_keys(DisplayWindowModel::STATUS_LABELS) as $status) {
            $counts[$status] = $this->model()->whereStatus($status)->countAllResults();
        }

        $counts['trashed'] = $this->model()->onlyDeleted()->countAllResults();

        if ($tab === 'trashed') {
            $model->onlyDeleted();
        } else {
            $model->whereStatus($tab === 'all' ? null : $tab);
        }

        if ($q !== '') {
            $model->groupStart()->like('title', $q)->orLike('alt_th', $q)->groupEnd();
        }

        $pager = $this->paginate($model->orderBy('sort_order')->orderBy('id'), $this->perPage());

        return view('backend/highlight/index', [
            'title'       => $this->label,
            'subheading'  => 'กดปุ่ม ⇅ ที่มุมตาราง แล้วลากเพื่อจัดลำดับการแสดงบนหน้าเว็บ',
            'breadcrumbs' => [$this->label => site_url($this->path), 'รายการ' => null],
            'kind'        => static::class,
            'path'        => $this->path,
            'label'       => $this->label,
            'tab'         => $tab,
            'q'           => $q,
            'pager'       => $pager,
            'query'       => $this->query(['tab', 'q', 'per_page']),
            'tabs'        => [
                'all'       => ['label' => 'ทั้งหมด', 'count' => $counts['all'], 'color' => 'primary'],
                'showing'   => ['label' => 'กำลังแสดง', 'count' => $counts['showing'], 'color' => 'success'],
                'scheduled' => ['label' => 'รอแสดง', 'count' => $counts['scheduled'], 'color' => 'info'],
                'expired'   => ['label' => 'หมดอายุ', 'count' => $counts['expired'], 'color' => 'gray'],
                'disabled'  => ['label' => 'ปิด', 'count' => $counts['disabled'], 'color' => 'danger'],
                'trashed'   => ['label' => 'ถังขยะ', 'count' => $counts['trashed'], 'color' => 'gray', 'icon' => 'trash'],
            ],
            'isSlides' => $this->directory === 'slides',
        ]);
    }

    public function toggle(int $id)
    {
        $row   = $this->findOr404($this->model(), $id);
        $value = (string) $this->request->getPost('value') === '1' ? 1 : 0;
        $this->model()->update($id, ['is_active' => $value]);
        $row['is_active'] = $value;
        $status           = DisplayWindowModel::displayStatus($row);

        return $this->json([
            'message' => $value ? 'เปิดแสดงแล้ว' : 'ปิดแสดงแล้ว',
            'badge'   => badge(DisplayWindowModel::STATUS_LABELS[$status], DisplayWindowModel::STATUS_COLORS[$status]),
        ]);
    }

    public function reorder()
    {
        $ids = (array) $this->request->getPost('ids');
        Reorder::apply(db_connect(), $this->model()->table, $ids, ' ' . $this->label . ' ');

        return $this->json(['message' => 'บันทึกลำดับแล้ว']);
    }

    public function delete(int $id)
    {
        $row = $this->findOr404($this->model(), $id);
        $this->model()->delete($id);
        $this->toast('ลบ "' . $row['title'] . '" แล้ว', 'success', 'กู้คืนได้ที่แท็บถังขยะ');

        return redirect()->to(site_url($this->path));
    }

    public function restore(int $id)
    {
        if (! $this->model()->restore($id)) {
            $this->toast('ไม่พบรายการที่ต้องการกู้คืน', 'danger');
        } else {
            $this->toast('กู้คืนแล้ว');
        }

        return redirect()->to(site_url($this->path . '?tab=trashed'));
    }

    protected function handleImages(array $fields, ?array $existing, array $maxDimensions = []): array
    {
        $values   = [];
        $errors   = [];
        $created  = [];
        $obsolete = [];

        foreach ($fields as $field => $required) {
            $file    = $this->request->getFile($field);
            $current = $existing[$field] ?? null;

            if (UploadCheck::chosen($file)) {
                try {
                    $stored           = service('imageProcessor')->storeUpload($file, $this->directory, false, $maxDimensions[$field] ?? null);
                    $values[$field]   = $stored->path;
                    $created[]        = $stored->path;

                    if ($current) {
                        $obsolete[] = $current;
                    }
                } catch (UploadRejected $e) {
                    $errors[$field] = $e->getMessage();
                }
            } elseif (! $required && $current && $this->request->getPost('remove_' . $field) === '1') {
                $values[$field] = null;
                $obsolete[]     = $current;
            } elseif ($required && ! $current) {
                $errors[$field] = 'กรุณาเลือกรูป';
            }
        }

        if ($errors !== []) {
            Storage::delete(...$created);

            return [[], $errors, []];
        }

        return [$values, [], $obsolete];
    }

    protected function dateErrors(?string $start, ?string $end, bool $required): array
    {
        $errors = [];

        if ($required && $start === null) {
            $errors['start_date'] = 'กรุณาเลือกวันเริ่มแสดงจากปฏิทิน';
        }

        if ($required && $end === null) {
            $errors['end_date'] = 'กรุณาเลือกวันสิ้นสุดจากปฏิทิน';
        }

        if ($start !== null && $end !== null && $end < $start) {
            $errors['end_date'] = 'วันสิ้นสุดต้องไม่ก่อนวันเริ่ม กรุณาเลือกใหม่';
        }

        return $errors;
    }

    protected function afterSave(string $action, string $title, int $id)
    {
        if ($action === 'create' && $this->request->getPost('_another') === '1') {
            $this->toast('บันทึก "' . $title . '" แล้ว', 'success', 'เพิ่มรายการถัดไปได้เลย');

            return redirect()->to(site_url($this->path . '/create'));
        }

        $this->toast('บันทึก "' . $title . '" แล้ว');

        return redirect()->to(site_url($this->path . '/' . $id . '/edit'));
    }
}
