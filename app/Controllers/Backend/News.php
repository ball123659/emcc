<?php

namespace App\Controllers\Backend;

use App\Libraries\HtmlSanitizer;
use App\Libraries\Storage;
use App\Libraries\Uploads\UploadCheck;
use App\Libraries\Uploads\UploadRejected;
use App\Models\NewsAttachmentModel;
use App\Models\NewsCategoryModel;
use App\Models\NewsImageModel;
use App\Models\NewsModel;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * ข่าวประชาสัมพันธ์: หัวข้อ, คำโปรย, เนื้อหา (rich text ไทย/อังกฤษ), หมวดหมู่, รูปปก, แกลเลอรี (ลากเรียง + คำบรรยาย),
 * ไฟล์แนบ, สถานะ ร่าง/เผยแพร่/ซ่อน, ตั้งเวลาเผยแพร่, ปักหมุด, slug อัตโนมัติ, ยอดเข้าชม
 */
class News extends BackendController
{
    private const PATH = 'backend/news';

    public function index()
    {
        $tab = (string) $this->request->getGet('tab');
        $tab = in_array($tab, ['draft', 'published', 'scheduled', 'hidden', 'trashed'], true) ? $tab : 'all';
        $q   = $this->searchTerm();

        $category = (int) $this->request->getGet('category') ?: null;
        $pinned   = (string) $this->request->getGet('pinned');

        $tabs = ['all' => ['label' => 'ทั้งหมด', 'count' => (new NewsModel())->countAllResults(), 'color' => 'primary']];

        foreach (NewsModel::DISPLAY_LABELS as $key => $label) {
            $tabs[$key] = ['label' => $label, 'count' => (new NewsModel())->whereDisplayStatus($key)->countAllResults(), 'color' => match (NewsModel::DISPLAY_COLORS[$key]) {
                'success' => 'success', 'info' => 'info', 'warning' => 'danger', default => 'gray',
            }];
        }

        $tabs['trashed'] = ['label' => 'ถังขยะ', 'count' => (new NewsModel())->onlyDeleted()->countAllResults(), 'color' => 'gray', 'icon' => 'trash'];

        $model = (new NewsModel())->select('news.*, c.name_th AS category_name')->join('news_categories c', 'c.id = news.category_id', 'left');
        $tab === 'trashed' ? $model->onlyDeleted() : $model->whereDisplayStatus($tab === 'all' ? null : $tab);

        if ($q !== '') {
            $model->groupStart()->like('news.title_th', $q)->orLike('news.title_en', $q)->groupEnd();
        }

        if ($category !== null) {
            $model->where('news.category_id', $category);
        }

        if ($pinned === '1' || $pinned === '0') {
            $model->where('news.is_pinned', (int) $pinned);
        }

        [$sort, $dir] = $this->sorting(['published_at', 'view_count', 'id'], 'id');
        $model->orderBy('news.' . $sort, $dir)->orderBy('news.id', 'desc');

        return view('backend/news/index', [
            'title'       => 'ข่าวประชาสัมพันธ์',
            'breadcrumbs' => ['ข่าวประชาสัมพันธ์' => site_url(self::PATH), 'รายการ' => null],
            'tab'         => $tab,
            'tabs'        => $tabs,
            'q'           => $q,
            'category'    => $category,
            'pinned'      => $pinned,
            'categories'  => array_column((new NewsCategoryModel())->orderBy('sort_order')->findAll(), 'name_th', 'id'),
            'pager'       => $this->paginate($model),
            'query'       => $this->query(['tab', 'q', 'per_page', 'category', 'pinned', 'sort', 'dir']),
            'sort'        => $sort,
            'dir'         => $dir,
        ]);
    }

    /** @return array{0: string, 1: string} */
    protected function sorting(array $allowed, string $default): array
    {
        $sort = (string) $this->request->getGet('sort');
        $dir  = strtolower((string) $this->request->getGet('dir')) === 'asc' ? 'asc' : 'desc';

        return in_array($sort, $allowed, true) ? [$sort, $dir] : [$default, 'desc'];
    }

