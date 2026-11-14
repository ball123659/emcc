<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Emcc extends BaseConfig
{

    public string $brandName = 'EMCC Backend';

    public string $defaultPassword = 'passwordemcc';

    public int $passwordMinLength = 6;

    public int $loginMaxAttempts = 5;

    public int $loginLockoutMinutes = 15;

    public int $rememberDays = 30;

    public bool $forceHttps = true;

    public int $activityLogRetentionDays = 730;

    public string $dateFormat = 'd/m/Y';

    public string $datetimeFormat = 'd/m/Y H:i';

    public string $virusScanner = 'none';

    public int $virusScanTimeout = 120;

    public string $clamavBinary = 'clamdscan';

    public string $defenderBinary = 'C:\\Program Files\\Windows Defender\\MpCmdRun.exe';

    public string $storagePath = WRITEPATH . 'storage/';

    public int $fileMaxKb = 20480;

    public int $imageMaxKb = 10240;

    public int $imageMaxDimension = 1920;

    public int $imageMaxSourcePixels = 50_000_000;

    public int $webpQuality = 85;

    public int $thumbDimension = 480;

    public int $phpUploadMaxMb = 20;

    public int $phpPostMaxMb = 30;
}
