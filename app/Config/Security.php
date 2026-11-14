<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Security extends BaseConfig
{

    public string $csrfProtection = 'session';

    public bool $tokenRandomize = false;

    public string $tokenName = '_token';

    public string $headerName = 'X-CSRF-TOKEN';

    public string $cookieName = 'emcc_csrf';

    public int $expires = 7200;

    public bool $regenerate = false;

    public bool $redirect = true;
}