    public function create()
    {
        return $this->form(null);
    }

    public function edit(int $id)
    {
        return $this->form($this->findOr404(new NewsModel(), $id));
    }

    private function form(?array $news)
    {
        return view('backend/news/form', [
            'title'       => $news ? 'แก้ไขข่าว' : 'เพิ่มข่าว',
            'breadcrumbs' => ['ข่าวประชาสัมพันธ์' => site_url(self::PATH), $news ? 'แก้ไข' : 'เพิ่ม' => null],
            'news'        => $news,
            'categories'  => $this->categoryOptions($news['category_id'] ?? null),
            'images'      => $news ? (new NewsImageModel())->where('news_id', $news['id'])->orderBy('sort_order')->orderBy('id')->findAll() : [],
            'attachments' => $news ? (new NewsAttachmentModel())->where('news_id', $news['id'])->orderBy('sort_order')->orderBy('id')->findAll() : [],
        ]);
    }

    /** หมวดที่ปิดแล้วไม่ให้เลือกใหม่ แต่รายการเดิมที่อยู่ในหมวดนั้นต้องบันทึกต่อได้ */
    private function categoryOptions(?int $current): array
    {
        $rows = (new NewsCategoryModel())->groupStart()->where('is_active', 1)->when($current, static fn ($q) => $q->orWhere('id', $current))->groupEnd()
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
        return $this->save($this->findOr404(new NewsModel(), $id));
    }

