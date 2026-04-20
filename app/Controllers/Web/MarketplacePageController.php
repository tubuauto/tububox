<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\DeliveryLogRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\TenantRepository;
use App\Services\DeliveryService;
use Throwable;

final class MarketplacePageController extends BaseWebController
{
    public function __construct(
        \App\Core\View $view,
        private readonly DeliveryService $deliveries,
        private readonly DeliveryLogRepository $deliveryLogs,
        private readonly OrganizationRepository $organizations,
        private readonly TenantRepository $tenants
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        try {
            $items = $this->deliveries->listMarketplaceForUser($auth, $request->query());

            return $this->render('marketplace.index', [
                'auth' => $auth,
                'items' => $items,
                'query' => $request->query(),
                'flash' => $this->pullFlash(),
            ]);
        } catch (Throwable $e) {
            return Response::html('<h1>400</h1><p>' . h($e->getMessage()) . '</p>', 400);
        }
    }

    public function createForm(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        try {
            $stores = $this->organizations->listMarketplaceStores();
            return $this->render('marketplace.create', [
                'auth' => $auth,
                'stores' => $stores,
                'errors' => [],
                'old' => ['pickup_mode' => 'store'],
            ]);
        } catch (Throwable $e) {
            return Response::html('<h1>400</h1><p>' . h($e->getMessage()) . '</p>', 400);
        }
    }

    public function store(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        $input = $request->body();
        $pickupMode = strtolower(trim((string) ($input['pickup_mode'] ?? 'store')));
        $storeId = (int) ($input['store_id'] ?? 0);
        $tenantId = 0;

        try {
            $store = null;
            $pickup = [];

            if ($pickupMode === 'custom') {
                $tenantId = (int) ($this->tenants->resolveDefaultFulfillmentTenantId() ?? 0);
                if ($tenantId <= 0) {
                    throw new \RuntimeException('No active fulfillment network found. Please contact admin.');
                }

                $pickupName = trim((string) ($input['pickup_name'] ?? ''));
                $pickupAddress = trim((string) ($input['pickup_address'] ?? ''));
                if ($pickupName === '' || $pickupAddress === '') {
                    throw new \RuntimeException('Pickup contact and pickup address are required for custom pickup.');
                }

                $pickup = [
                    'name' => $pickupName,
                    'phone' => $input['pickup_phone'] ?? null,
                    'address' => $pickupAddress,
                    'lat' => isset($input['pickup_lat']) && trim((string) $input['pickup_lat']) !== '' ? (float) $input['pickup_lat'] : null,
                    'lng' => isset($input['pickup_lng']) && trim((string) $input['pickup_lng']) !== '' ? (float) $input['pickup_lng'] : null,
                ];
                $storeId = 0;
            } else {
                $store = $this->organizations->findById($storeId);
                if ($store === null) {
                    throw new \RuntimeException('Store not found.');
                }

                $tenantId = (int) ($store['tenant_id'] ?? 0);
                if ($tenantId <= 0) {
                    throw new \RuntimeException('Store tenant is invalid.');
                }

                $pickup = [
                    'name' => (string) ($store['name'] ?? 'Store'),
                    'phone' => null,
                    'address' => (string) ($store['address'] ?? ''),
                    'lat' => $store['lat'] ?? null,
                    'lng' => $store['lng'] ?? null,
                ];
            }

            $sourceOrderNo = trim((string) ($input['source_order_no'] ?? ''));
            if ($sourceOrderNo === '') {
                $sourceOrderNo = 'mkp-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            }

            $payload = [
                'store_id' => $storeId > 0 ? $storeId : null,
                'source_type' => 'marketplace',
                'source_platform' => 'tububox_marketplace',
                'source_order_no' => $sourceOrderNo,
                'external_ref' => $input['external_ref'] ?? null,
                'idempotency_key' => $input['idempotency_key'] ?? null,
                'pickup' => $pickup,
                'dropoff' => [
                    'name' => $input['dropoff_name'] ?? '',
                    'phone' => $input['dropoff_phone'] ?? null,
                    'address' => $input['dropoff_address'] ?? '',
                    'lat' => isset($input['dropoff_lat']) ? (float) $input['dropoff_lat'] : null,
                    'lng' => isset($input['dropoff_lng']) ? (float) $input['dropoff_lng'] : null,
                ],
                'goods' => [
                    'type' => $input['goods_type'] ?? 'marketplace_goods',
                    'weight' => isset($input['goods_weight']) ? (float) $input['goods_weight'] : null,
                    'note' => $input['goods_note'] ?? null,
                ],
                'pricing' => [
                    'delivery_fee_cents' => (int) ($input['delivery_fee_cents'] ?? 0),
                ],
                'cod' => [
                    'required' => ($input['cod_required'] ?? '') === '1',
                    'amount_cents' => (int) ($input['cod_amount_cents'] ?? 0),
                    'currency' => $input['cod_currency'] ?? 'CAD',
                ],
                'scheduled_at' => $input['scheduled_at'] ?? null,
            ];

            $result = $this->deliveries->create($tenantId, $auth, $payload);
            $deliveryId = (int) ($result['delivery']['id'] ?? 0);

            return $this->redirect('/marketplace/orders/' . $deliveryId);
        } catch (Throwable $e) {
            $stores = $this->organizations->listMarketplaceStores();
            $decoded = json_decode($e->getMessage(), true);
            return $this->render('marketplace.create', [
                'auth' => $auth,
                'stores' => $stores,
                'errors' => is_array($decoded) ? $decoded : ['general' => $e->getMessage()],
                'old' => $input,
            ]);
        }
    }

    public function show(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        try {
            $id = (int) $request->attribute('id');
            $delivery = $this->deliveries->getMarketplaceForUserOrFail($auth, $id);
            $latestLogs = $this->deliveryLogs->listLatestByDelivery($id, 10);
            $allLogs = $this->deliveryLogs->listByDelivery($id);
            $tracking = $this->deliveries->tracking($id);

            return $this->render('marketplace.show', [
                'auth' => $auth,
                'delivery' => $delivery,
                'latest_logs' => $latestLogs,
                'logs' => $allLogs,
                'tracking' => $tracking,
                'flash' => $this->pullFlash(),
            ]);
        } catch (Throwable $e) {
            return Response::html('<h1>400</h1><p>' . h($e->getMessage()) . '</p>', 400);
        }
    }

    public function cancel(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        $id = (int) $request->attribute('id');
        try {
            $this->deliveries->cancelMarketplaceForUser(
                $auth,
                $id,
                (string) ($request->input('reason') ?? 'Cancelled by marketplace user')
            );
            $this->pushFlash('success', 'Order cancelled successfully.');
        } catch (Throwable $e) {
            $this->pushFlash('error', $e->getMessage());
        }

        return $this->redirect('/marketplace/orders/' . $id);
    }

    private function pushFlash(string $type, string $message): void
    {
        $_SESSION['marketplace_flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array<string, string>|null
     */
    private function pullFlash(): ?array
    {
        $flash = $_SESSION['marketplace_flash'] ?? null;
        if (!is_array($flash)) {
            return null;
        }

        unset($_SESSION['marketplace_flash']);
        return [
            'type' => (string) ($flash['type'] ?? ''),
            'message' => (string) ($flash['message'] ?? ''),
        ];
    }
}

