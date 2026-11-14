<?php

namespace App\Libraries;

use CodeIgniter\HTTP\DownloadResponse;

class FileResponse
{
    public static function make(string $relativePath, string $name, string $mime, string $fallbackBase): DownloadResponse
    {
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        $ascii     = Slug::make(pathinfo($name, PATHINFO_FILENAME));
        $fallback  = ($ascii !== '' ? $ascii : $fallbackBase) . '.' . $extension;
        $path      = Storage::path($relativePath);

        $response = new DownloadResponse($fallback, true);
        $response->setFilePath($path);
        $response->setContentType($mime, '');
        $response->setHeader('Content-Disposition', 'attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('Cache-Control', 'private, no-store');

        return $response;
    }
}
