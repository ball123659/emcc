<?php

namespace App\Controllers\Backend;

use App\Libraries\Storage;
use App\Models\DisplayWindowModel;
use App\Models\PopupModel;

/**
 * Popup: รูปไทย/อังกฤษ, alt, ลิงก์ไม่บังคับ, วันเริ่ม–สิ้นสุด (ระดับวัน, บังคับ), เปิด/ปิด, ลากเรียง
 * หน้าเว็บแสดงต่อกันทีละอันตามลำดับ
 */
class Popups extends HighlightController
{
    protected string $path      = 'backend/popups';
    protected string $label     = 'Popup';
    protected string $directory = 'popups';

    protected function model(): DisplayWindowModel
    {
        return new PopupModel();
    }

    public function create()
    {
        return $this->form(null);
    }

    public function edit(int $id)
    {
        return $this->form($this->findOr404($this->model(), $id));
    }

    public function store()
    {
        return $this->save(null);
    }

    public function update(int $id)
    {
        return $this->save($this->findOr404($this->model(), $id));
    }

    /** ต่ออายุ +7 วัน (นับจากวันสิ้นสุดเดิม หรือวันนี้ถ้าหมดอายุไปแล้ว) */
    public function extend(int $id)
    {
        $popup = $this->findOr404($this->model(), $id);
        $from  = max($popup['end_date'], date('Y-m-d'));
        $end   = date('Y-m-d', strtotime($from . ' +7 days'));
        $this->model()->update($id, ['end_date' => $end]);
        $this->toast('ต่ออายุแล้ว', 'success', 'แสดงถึงวันที่ ' . th_date($end));

        return redirect()->back();
    }

    private function form(?array $popup)
    {
        return view('backend/popups/form', [
            'title'       => $popup ? 'แก้ไข Popup' : 'เพิ่ม Popup',
            'breadcrumbs' => ['Popup' => site_url($this->path), $popup ? 'แก้ไข' : 'เพิ่ม' => null],
            'popup'       => $popup,
        ]);
    }

    private function save(?array $popup)
    {
        $data = [
            'title'        => $this->input('title'),
            'alt_th'       => $this->input('alt_th'),
            'alt_en'       => $this->input('alt_en'),
            'link_url'     => $this->input('link_url'),
            'open_new_tab' => $this->bool('open_new_tab'),
            'start_date'   => $this->date('start_date'),
            'end_date'     => $this->date('end_date'),
            'is_active'    => $this->bool('is_active'),
        ];

        $errors = $this->check($data, [
            'title'  => ['label' => 'ชื่อเรียก', 'rules' => 'required|max_length[200]'],
            'alt_th' => ['label' => 'คำอธิบายรูป (ภาษาไทย)', 'rules' => 'required|max_length[255]'],
            'alt_en' => ['label' => 'คำอธิบายรูป (ภาษาอังกฤษ)', 'rules' => 'permit_empty|max_length[255]'],
        ]);

        if ($data['link_url'] !== null) {
            if (mb_strlen($data['link_url']) > 500) {
                $errors['link_url'] = 'ลิงก์ยาวได้ไม่เกิน 500 ตัวอักษร';
            } elseif (! self::isHttpUrl($data['link_url'])) {
                $errors['link_url'] = 'ลิงก์ไม่ถูกต้อง ต้องขึ้นต้นด้วย http:// หรือ https:// เช่น https://www.example.com';
            }
        }

        $errors += $this->dateErrors($data['start_date'], $data['end_date'], true);

        if ($errors !== []) {
            return $this->backWithErrors($errors);
        }

        [$images, $imageErrors, $obsolete] = $this->handleImages(['image_th' => true, 'image_en' => false], $popup);

        if ($imageErrors !== []) {
            return $this->backWithErrors($imageErrors);
        }

        $model = new PopupModel();

        if ($popup === null) {
            $id = (int) $model->insert($data + $images);
        } else {
            $id = (int) $popup['id'];
            $model->update($id, $data + $images);
        }

        Storage::delete(...$obsolete);

        return $this->afterSave($popup ? 'update' : 'create', (string) $data['title'], $id);
    }
}
