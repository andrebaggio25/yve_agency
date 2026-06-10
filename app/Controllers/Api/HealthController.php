<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class HealthController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->json([
            'status'  => 'ok',
            'app'     => env('APP_NAME', 'YVE Agency'),
            'env'     => env('APP_ENV', 'production'),
            'time'    => date('c'),
        ]);
    }
}
