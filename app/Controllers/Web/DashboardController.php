<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\DashboardRepository;

final class DashboardController extends BaseWebController
{
    public function __construct(
        \App\Core\View $view,
        private readonly DashboardRepository $dashboard
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        $stats = $this->dashboard->deliveryStats(
            tenantId: $auth['tenant_id'] !== null ? (int) $auth['tenant_id'] : null,
            isAdmin: (bool) ($auth['is_admin'] ?? false)
        );

        return $this->render('dashboard.index', [
            'auth' => $auth,
            'stats' => $stats,
        ]);
    }
}