    private function save(?array $news)
    {
        $rawTh = (string) $this->request->getPost('content_th');
        $rawEn = (string) $this->request->getPost('content_en');

        $data = [
            'title_th'     => $this->input('title_th'),
            'title_en'     => $this->input('title_en'),
            'excerpt_th'   => $this->input('excerpt_th'),
            'excerpt_en'   => $this->input('excerpt_en'),
            'content_th'   => $rawTh,
            'content_en'   => $rawEn,
            'cover_alt_th' => $this->input('cover_alt_th'),
            'cover_alt_en' => $this->input('cover_alt_en'),
            'status'       => (string) $this->request->getPost('status'),
            'published_at' => $this->date('published_at'),
            'category_id'  => (int) $this->request->getPost('category_id') ?: null,
            'is_pinned'    => $this->bool('is_pinned'),
            'slug'         => $this->input('slug'),
        ];

        $errors = $this->check($data, [
            'title_th'     => ['label' => 'หัวข้อข่าว', 'rules' => 'required|max_length[255]'],
            'title_en'     => ['label' => 'หัวข้อข่าว (อังกฤษ)', 'rules' => 'permit_empty|max_length[255]'],
            'excerpt_th'   => ['label' => 'คำโปรย', 'rules' => 'permit_empty|max_length[500]'],
            'excerpt_en'   => ['label' => 'คำโปรย (อังกฤษ)', 'rules' => 'permit_empty|max_length[500]'],
            'cover_alt_th' => ['label' => 'คำอธิบายรูปปก (ไทย)', 'rules' => 'permit_empty|max_length[255]'],
            'cover_alt_en' => ['label' => 'คำอธิบายรูปปก (อังกฤษ)', 'rules' => 'permit_empty|max_length[255]'],
            'status'       => ['label' => 'สถานะ', 'rules' => 'required|in_list[draft,published,hidden]'],
        ], ['title_th' => ['required' => 'กรุณากรอกหัวข้อข่าวภาษาไทย']]);

        if (HtmlSanitizer::isBlank(HtmlSanitizer::clean($rawTh))) {
            $errors['content_th'] = 'กรุณากรอกเนื้อหาข่าวภาษาไทย';
        }

        if (mb_strlen($rawTh) > 2_000_000 || mb_strlen($rawEn) > 2_000_000) {
            $errors['content_th'] = 'เนื้อหายาวเกินไป กรุณาแบ่งเป็นหลายข่าว';
        }

        if ($data['category_id'] === null || ! isset($this->categoryOptions($news['category_id'] ?? null)[$data['category_id']])) {
            $errors['category_id'] = 'กรุณาเลือกหมวดหมู่';
        }

        $slugLocked = $news !== null && NewsModel::isSlugLocked($news);

        if ($slugLocked) {
            unset($data['slug']);
        } elseif ($data['slug'] !== null) {
            if (mb_strlen($data['slug']) > 200) {
                $errors['slug'] = 'ชื่อใน URL ยาวได้ไม่เกิน 200 ตัวอักษร';
            } elseif (! preg_match('/^[a-zA-Z0-9\-]+$/', $data['slug'])) {
                $errors['slug'] = 'ใช้ได้เฉพาะ a-z, 0-9 และขีด (-) ห้ามมีช่องว่างหรือภาษาไทย';
            } elseif ((new NewsModel())->withDeleted()->where('slug', strtolower($data['slug']))->when($news, static fn ($q) => $q->where('id !=', $news['id']))->countAllResults() > 0) {
                $errors['slug'] = 'ชื่อใน URL นี้ถูกใช้แล้ว (รวมข่าวที่ถูกลบ) กรุณาใช้ชื่ออื่น';
            }
        } else {
            $data['slug'] = '';
        }

        // คำบรรยายรูป/ชื่อไฟล์แนบ (ยาวเกิน)
        foreach (['images' => 'caption', 'gallery_new_meta' => 'caption', 'attachments' => 'title', 'attachment_new_meta' => 'title'] as $group => $prefix) {
            foreach ((array) $this->request->getPost($group) as $key => $row) {
                foreach (['_th', '_en'] as $suffix) {
                    if (mb_strlen(trim((string) ($row[$prefix . $suffix] ?? ''))) > 255) {
                        $errors["{$group}.{$key}.{$prefix}{$suffix}"] = 'ยาวได้ไม่เกิน 255 ตัวอักษร';
                    }
                }
            }
        }

        foreach ((array) $this->request->getPost('attachments') as $id => $row) {
            if (($row['delete'] ?? '0') !== '1' && trim((string) ($row['title_th'] ?? '')) === '') {
                $errors["attachments.{$id}.title_th"] = 'กรุณาตั้งชื่อไฟล์ที่จะแสดงบนหน้าเว็บ';
            }
        }

        if ($errors !== []) {
            return $this->backWithErrors($errors);
        }

        // ---------- ไฟล์ (หลังตรวจข้อมูลอื่นผ่านแล้ว) ----------
        $created  = [];
        $obsolete = [];
        $uploadErrors = [];

        $cover = $this->request->getFile('cover_image');

        if (UploadCheck::chosen($cover)) {
            try {
                $stored              = service('imageProcessor')->storeUpload($cover, 'news', true);
                $data['cover_image'] = $stored->path;
                array_push($created, $stored->path, $stored->thumbPath);

                if ($news) {
                    array_push($obsolete, $news['cover_image'], $news['cover_thumb']);
                }
            } catch (UploadRejected $e) {
                $uploadErrors['cover_image'] = $e->getMessage();
            }
        } elseif ($news === null) {
            $uploadErrors['cover_image'] = 'กรุณาเลือกรูปปก';
        }

        $newImages = [];

        foreach ($this->files('gallery_new') as $key => $file) {
            if (! UploadCheck::chosen($file)) {
                continue;
            }

            try {
                $stored = service('imageProcessor')->storeUpload($file, 'news', true);
                array_push($created, $stored->path, $stored->thumbPath);
                $meta        = (array) ($this->request->getPost('gallery_new_meta')[$key] ?? []);
                $newImages[] = [
                    'image_path' => $stored->path, 'thumb_path' => $stored->thumbPath, 'width' => $stored->width, 'height' => $stored->height,
                    'file_size'  => $stored->size, 'caption_th' => self::clean($meta['caption_th'] ?? null), 'caption_en' => self::clean($meta['caption_en'] ?? null),
                    'sort_order' => (int) ($meta['sort'] ?? 999),
                ];
            } catch (UploadRejected $e) {
                $uploadErrors['gallery'] = 'รูป "' . $file->getClientName() . '": ' . $e->getMessage();
            }
        }

        $newAttachments = [];

        foreach ($this->files('attachment_new') as $key => $file) {
            if (! UploadCheck::chosen($file)) {
                continue;
            }

            try {
                $stored = service('documentStorer')->storeUpload($file, 'news-attachments');
                $created[] = $stored->path;
                $meta      = (array) ($this->request->getPost('attachment_new_meta')[$key] ?? []);
                $title     = self::clean($meta['title_th'] ?? null) ?? pathinfo($stored->originalName, PATHINFO_FILENAME);
                $newAttachments[] = [
                    'title_th' => mb_substr($title, 0, 255), 'title_en' => self::clean($meta['title_en'] ?? null), 'file_path' => $stored->path,
                    'original_name' => $stored->originalName, 'mime_type' => $stored->mimeType, 'file_size' => $stored->size,
                    'scan_status' => $stored->scanStatus->value, 'sort_order' => (int) ($meta['sort'] ?? 999),
                ];
            } catch (UploadRejected $e) {
                $uploadErrors['attachments'] = 'ไฟล์ "' . $file->getClientName() . '": ' . $e->getMessage();
            }
        }

        if ($uploadErrors !== []) {
            Storage::delete(...$created);

            return $this->backWithErrors($uploadErrors, 'ยังบันทึกไม่ได้ กรุณาแก้ไขช่องที่มีข้อความสีแดง (ไฟล์ที่เลือกไว้ต้องเลือกใหม่)');
        }

        // ---------- บันทึก ----------
        $db    = db_connect();
        $model = new NewsModel();
        $db->transStart();

        if ($news === null) {
            $id = (int) $model->insert($data);
        } else {
            $id = (int) $news['id'];
            $model->update($id, $data);
        }

        $obsolete = array_merge($obsolete, $this->saveGallery($id, $newImages), $this->saveAttachments($id, $newAttachments));
        $db->transComplete();

        if (! $db->transStatus()) {
            Storage::delete(...$created);
            $this->toast('บันทึกไม่สำเร็จ กรุณาลองใหม่อีกครั้ง', 'danger');

            return redirect()->back()->withInput();
        }

        Storage::delete(...$obsolete);

        if (HtmlSanitizer::containsImage($rawTh) || HtmlSanitizer::containsImage($rawEn)) {
            session()->setFlashdata('toast', ['type' => 'warning', 'title' => 'รูปในเนื้อหาข่าวถูกนำออก', 'text' => 'บันทึกข่าวแล้ว แต่รูปที่วางในเนื้อหาไม่ถูกเก็บ กรุณาเพิ่มรูปในส่วน "แกลเลอรีรูป" แทน']);
        } else {
            $this->toast('บันทึกข่าว "' . mb_strimwidth((string) $data['title_th'], 0, 60, '…') . '" แล้ว');
        }

        if ($news === null && $this->request->getPost('_another') === '1') {
            return redirect()->to(site_url(self::PATH . '/create'));
        }

        return redirect()->to(site_url(self::PATH . '/' . $id . '/edit'));
    }

