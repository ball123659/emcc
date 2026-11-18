<?php

namespace App\Controllers;

use App\Filters\SiteLocale;
use App\Models\DownloadCategoryModel;
use App\Models\DownloadModel;
use App\Models\NewsAttachmentModel;
use App\Models\NewsCategoryModel;
use App\Models\NewsImageModel;
use App\Models\NewsModel;
use App\Models\PopupModel;
use App\Models\SlideModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * หน้าบ้าน (เว็บไซต์สาธารณะ)
 */
class Site extends BaseController
{
    private const NEWS_PER_PAGE = 12;

    public function home(): string
    {
        return view('site/home', [
            'active'    => 'home',
            'slides'    => $this->slides(),
            'popups'    => (new PopupModel())->visible()->findAll(20),
            'news'      => $this->withCategories((new NewsModel())->published()->findAll(8)),
            'downloads' => $this->downloadsWithCategories((new DownloadModel())->visible()->findAll(5)),
        ]);
    }

    public function news(): string
    {
        $categories = (new NewsCategoryModel())->where('is_active', 1)->orderBy('sort_order')->orderBy('id')->findAll();
        $slug       = trim((string) $this->request->getGet('category'));
        $category   = null;

        if ($slug !== '') {
            foreach ($categories as $row) {
                if ($row['slug'] === $slug) {
                    $category = $row;
                }
            }

            if ($category === null) {
                throw PageNotFoundException::forPageNotFound();
            }
        }

        $page  = max(1, (int) $this->request->getGet('page'));
        $model = (new NewsModel())->published();

        if ($category !== null) {
            $model->where('category_id', $category['id']);
        }

        $total = $model->countAllResults(false);
        $pages = max(1, (int) ceil($total / self::NEWS_PER_PAGE));

        if ($page > $pages) {
            throw PageNotFoundException::forPageNotFound();
        }

        $rows     = $this->withCategories($model->findAll(self::NEWS_PER_PAGE, ($page - 1) * self::NEWS_PER_PAGE));
        $featured = $page === 1 && $category === null && $rows !== [] ? array_shift($rows) : null;

        return view('site/news', [
            'title'      => $category !== null ? loc($category, 'name') : t('ข่าวสารและกิจกรรม', 'News & Activities'),
            'active'     => 'news',
            'categories' => $categories,
            'category'   => $category,
            'featured'   => $featured,
            'rows'       => $rows,
            'page'       => $page,
            'pages'      => $pages,
            'total'      => $total,
        ]);
    }

    public function newsShow(string $slug): string
    {
        $model = new NewsModel();
        $news  = $model->published()->where('slug', $slug)->first();
        $preview = false;

        if ($news === null && $this->canPreview('news')) {
            $news    = (new NewsModel())->where('slug', $slug)->first();
            $preview = $news !== null;
        }

        if ($news === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! $preview && ! $this->canPreview('news')) {
            $this->countView($news);
        }

        [$news] = $this->withCategories([$news]);

        $latest = $this->withCategories((new NewsModel())->published()->where('id !=', $news['id'])->findAll(4));
        $related = $this->withCategories((new NewsModel())->published()
            ->where('id !=', $news['id'])->where('category_id', $news['category_id'])->findAll(3));

        $cover = media_url($news['cover_image']);

        return view('site/news_show', [
            'title'       => loc($news, 'title'),
            'description' => loc($news, 'excerpt'),
            'ogImage'     => $cover,
            'active'      => 'news',
            'news'        => $news,
            'preview'     => $preview,
            'gallery'     => (new NewsImageModel())->where('news_id', $news['id'])->orderBy('sort_order')->orderBy('id')->findAll(),
            'attachments' => (new NewsAttachmentModel())->where('news_id', $news['id'])->where('scan_status', 'clean')->orderBy('sort_order')->orderBy('id')->findAll(),
            'latest'      => $latest,
            'related'     => $related,
            'downloads'   => $this->downloadsWithCategories((new DownloadModel())->visible()->findAll(3)),
        ]);
    }

