<?php

namespace App\Controllers\Backend;

use App\Libraries\Reorder;
use App\Libraries\Storage;
use App\Libraries\Uploads\UploadCheck;
use App\Libraries\Uploads\UploadRejected;
use App\Models\DownloadCategoryModel;
use App\Models\DownloadModel;

/**
 * ไฟล์ดาวน์โหลด: อัปโหลดไฟล์ หรือ ลิงก์ภายนอก, หมวดหมู่, ชื่อ/คำอธิบาย 2 ภาษา, เปิด/ปิด, เรียงลำดับ, ยอดดาวน์โหลด
 * อัปไฟล์ใหม่ทับได้ ลิงก์เดิม (/downloads/{id}) ไม่เสีย
 */
class Downloads extends BackendController
{
    private const PATH = 'backend/downloads';

    public const SCAN_LABELS = [
        'clean'    => ['ผ่าน', 'success'],
        'pending'  => ['รอสแกน', 'warning'],
        'infected' => ['พบไวรัส', 'danger'],
        'failed'   => ['สแกนไม่สำเร็จ', 'danger'],
    ];

    public function index()
    {
        $q        = $this->searchTerm();
        $trashed  = $this->request->getGet('tab') === 'trashed';
        $category = (int) $this->request->getGet('category') ?: null;
        $type     = (string) $this->request->getGet('type');

        $model = (new DownloadModel())->select('downloads.*, c.name_th AS category_name')->join('download_categories c', 'c.id = downloads.category_id', 'left');

        if ($trashed) {
            $model->onlyDeleted();
        }

        if ($q !== '') {
            $model->groupStart()->like('downloads.title_th', $q)->orLike('downloads.title_en', $q)->orLike('downloads.original_name', $q)->groupEnd();
        }

        if ($category !== null) {
            $model->where('downloads.category_id', $category);
        }

        if (in_array($type, ['file', 'link'], true)) {
            $model->where('downloads.type', $type);
        }

        $sort = $this->request->getGet('sort') === 'download_count' ? 'download_count' : 'sort_order';
        $dir  = $sort === 'download_count' && $this->request->getGet('dir') !== 'asc' ? 'desc' : 'asc';
        $model->orderBy('downloads.' . $sort, $dir)->orderBy('downloads.id');

        return view('backend/downloads/index', [
            'title'       => 'ไฟล์ดาวน์โหลด',
            'subheading'  => 'กดปุ่ม ⇅ ที่มุมตาราง แล้วลากเพื่อจัดลำดับการแสดงบนหน้าเว็บ',
            'breadcrumbs' => ['ไฟล์ดาวน์โหลด' => site_url(self::PATH), 'รายการ' => null],
            'tab'         => $trashed ? 'trashed' : 'all',
            'tabs'        => [
                'all'     => ['label' => 'ทั้งหมด', 'count' => (new DownloadModel())->countAllResults(), 'color' => 'primary'],
                'trashed' => ['label' => 'ถังขยะ', 'count' => (new DownloadModel())->onlyDeleted()->countAllResults(), 'color' => 'gray', 'icon' => 'trash'],
            ],
            'q'           => $q,
            'category'    => $category,
            'type'        => $type,
            'categories'  => array_column((new DownloadCategoryModel())->orderBy('sort_order')->findAll(), 'name_th', 'id'),
            'pager'       => $this->paginate($model),
            'query'       => $this->query(['tab', 'q', 'per_page', 'category', 'type', 'sort', 'dir']),
            'sort'        => $sort,
            'dir'         => $dir,
            'reorder'     => ! $trashed && $q === '' && $category === null && $type === '' && $sort === 'sort_order',
        ]);
    }

    public function create()
    {
        return $this->form(null);
    }

    public function edit(int $id)
    {
        return $this->form($this->findOr404(new DownloadModel(), $id));
    }

    private function form(?array $download)
    {
        return view('backend/downloads/form', [
            'title'       => $download ? 'แก้ไขไฟล์ดาวน์โหลด' : 'เพิ่มไฟล์ดาวน์โหลด',
            'breadcrumbs' => ['ไฟล์ดาวน์โหลด' => site_url(self::PATH), $download ? 'แก้ไข' : 'เพิ่ม' => null],
            'download'    => $download,
            'categories'  => $this->categoryOptions($download['category_id'] ?? null),
        ]);
    }

    private function categoryOptions(?int $current): array
    {
        $rows    = (new DownloadCategoryModel())->groupStart()->where('is_active', 1)->when($current, static fn ($q) => $q->orWhere('id', $current))->groupEnd()
            ->orderBy('sort_order')->orderBy('id')->findAll();
        $options = [];

        foreach ($rows as $row) {
            $options[$row['id']] = $row['name_th'] . ($row['is_active'] ? '' : ' (ปิดใช้งาน)');
        }

        return $options;
    }

    public function store()
    {
        return $this->save(null);
    }

    public function update(int $id)
    {
        return $this->save($this->findOr404(new DownloadModel(), $id));
    }

