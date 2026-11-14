<?php

namespace App\Controllers;

use App\Libraries\Storage;
use CodeIgniter\Exceptions\PageNotFoundException;

class Media extends BaseController
{
    public function show(string $folder, string $file)
    {
        $relative = $folder . '/' . $file;

        if (! preg_match('~^(slides|popups|news|settings)/[0-9a-f\-]{36}(_thumb)?\.webp$~', $relative) || ! Storage::exists($relative)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response
            ->setHeader('Content-Type', 'image/webp')
            ->setHeader('Cache-Control', 'public, max-age=31536000, immutable')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents(Storage::path($relative)));
    }
}
