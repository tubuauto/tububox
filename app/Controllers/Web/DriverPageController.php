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
                case 'accept':
                    $this->driverService->accept($auth, $deliveryId);
                    $message = 'Delivery accepted.';
                    break;
                case 'arrive-pickup':
                    $this->driverService->arrivePickup($auth, $deliveryId);
                    $message = 'Arrival at pickup confirmed.';
                    break;
                case 'pickup':
                    $this->driverService->pickup($auth, $deliveryId, (string) $request->input('note', ''));
                    $message = 'Pickup confirmed.';
                    break;
                case 'arrive-dropoff':
                    $this->driverService->arriveDropoff($auth, $deliveryId);
                    $message = 'Arrival at dropoff confirmed.';
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
                    throw new \RuntimeException('Unknown driver action.');
            }

            $this->pushFlash('success', $message);
            return $this->redirect('/driver/deliveries/' . $deliveryId);
        } catch (Throwable $e) {
            $this->pushFlash('error', $e->getMessage());
            return $this->redirect('/driver/deliveries/' . $deliveryId);
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