    private function save(?array $download)
    {
        $type = (string) $this->request->getPost('type');
        $data = [
            'title_th'       => $this->input('title_th'),
            'title_en'       => $this->input('title_en'),
            'description_th' => $this->input('description_th'),
            'description_en' => $this->input('description_en'),
            'type'           => $type,
            'external_url'   => $type === DownloadModel::TYPE_LINK ? $this->input('external_url') : null,
            'category_id'    => (int) $this->request->getPost('category_id') ?: null,
            'is_active'      => $this->bool('is_active'),
        ];

        $errors = $this->check($data, [
            'title_th'       => ['label' => 'ชื่อ (ภาษาไทย)', 'rules' => 'required|max_length[255]'],
            'title_en'       => ['label' => 'ชื่อ (ภาษาอังกฤษ)', 'rules' => 'permit_empty|max_length[255]'],
            'description_th' => ['label' => 'คำอธิบาย (ภาษาไทย)', 'rules' => 'permit_empty|max_length[2000]'],
            'description_en' => ['label' => 'คำอธิบาย (ภาษาอังกฤษ)', 'rules' => 'permit_empty|max_length[2000]'],
            'type'           => ['label' => 'ประเภท', 'rules' => 'required|in_list[file,link]'],
        ], ['title_th' => ['required' => 'กรุณากรอกชื่อที่จะแสดงบนหน้าเว็บ']]);

        if ($data['category_id'] === null || ! isset($this->categoryOptions($download['category_id'] ?? null)[$data['category_id']])) {
            $errors['category_id'] = 'กรุณาเลือกหมวดหมู่';
        }

        if ($type === DownloadModel::TYPE_LINK) {
            if ($data['external_url'] === null) {
                $errors['external_url'] = 'กรุณากรอกลิงก์';
            } elseif (mb_strlen($data['external_url']) > 500) {
                $errors['external_url'] = 'ลิงก์ยาวได้ไม่เกิน 500 ตัวอักษร';
            } elseif (! self::isHttpUrl($data['external_url'])) {
                $errors['external_url'] = 'ลิงก์ต้องขึ้นต้นด้วย http:// หรือ https:// เช่น https://drive.google.com/...';
            }
        }

        if ($errors !== []) {
            return $this->backWithErrors($errors);
        }

        $obsolete = [];
        $file     = $this->request->getFile('file');

        if ($type === DownloadModel::TYPE_FILE) {
            if (UploadCheck::chosen($file)) {
                try {
                    $stored = service('documentStorer')->storeUpload($file, 'downloads');
                } catch (UploadRejected $e) {
                    return $this->backWithErrors(['file' => $e->getMessage()]);
                }

                $data += [
                    'file_path' => $stored->path, 'original_name' => $stored->originalName, 'file_extension' => $stored->extension,
                    'mime_type' => $stored->mimeType, 'file_size' => $stored->size, 'file_hash' => $stored->hash,
                    'scan_status' => $stored->scanStatus->value, 'scanned_at' => date('Y-m-d H:i:s'),
                ];

                if (! empty($download['file_path'])) {
                    $obsolete[] = $download['file_path'];
                }
            } elseif (empty($download['file_path'])) {
                return $this->backWithErrors(['file' => 'กรุณาเลือกไฟล์ที่จะให้ดาวน์โหลด']);
            }
        } elseif (! empty($download['file_path'])) {
            $obsolete[] = $download['file_path']; // เปลี่ยนเป็นลิงก์ -> ลบไฟล์เก่า
        }

        $model = new DownloadModel();

        if ($download === null) {
            $id = (int) $model->insert($data);
        } else {
            $id = (int) $download['id'];
            $model->update($id, $data);
        }

        Storage::delete(...$obsolete);
        $this->toast('บันทึก "' . $data['title_th'] . '" แล้ว');

        if ($download === null && $this->request->getPost('_another') === '1') {
            return redirect()->to(site_url(self::PATH . '/create'));
        }

        return redirect()->to(site_url(self::PATH . '/' . $id . '/edit'));
    }

    public function toggle(int $id)
    {
        $this->findOr404(new DownloadModel(), $id);
        $value = (string) $this->request->getPost('value') === '1' ? 1 : 0;
        (new DownloadModel())->update($id, ['is_active' => $value]);

        return $this->json(['message' => $value ? 'เปิดแสดงแล้ว' : 'ปิดแสดงแล้ว']);
    }

    public function reorder()
    {
        Reorder::apply(db_connect(), 'downloads', (array) $this->request->getPost('ids'), 'ไฟล์ดาวน์โหลด');

        return $this->json(['message' => 'บันทึกลำดับแล้ว']);
    }

    public function delete(int $id)
    {
        $row = $this->findOr404(new DownloadModel(), $id);
        (new DownloadModel())->delete($id);
        $this->toast('ลบ "' . $row['title_th'] . '" แล้ว', 'success', 'รายการหายจากหน้าเว็บ กู้คืนได้ที่แท็บถังขยะ');

        return redirect()->to(site_url(self::PATH));
    }

    public function restore(int $id)
    {
        $this->toast((new DownloadModel())->restore($id) ? 'กู้คืนแล้ว' : 'ไม่พบรายการที่ต้องการกู้คืน');

        return redirect()->to(site_url(self::PATH . '?tab=trashed'));
    }
}
