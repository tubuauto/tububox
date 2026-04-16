<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\DeliveryRepository;
use App\Repositories\DriverRepository;
use App\Services\DispatchService;
use Throwable;

final class DispatchPageController extends BaseWebController
{
    public function __construct(
        \App\Core\View $view,
        private readonly DeliveryRepository $deliveries,
        private readonly DriverRepository $drivers,
        private readonly DispatchService $dispatchService
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        $tenantId = $auth['tenant_id'] !== null ? (int) $auth['tenant_id'] : null;
        $isAdmin = (bool) ($auth['is_admin'] ?? false);

        $pending = $this->deliveries->list(['status' => 'pending'], $tenantId, $isAdmin);
        $assigned = $this->deliveries->list(['status' => 'assigned'], $tenantId, $isAdmin);
        $failed = $this->deliveries->list(['status' => 'failed'], $tenantId, $isAdmin);
        $drivers = $tenantId !== null ? $this->drivers->listByTenant($tenantId) : [];

        return $this->render('dispatch.index', [
            'auth' => $auth,
            'pending' => $pending,
            'assigned' => $assigned,
            'failed' => $failed,
            'drivers' => $drivers,
            'error' => null,
        ]);
    }

    public function assign(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        try {
            $this->dispatchService->assign($auth, $request->body());
            return $this->redirect('/dispatch');
        } catch (Throwable $e) {
            return Response::html('<h1>400</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>', 400);
        }
    }

    public function reassign(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        try {
            $this->dispatchService->reassign($auth, $request->body());
            return $this->redirect('/dispatch');
        } catch (Throwable $e) {
            return Response::html('<h1>400</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>', 400);
        }
    }

    public function markFailed(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        try {
            $this->dispatchService->markFailed($auth, $request->body());
            return $this->redirect('/dispatch');
        } catch (Throwable $e) {
            return Response::html('<h1>400</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>', 400);
        }
    }
}
