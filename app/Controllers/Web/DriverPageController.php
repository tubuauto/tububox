<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\DeliveryRepository;
use App\Repositories\DriverRepository;
use App\Services\DeliveryService;
use App\Services\DriverFulfillmentService;
use Throwable;

final class DriverPageController extends BaseWebController
{
    public function __construct(
        \App\Core\View $view,
        private readonly DriverRepository $drivers,
        private readonly DeliveryRepository $deliveries,
        private readonly DeliveryService $deliveryService,
        private readonly DriverFulfillmentService $driverService
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        $driver = $this->drivers->findByUserId((int) ($auth['id'] ?? 0));
        $isAdmin = (bool) ($auth['is_admin'] ?? false);

        if ($driver !== null) {
            $items = $this->deliveries->listByDriver((int) $driver['id']);
        } elseif ($isAdmin) {
            // Admin can inspect and drive the fulfillment flow for assigned deliveries.
            $items = $this->deliveries->list(['status' => 'assigned'], null, true);
        } else {
            $items = [];
        }

        return $this->render('driver.index', [
            'auth' => $auth,
            'driver' => $driver,
            'is_admin' => $isAdmin,
            'items' => $items,
        ]);
    }

    public function show(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        try {
            $delivery = $this->deliveryService->getOrFail($auth, (int) $request->attribute('id'));
            return $this->render('driver.show', [
                'auth' => $auth,
                'delivery' => $delivery,
                'error' => null,
            ]);
        } catch (Throwable $e) {
            return Response::html('<h1>400</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>', 400);
        }
    }

    public function action(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        $deliveryId = (int) $request->attribute('id');
        $action = (string) $request->attribute('action');

        try {
            switch ($action) {
                case 'accept':
                    $this->driverService->accept($auth, $deliveryId);
                    break;
                case 'arrive-pickup':
                    $this->driverService->arrivePickup($auth, $deliveryId);
                    break;
                case 'pickup':
                    $this->driverService->pickup($auth, $deliveryId, (string) $request->input('note', ''));
                    break;
                case 'arrive-dropoff':
                    $this->driverService->arriveDropoff($auth, $deliveryId);
                    break;
                case 'sign':
                    $this->driverService->sign($auth, $deliveryId, $request->body());
                    break;
                case 'complete':
                    $this->driverService->complete($auth, $deliveryId);
                    break;
                case 'cod-collect':
                    $this->driverService->collectCod($auth, $deliveryId, $request->body());
                    break;
                default:
                    throw new \RuntimeException('Unknown driver action.');
            }

            return $this->redirect('/driver/deliveries/' . $deliveryId);
        } catch (Throwable $e) {
            return Response::html('<h1>400</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>', 400);
        }
    }
}
