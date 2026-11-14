<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class PagePermission implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        foreach ((array) $arguments as $code) {
            if (! service('auth')->canViewPage($code)) {
                return Forbidden::response($request);
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
