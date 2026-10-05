<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class AuthFilter implements FilterInterface
{
    /**
     * Deny by default.
     *
     * The old check was `if (!$session->userid and !$session->time_login)`,
     * so a session carrying a userid but no time_login skipped the redirect
     * and went straight to checkAuthFilter(). Both markers are now required.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $session   = session();
        $userid    = $session->userid ?? null;
        $timeLogin = $session->time_login ?? null;

        if ($userid === null || $userid === '' || $timeLogin === null || $timeLogin === '') {
            return redirect()->to('/login')->with('msgWarning', "Please login first");
        }

        // checkAuthFilter() rejects expired sessions, deleted users, invalid
        // levels and disabled accounts, and logs the session out.
        $userModel = new UserModel();
        return $userModel->checkAuthFilter();
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do something here
    }
}
