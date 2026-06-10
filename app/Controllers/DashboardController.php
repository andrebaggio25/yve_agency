<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requirePermission('dashboard.view');

        // Em fases futuras, estas métricas virão de services dedicados
        $stats = [
            'active_clients'       => 0,
            'pending_plans'        => 0,
            'pending_approvals'    => 0,
            'pending_invoices'     => 0,
            'campaigns_with_alert' => 0,
        ];

        return $this->view('dashboard.index', [
            'stats' => $stats,
            'user'  => Auth::user(),
        ]);
    }
}
