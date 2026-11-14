<?php

namespace App\Controllers\Backend;

use App\Libraries\Reorder;
use App\Models\CategoryModel;

/**
 * หมวดหมู่ (ข่าว / ไฟล์ดาวน์โหลด): เพิ่ม/แก้ในหน้าต่าง, เปิด/ปิด, ลากเรียง, หมวดที่ยังมีข้อมูลลบไม่ได้
 */
abstract class CategoryController extends BackendController
{
    protected string $path;
    protected string $title;
    protected string $parentLabel;
    protected string $parentPath;
    protected string $countLabel;

    abstract protected function model(): CategoryModel;

    public function index()
    {
        $q     = $this->searchTerm();
        $model = $this->model();

        if ($q !== '') {
            $model->groupStart()->like('name_th', $q)->orLike('name_en', $q)->groupEnd();
        }

        $rows = $model->orderBy('sort_order')->orderBy('id')->findAll();

        foreach ($rows as &$row) {
            $row['item_count'] = $this->model()->itemCount((int) $row['id']);
        }

        unset($row);

        $editId = (int) $this->request->getGet('edit');
        $edit   = $editId ? $this->model()->find($editId) : null;

        return view('backend/categories/index', [
            'title'       => $this->title,
            'breadcrumbs' => [$this->title => site_url($this->path), 'รายการ' => null],
            'subheading'  => 'กดปุ่ม ⇅ ที่มุมตาราง แล้วลากเพื่อจัดลำดับ',
            'path'        => $this->path,
            'rows'        => $rows,
            'q'           => $q,
            'countLabel'  => $this->countLabel,
            'edit'        => $edit,
            'openModal'   => $edit !== null || session('errors') !== null || $this->request->getGet('create') === '1',
        ]);
    }

    public function create()
    {
        return redirect()->to(site_url($this->path . '?create=1'));
    }

    public function edit(int $id)
    {
        return redirect()->to(site_url($this->path . '?edit=' . $id));
    }

    public function store()
    {
        return $this->save(null);
    }

    public function update(int $id)
    {
        return $this->save($this->findOr404($this->model(), $id));
    }

    private function save(?array $category)
    {
        $data = [
            'name_th'   => $this->input('name_th'),
            'name_en'   => $this->input('name_en'),
            'slug'      => $this->input('slug'),
            'is_active' => $this->bool('is_active'),
        ];

        $errors = $this->check($data, [
            'name_th' => ['label' => 'ชื่อหมวด (ภาษาไทย)', 'rules' => 'required|max_length[100]'],
            'name_en' => ['label' => 'ชื่อหมวด (ภาษาอังกฤษ)', 'rules' => 'permit_empty|max_length[100]'],
            'slug'    => ['label' => 'ชื่อใน URL', 'rules' => 'permit_empty|max_length[100]|regex_match[/^[a-zA-Z0-9\-\s]*$/]'],
        ], ['slug' => ['regex_match' => 'ใช้ได้เฉพาะ a-z, 0-9 และขีด (-)']]);

        if ($data['slug'] !== null && ! isset($errors['slug'])) {
            $exists = $this->model()->where('slug', strtolower(str_replace(' ', '-', $data['slug'])))
                ->when($category !== null, static fn ($q) => $q->where('id !=', $category['id']))->countAllResults();

            if ($exists > 0) {
                $errors['slug'] = 'ชื่อใน URL นี้ถูกใช้แล้ว กรุณาใช้ชื่ออื่น';
            }
        }

        if ($errors !== []) {
            return redirect()->to(site_url($this->path . ($category ? '?edit=' . $category['id'] : '?create=1')))->withInput()->with('errors', $errors);
        }

        if ($category === null) {
            $this->model()->insert($data);
        } else {
            $this->model()->update($category['id'], $data);
        }

        $this->toast('บันทึกหมวด "' . $data['name_th'] . '" แล้ว');

        return redirect()->to(site_url($this->path));
    }

    public function toggle(int $id)
    {
        $this->findOr404($this->model(), $id);
        $value = (string) $this->request->getPost('value') === '1' ? 1 : 0;
        $this->model()->update($id, ['is_active' => $value]);

        return $this->json(['message' => $value ? 'เปิดใช้งานหมวดแล้ว' : 'ปิดใช้งานหมวดแล้ว']);
    }

    public function reorder()
    {
        Reorder::apply(db_connect(), $this->model()->table, (array) $this->request->getPost('ids'), 'หมวดหมู่');

        return $this->json(['message' => 'บันทึกลำดับแล้ว']);
    }

    public function delete(int $id)
    {
        $row = $this->findOr404($this->model(), $id);

        // ตรวจซ้ำฝั่งเซิร์ฟเวอร์: หมวดที่ยังมีข้อมูล (รวมที่ถูกลบแบบกู้คืนได้) ลบไม่ได้
        if ($this->model()->itemCount($id) > 0) {
            $this->toast('หมวดนี้ยังมีข้อมูลอยู่ ลบไม่ได้', 'danger', 'ย้ายข้อมูลไปหมวดอื่นก่อน (รวมรายการในถังขยะ) จึงจะลบได้');

            return redirect()->to(site_url($this->path));
        }

        $this->model()->delete($id);
        $this->toast('ลบหมวด "' . $row['name_th'] . '" แล้ว');

        return redirect()->to(site_url($this->path));
    }
}
