<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\DeliveryLogRepository;
use App\Services\DeliveryService;
use Throwable;

final class DeliveryPageController extends BaseWebController
{
    public function __construct(
        \App\Core\View $view,
        private readonly DeliveryService $deliveryService,
        private readonly DeliveryLogRepository $deliveryLogs
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        $items = $this->deliveryService->list($auth, $request->query());
        return $this->render('deliveries.index', [
            'auth' => $auth,
            'items' => $items,
            'query' => $request->query(),
        ]);
    }

    public function createForm(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        return $this->render('deliveries.create', [
            'auth' => $auth,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function store(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth) || !isset($auth['tenant_id'])) {
            return $this->redirect('/login');
        }

        $input = $request->body();
        $payload = [
            'source_type' => 'manual',
            'source_platform' => 'merchant_console',
            'source_order_no' => $input['source_order_no'] ?? null,
            'external_ref' => $input['external_ref'] ?? null,
            'idempotency_key' => $input['idempotency_key'] ?? null,
            'pickup' => [
                'name' => $input['pickup_name'] ?? '',
                'phone' => $input['pickup_phone'] ?? null,
                'address' => $input['pickup_address'] ?? '',
                'lat' => isset($input['pickup_lat']) ? (float) $input['pickup_lat'] : null,
                'lng' => isset($input['pickup_lng']) ? (float) $input['pickup_lng'] : null,
            ],
            'dropoff' => [
                'name' => $input['dropoff_name'] ?? '',
                'phone' => $input['dropoff_phone'] ?? null,
                'address' => $input['dropoff_address'] ?? '',
                'lat' => isset($input['dropoff_lat']) ? (float) $input['dropoff_lat'] : null,
                'lng' => isset($input['dropoff_lng']) ? (float) $input['dropoff_lng'] : null,
            ],
            'goods' => [
                'type' => $input['goods_type'] ?? null,
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

        try {
            $result = $this->deliveryService->create((int) $auth['tenant_id'], $auth, $payload);
            $deliveryId = (int) $result['delivery']['id'];
            return $this->redirect('/deliveries/' . $deliveryId);
        } catch (Throwable $e) {
            $decoded = json_decode($e->getMessage(), true);
            return $this->render('deliveries.create', [
                'auth' => $auth,
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
            $delivery = $this->deliveryService->getOrFail($auth, $id);
            $latestLogs = $this->deliveryLogs->listLatestByDelivery($id, 10);
            $logs = $this->deliveryLogs->listByDelivery($id);

            return $this->render('deliveries.show', [
                'auth' => $auth,
                'delivery' => $delivery,
                'latest_logs' => $latestLogs,
                'logs' => $logs,
            ]);
        } catch (Throwable $e) {
            return Response::html('<h1>400</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>', 400);
        }
    }
}
