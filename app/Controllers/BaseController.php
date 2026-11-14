<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {

        parent::initController($request, $response, $logger);

    }

    protected function hasSessionCookie(): bool
    {
        return $this->request->getCookie(config('Session')->cookieName) !== null;
    }

    protected function canPreview(string $page): bool
    {
        if (! $this->hasSessionCookie()) {
            return false;
        }

        $auth = service('auth');
        $user = $auth->user();

        return $user !== null && $auth->invalidReason() === null && ! (bool) $user['must_change_password'] && $auth->canViewPage($page);
    }
}
