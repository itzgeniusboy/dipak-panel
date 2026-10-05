<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class AdminFilter implements FilterInterface
{
    /**
     * Deny by default.
     *
     * Anything this filter cannot positively verify is treated as "not an
     * admin": the previous version returned NULL for a session with no
     * userid/time_login and for a user row that could not be found, which
     * silently let the request through.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $session   = session();
        $userid    = $session->userid ?? null;
        $timeLogin = $session->time_login ?? null;

        // 1) No session markers at all -> not logged in.
        if ($userid === null || $userid === '' || $timeLogin === null || $timeLogin === '') {
            return redirect()->to('/login')->with('msgWarning', "Please login first");
        }

        $userModel = new UserModel();
        $user      = $userModel->getUser($userid);

        // 2) Session points at a user that no longer exists -> not logged in.
        if (!$user) {
            return redirect()->to('/login')->with('msgWarning', "Please login first");
        }

        // 3) Logged in, but the role is not admin level 1 or 2.
        if (!in_array((int) $user->level, [1, 2], true)) {
            return redirect()->to('dashboard')->with('msgWarning', "Access Denied!");
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do something here
    }
}
