<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requirePlatformAdmin();
        return $this->view('admin.index');
    }
}
