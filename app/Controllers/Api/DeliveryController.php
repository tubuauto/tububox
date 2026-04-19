<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\DeliveryLogRepository;
use App\Services\DeliveryService;
use Throwable;

final class DeliveryController extends BaseApiController
{
    public function __construct(
        private readonly DeliveryService $deliveryService,
        private readonly DeliveryLogRepository $deliveryLogs
    ) {
    }

    public function create(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth) || !isset($auth['tenant_id'])) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $tenantId = (int) $auth['tenant_id'];
            $payload = $request->body();
            if (trim((string) ($payload['source_type'] ?? '')) === '') {
                $payload['source_type'] = 'merchant_api';
            }
            if (trim((string) ($payload['source_platform'] ?? '')) === '') {
                $payload['source_platform'] = 'merchant_api';
            }

            $result = $this->deliveryService->create($tenantId, $auth, $payload);
            $delivery = $result['delivery'];

            return $this->success(
                $result['idempotent'] ? 'Delivery already exists' : 'Delivery created',
                [
                    'id' => (int) $delivery['id'],
                    'status' => (string) $delivery['status'],
                    'source_order_no' => $delivery['source_order_no'],
                ],
                request: $request
            );
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    public function createMarketplace(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $tenantId = (int) $request->input('tenant_id', 0);
            if ($tenantId <= 0) {
                return $this->error(
                    message: 'tenant_id is required',
                    errors: ['tenant_id' => 'tenant_id is required'],
                    status: 422,
                    errorCode: 'VALIDATION_FAILED',
                    request: $request
                );
            }

            $payload = $request->body();
            $payload['source_type'] = 'marketplace';
            if (trim((string) ($payload['source_platform'] ?? '')) === '') {
                $payload['source_platform'] = 'tububox_marketplace';
            }

            $result = $this->deliveryService->create($tenantId, $auth, $payload);
            $delivery = $result['delivery'];

            return $this->success(
                $result['idempotent'] ? 'Marketplace order already exists' : 'Marketplace order created',
                [
                    'id' => (int) $delivery['id'],
                    'tenant_id' => (int) $delivery['tenant_id'],
                    'status' => (string) $delivery['status'],
                    'source_order_no' => $delivery['source_order_no'],
                ],
                request: $request
            );
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    public function indexMarketplace(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $items = $this->deliveryService->listMarketplaceForUser($auth, $request->query());

            return $this->success('OK', [
                'items' => $items,
                'pagination' => [
                    'page' => 1,
                    'per_page' => 200,
                    'total' => count($items),
                ],
            ], request: $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    public function showMarketplace(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $deliveryId = (int) $request->attribute('id');
            $delivery = $this->deliveryService->getMarketplaceForUserOrFail($auth, $deliveryId);
            $logs = $this->deliveryLogs->listByDelivery($deliveryId);

            return $this->success('OK', [
                'delivery' => $delivery,
                'logs' => $logs,
            ], request: $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    public function index(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $items = $this->deliveryService->list($auth, $request->query());
            return $this->success('OK', [
                'items' => $items,
                'pagination' => [
                    'page' => 1,
                    'per_page' => 200,
                    'total' => count($items),
                ],
            ], request: $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    public function show(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $deliveryId = (int) $request->attribute('id');
            $delivery = $this->deliveryService->getOrFail($auth, $deliveryId);
            $logs = $this->deliveryLogs->listByDelivery($deliveryId);

            return $this->success('OK', [
                'delivery' => $delivery,
                'logs' => $logs,
            ], request: $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    public function cancel(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $deliveryId = (int) $request->attribute('id');
            $updated = $this->deliveryService->transition(
                auth: $auth,
                deliveryId: $deliveryId,
                toStatus: 'cancelled',
                note: (string) ($request->input('reason', 'Cancelled by request'))
            );

            return $this->success('Delivery cancelled', ['delivery' => $updated], request: $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    public function tracking(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $deliveryId = (int) $request->attribute('id');
            $this->deliveryService->getOrFail($auth, $deliveryId);
            $items = $this->deliveryService->tracking($deliveryId);

            return $this->success('OK', ['items' => $items], request: $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }
}
