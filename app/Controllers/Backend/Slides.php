<?php

namespace App\Controllers\Backend;

use App\Libraries\Storage;
use App\Models\DisplayWindowModel;
use App\Models\NewsModel;
use App\Models\SlideModel;

/**
 * Slide Show: รูปไทย (บังคับ)/อังกฤษ/มือถือ, alt, ลิงก์ (ไม่มี / ข่าวในเว็บ / URL ภายนอก), เปิด/ปิด, ลากเรียง, ช่วงวันที่ (ไม่บังคับ)
 */
class Slides extends HighlightController
{
    protected string $path      = 'backend/slides';
    protected string $label     = 'Slide Show';
    protected string $directory = 'slides';

    protected function model(): DisplayWindowModel
    {
        return new SlideModel();
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

    private function form(?array $slide)
    {
        return view('backend/slides/form', [
            'title'       => $slide ? 'แก้ไข Slide Show' : 'เพิ่ม Slide Show',
            'breadcrumbs' => ['Slide Show' => site_url($this->path), $slide ? 'แก้ไข' : 'เพิ่ม' => null],
            'slide'       => $slide,
            'newsOptions' => $this->newsOptions($slide['news_id'] ?? null),
        ]);
    }

    /** ข่าวที่เผยแพร่แล้ว 100 ข่าวล่าสุด + ข่าวเดิมของสไลด์ (แม้จะเลิกเผยแพร่แล้ว) */
    private function newsOptions(?int $current): array
    {
        $rows = (new NewsModel())->select('id, title_th')->where('status', NewsModel::STATUS_PUBLISHED)
            ->where('published_at <=', date('Y-m-d H:i:s'))->orderBy('published_at', 'desc')->orderBy('id', 'desc')->findAll(100);
        $options = array_column($rows, 'title_th', 'id');

        if ($current && ! isset($options[$current]) && ($extra = (new NewsModel())->find($current))) {
            $options[$current] = $extra['title_th'] . ' (ยังไม่เผยแพร่)';
        }

        return $options;
    }

    private function save(?array $slide)
    {
        $linkType = (string) $this->request->getPost('link_type');
        $data     = [
            'title'        => $this->input('title'),
            'alt_th'       => $this->input('alt_th'),
            'alt_en'       => $this->input('alt_en'),
            'link_type'    => $linkType,
            'news_id'      => $linkType === SlideModel::LINK_NEWS ? ((int) $this->request->getPost('news_id') ?: null) : null,
            'link_url'     => $linkType === SlideModel::LINK_URL ? $this->input('link_url') : null,
            'open_new_tab' => $linkType === SlideModel::LINK_NONE ? 1 : $this->bool('open_new_tab'),
            'start_date'   => $this->date('start_date'),
            'end_date'     => $this->date('end_date'),
            'is_active'    => $this->bool('is_active'),
        ];

        $errors = $this->check($data, [
            'title'     => ['label' => 'ชื่อเรียก', 'rules' => 'required|max_length[200]'],
            'alt_th'    => ['label' => 'คำอธิบายรูป (ภาษาไทย)', 'rules' => 'required|max_length[255]'],
            'alt_en'    => ['label' => 'คำอธิบายรูป (ภาษาอังกฤษ)', 'rules' => 'permit_empty|max_length[255]'],
            'link_type' => ['label' => 'ประเภทลิงก์', 'rules' => 'required|in_list[none,news,url]'],
        ]);

        if ($linkType === SlideModel::LINK_NEWS) {
            if ($data['news_id'] === null) {
                $errors['news_id'] = 'กรุณาเลือกข่าวที่ต้องการลิงก์ไป';
            } elseif (! isset($this->newsOptions($slide['news_id'] ?? null)[$data['news_id']])) {
                $errors['news_id'] = 'เลือกได้เฉพาะข่าวที่เผยแพร่แล้ว กรุณาเลือกใหม่';
            }
        }

        if ($linkType === SlideModel::LINK_URL) {
            if ($data['link_url'] === null) {
                $errors['link_url'] = 'กรุณากรอกลิงก์ เช่น https://www.example.com';
            } elseif (mb_strlen($data['link_url']) > 500) {
                $errors['link_url'] = 'ลิงก์ยาวได้ไม่เกิน 500 ตัวอักษร';
            } elseif (! self::isHttpUrl($data['link_url'])) {
                $errors['link_url'] = 'ลิงก์ไม่ถูกต้อง ต้องขึ้นต้นด้วย http:// หรือ https:// เช่น https://www.example.com';
            }
        }

        $errors += $this->dateErrors($data['start_date'], $data['end_date'], false);

        if ($errors !== []) {
            return $this->backWithErrors($errors);
        }

        [$images, $imageErrors, $obsolete] = $this->handleImages(
            ['image_th' => true, 'image_en' => false, 'image_mobile' => false],
            $slide,
        );

        if ($imageErrors !== []) {
            return $this->backWithErrors($imageErrors);
        }

        $model = new SlideModel();

        if ($slide === null) {
            $id = (int) $model->insert($data + $images);
        } else {
            $id = (int) $slide['id'];
            $model->update($id, $data + $images);
        }

        Storage::delete(...$obsolete);

        return $this->afterSave($slide ? 'update' : 'create', (string) $data['title'], $id);
    }
}