    public function downloads(): string
    {
        $items      = $this->downloadsWithCategories((new DownloadModel())->visible()->findAll());
        $used       = array_flip(array_column($items, 'category_id'));
        $categories = array_values(array_filter(
            (new DownloadCategoryModel())->orderBy('sort_order')->orderBy('id')->findAll(),
            static fn (array $c): bool => isset($used[$c['id']]),
        ));

        return view('site/downloads', [
            'title'      => t('ดาวน์โหลด', 'Downloads'),
            'active'     => 'downloads',
            'items'      => $items,
            'categories' => $categories,
        ]);
    }

    public function about(): string
    {
        return view('site/about', ['title' => t('เกี่ยวกับ', 'About'), 'active' => 'about']);
    }

    public function contact(): string
    {
        return view('site/contact', ['title' => t('ติดต่อเรา', 'Contact'), 'active' => 'contact']);
    }

    public function lang(string $lang): RedirectResponse
    {
        $lang = $lang === 'en' ? 'en' : 'th';

        return redirect()->to($this->backTo())
            ->setCookie(SiteLocale::COOKIE, $lang, 365 * DAY, '', '/', '', null, true, 'Lax');
    }

    /**
     * กลับไปหน้าเดิมเฉพาะ URL ในเว็บนี้เท่านั้น (กัน open redirect)
     */
    private function backTo(): string
    {
        $referer = (string) $this->request->getServer('HTTP_REFERER');
        $base    = rtrim(base_url(), '/') . '/';

        if ($referer === '' || ! str_starts_with($referer, $base)) {
            return site_url('/');
        }

        $path = substr($referer, strlen($base));

        if (str_starts_with($path, 'backend') || str_starts_with($path, 'lang/') || preg_match('~^[A-Za-z0-9/_\-.%?=&]*$~', $path) !== 1) {
            return site_url('/');
        }

        return $base . $path;
    }

    /**
     * สไลด์ที่ลิงก์ไปข่าว — แสดงลิงก์เฉพาะข่าวที่เผยแพร่อยู่
     */
    private function slides(): array
    {
        $slides  = (new SlideModel())->visible()->findAll(20);
        $newsIds = array_values(array_filter(array_map(
            static fn (array $s) => $s['link_type'] === SlideModel::LINK_NEWS ? (int) $s['news_id'] : 0,
            $slides,
        )));
        $news = $newsIds === [] ? [] : array_column((new NewsModel())->published()->whereIn('id', $newsIds)->findAll(), null, 'id');

        foreach ($slides as &$slide) {
            $slide['href'] = match ($slide['link_type']) {
                SlideModel::LINK_NEWS => isset($news[$slide['news_id']]) ? news_url($news[$slide['news_id']]) : null,
                SlideModel::LINK_URL  => safe_url($slide['link_url']),
                default               => null,
            };
        }

        return $slides;
    }

    private function withCategories(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $categories = array_column((new NewsCategoryModel())->whereIn('id', array_unique(array_column($rows, 'category_id')))->findAll(), null, 'id');

        foreach ($rows as &$row) {
            $category            = $categories[$row['category_id']] ?? null;
            $row['category']     = $category;
            $row['category_name'] = $category !== null ? loc($category, 'name') : '';
        }

        return $rows;
    }

    private function downloadsWithCategories(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $categories = array_column((new DownloadCategoryModel())->whereIn('id', array_unique(array_column($rows, 'category_id')))->findAll(), null, 'id');

        foreach ($rows as &$row) {
            $category             = $categories[$row['category_id']] ?? null;
            $row['category_slug'] = $category['slug'] ?? '';
            $row['category_name'] = $category !== null ? loc($category, 'name') : '';
            $row['url']           = DownloadModel::publicUrl($row);
        }

        return $rows;
    }

    /**
     * นับยอดเข้าชม 1 ครั้ง / IP / ข่าว / ชั่วโมง ไม่นับบอท
     */
    private function countView(array $news): void
    {
        if (Downloads::isBot((string) $this->request->getUserAgent())) {
            return;
        }

        $key = 'news_view_' . $news['id'] . '_' . sha1($this->request->getIPAddress());

        if (cache($key) !== null) {
            return;
        }

        cache()->save($key, 1, HOUR);
        db_connect()->table('news')->where('id', $news['id'])->set('view_count', 'view_count + 1', false)->update();
    }
}
