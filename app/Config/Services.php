<?php

namespace Config;

use App\Libraries\Auth;
use App\Libraries\Uploads\DocumentStorer;
use App\Libraries\Uploads\ImageProcessor;
use App\Libraries\VirusScan\ClamAvScanner;
use App\Libraries\VirusScan\DefenderScanner;
use App\Libraries\VirusScan\NullScanner;
use App\Libraries\VirusScan\VirusScanner;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    /** ตัวสแกนไวรัสตาม emcc.virusScanner: none | clamav | defender */
    public static function virusScanner(bool $getShared = true): VirusScanner
    {
        if ($getShared) {
            return static::getSharedInstance('virusScanner');
        }

        return match (strtolower(config(Emcc::class)->virusScanner)) {
            'clamav'   => new ClamAvScanner(),
            'defender' => new DefenderScanner(),
            default    => new NullScanner(),
        };
    }

    public static function imageProcessor(bool $getShared = true): ImageProcessor
    {
        if ($getShared) {
            return static::getSharedInstance('imageProcessor');
        }

        return new ImageProcessor(static::virusScanner());
    }

    public static function documentStorer(bool $getShared = true): DocumentStorer
    {
        if ($getShared) {
            return static::getSharedInstance('documentStorer');
        }

        return new DocumentStorer(static::virusScanner());
    }

    /** ผู้ใช้ที่ล็อกอินอยู่ */
    public static function auth(bool $getShared = true): Auth
    {
        if ($getShared) {
            return static::getSharedInstance('auth');
        }

        return new Auth();
    }
}
