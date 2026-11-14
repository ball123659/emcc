<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminOnly implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return service('auth')->isAdmin() ? null : Forbidden::response($request);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