    /** @return array<string, UploadedFile> */
    private function files(string $name): array
    {
        $files = $this->request->getFiles()[$name] ?? [];

        return is_array($files) ? array_filter($files, static fn ($f) => $f instanceof UploadedFile) : [];
    }

    private static function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }

    /** @return list<string> ไฟล์ที่ต้องลบหลังบันทึก */
    private function saveGallery(int $newsId, array $newImages): array
    {
        $model    = new NewsImageModel();
        $existing = array_column($model->where('news_id', $newsId)->findAll(), null, 'id');
        $obsolete = [];

        foreach ((array) $this->request->getPost('images') as $imageId => $row) {
            $imageId = (int) $imageId;

            if (! isset($existing[$imageId])) {
                continue; // ไม่ใช่รูปของข่าวนี้
            }

            if (($row['delete'] ?? '0') === '1') {
                $model->delete($imageId);
                array_push($obsolete, $existing[$imageId]['image_path'], $existing[$imageId]['thumb_path']);

                continue;
            }

            $model->update($imageId, [
                'caption_th' => self::clean($row['caption_th'] ?? null),
                'caption_en' => self::clean($row['caption_en'] ?? null),
                'sort_order' => (int) ($row['sort'] ?? $existing[$imageId]['sort_order']),
            ]);
        }

        foreach ($newImages as $image) {
            $model->insert($image + ['news_id' => $newsId]);
        }

        $this->renumber('news_images', $newsId);

        return $obsolete;
    }

    /** @return list<string> */
    private function saveAttachments(int $newsId, array $newAttachments): array
    {
        $model    = new NewsAttachmentModel();
        $existing = array_column($model->where('news_id', $newsId)->findAll(), null, 'id');
        $obsolete = [];

        foreach ((array) $this->request->getPost('attachments') as $attachmentId => $row) {
            $attachmentId = (int) $attachmentId;

            if (! isset($existing[$attachmentId])) {
                continue;
            }

            if (($row['delete'] ?? '0') === '1') {
                $model->delete($attachmentId);
                $obsolete[] = $existing[$attachmentId]['file_path'];

                continue;
            }

            $model->update($attachmentId, [
                'title_th'   => self::clean($row['title_th'] ?? null) ?? $existing[$attachmentId]['title_th'],
                'title_en'   => self::clean($row['title_en'] ?? null),
                'sort_order' => (int) ($row['sort'] ?? $existing[$attachmentId]['sort_order']),
            ]);
        }

        foreach ($newAttachments as $attachment) {
            $model->insert($attachment + ['news_id' => $newsId]);
        }

        $this->renumber('news_attachments', $newsId);

        return $obsolete;
    }

    /** เรียงเลขลำดับใหม่ 1..N (ไม่ลง log) */
    private function renumber(string $table, int $newsId): void
    {
        $db   = db_connect();
        $rows = $db->table($table)->select('id')->where('news_id', $newsId)->orderBy('sort_order')->orderBy('id')->get()->getResultArray();

        foreach ($rows as $i => $row) {
            $db->table($table)->where('id', $row['id'])->update(['sort_order' => $i + 1]);
        }
    }

    public function delete(int $id)
    {
        $news = $this->findOr404(new NewsModel(), $id);
        (new NewsModel())->delete($id);
        $this->toast('ลบข่าวแล้ว', 'success', 'ข่าวหายจากหน้าเว็บ กู้คืนได้ที่แท็บถังขยะ');

        return redirect()->to(site_url(self::PATH));
    }

    public function restore(int $id)
    {
        $this->toast((new NewsModel())->restore($id) ? 'กู้คืนข่าวแล้ว' : 'ไม่พบข่าวที่ต้องการกู้คืน', 'success');

        return redirect()->to(site_url(self::PATH . '?tab=trashed'));
    }

    /** เผยแพร่ทันที (ร่าง / ซ่อน / ตั้งเวลา) */
    public function publish(int $id)
    {
        $news = $this->findOr404(new NewsModel(), $id);
        $date = $news['published_at'] === null || strtotime($news['published_at']) > time() ? date('Y-m-d') : $news['published_at'];
        (new NewsModel())->update($id, ['status' => NewsModel::STATUS_PUBLISHED, 'published_at' => $date]);
        $this->toast('เผยแพร่ข่าวแล้ว');

        return redirect()->back();
    }

    /** ซ่อนจากหน้าเว็บ */
    public function hide(int $id)
    {
        $this->findOr404(new NewsModel(), $id);
        (new NewsModel())->update($id, ['status' => NewsModel::STATUS_HIDDEN]);
        $this->toast('ซ่อนข่าวแล้ว');

        return redirect()->back();
    }
}
