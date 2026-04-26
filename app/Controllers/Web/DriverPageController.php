<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\DeliveryLogRepository;
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
        private readonly DeliveryLogRepository $deliveryLogs,
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
        $grabPool = [];

        if ($driver !== null) {
            $items = $this->deliveries->listByDriver((int) $driver['id']);
            $grabPool = $this->driverService->listGrabPool($auth);
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
            'grab_pool' => $grabPool,
        ]);
    }

    public function show(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        try {
            $deliveryId = (int) $request->attribute('id');
            $delivery = $this->deliveryService->getOrFail($auth, $deliveryId);
            $logs = $this->deliveryLogs->listByDelivery($deliveryId);

            return $this->render('driver.show', [
                'auth' => $auth,
                'delivery' => $delivery,
                'logs' => $logs,
                'flash' => $this->pullFlash(),
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
            $message = 'Action completed.';
            switch ($action) {
                case 'claim':
                    $this->driverService->claim($auth, $deliveryId);
                    $message = 'Order claimed successfully.';
                    break;
                case 'accept':
                    $this->driverService->accept($auth, $deliveryId);
                    $message = 'Delivery accepted.';
                    break;
                case 'arrive-pickup':
                    $this->driverService->arrivePickup($auth, $deliveryId);
                    $message = 'Arrival at pickup confirmed.';
                    break;
                case 'pickup':
                    $this->driverService->pickup(
                        $auth,
                        $deliveryId,
                        (string) $request->input('note', ''),
                        (string) $request->input('pickup_code', '')
                    );
                    $message = 'Pickup confirmed.';
                    break;
                case 'arrive-dropoff':
                    $this->driverService->arriveDropoff($auth, $deliveryId);
                    $message = 'Arrival at dropoff confirmed.';
                    break;
                case 'return-dispatch':
                    $this->driverService->returnToDispatch($auth, $deliveryId, (string) $request->input('reason', ''));
                    $message = 'Order returned to dispatch center.';
                    break;
                case 'sign':
                    $this->driverService->sign($auth, $deliveryId, $request->body());
                    $message = 'Delivery signed.';
                    break;
                case 'complete':
                    $this->driverService->complete($auth, $deliveryId);
                    $message = 'Delivery completed.';
                    break;
                case 'cod-collect':
                    $this->driverService->collectCod($auth, $deliveryId, $request->body());
                    $message = 'COD collection submitted.';
                    break;
                default:
                    throw new \RuntimeException('Unknown rider action.');
            }

            $this->pushFlash('success', $message);
            return $this->redirect('/rider/deliveries/' . $deliveryId);
        } catch (Throwable $e) {
            $this->pushFlash('error', $e->getMessage());
            return $this->redirect('/rider/deliveries/' . $deliveryId);
        }
    }

    private function pushFlash(string $type, string $message): void
    {
        $_SESSION['driver_flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array<string, string>|null
     */
    private function pullFlash(): ?array
    {
        $flash = $_SESSION['driver_flash'] ?? null;
        if (!is_array($flash)) {
            return null;
        }

        unset($_SESSION['driver_flash']);
        return [
            'type' => (string) ($flash['type'] ?? ''),
            'message' => (string) ($flash['message'] ?? ''),
        ];
    }
}
