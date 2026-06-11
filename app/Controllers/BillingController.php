<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use App\Services\BillingService;
use App\Repositories\SubscriptionRepository;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingService         $billing,
        private readonly SubscriptionRepository $subRepo,
    ) {}

    public function index(Request $request): Response
    {
        $agencyId     = (int) Auth::agencyId();
        $subscription = $this->billing->getSubscription($agencyId);
        $usage        = $this->billing->usageSummary($agencyId);
        $plans        = $this->billing->allPlans();
        $events       = $this->subRepo->eventsForAgency($agencyId, 10);

        return $this->view('billing.index', compact('subscription', 'usage', 'plans', 'events'));
    }
}
