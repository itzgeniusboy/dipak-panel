<?php

namespace App\Controllers;

/**
 * Landing controller.
 *
 * Home::index used to echo view('New'), and app/Views/New.php does not exist in
 * this project, so every request to "/" and "/New" was a fatal error. "/" now
 * resolves through Routes.php setDefaultRoute('dashboard'), and this action
 * redirects rather than rendering a view that is not there.
 */
class Home extends BaseController
{
    public function index()
    {
        return redirect()->to('dashboard');
    }
}
