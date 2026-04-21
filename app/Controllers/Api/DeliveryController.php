<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\DeliveryLogRepository;
use App\Repositories\TenantRepository;
use App\Services\DeliveryService;
use Throwable;

final class DeliveryController extends BaseApiController
{
    public function __construct(
        private readonly DeliveryService $deliveryService,
        private readonly DeliveryLogRepository $deliveryLogs,
        private readonly TenantRepository $tenants
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

            $requestedTenantId = (int) $request->input('tenant_id', 0);
            $authTenantId = (int) ($auth['tenant_id'] ?? 0);
            $role = (string) ($auth['role'] ?? '');

            $tenantId = $requestedTenantId;
            if ($tenantId <= 0) {
                if ($role === 'user') {
                    $tenantId = (int) ($this->tenants->resolveDefaultFulfillmentTenantId() ?? 0);
                } else {
                    $tenantId = $authTenantId;
                }
            }

            if ($tenantId <= 0) {
                return $this->error(
                    message: 'tenant_id is required for this role',
                    errors: ['tenant_id' => 'tenant_id is required for this role'],
                    status: 422,
                    errorCode: 'VALIDATION_FAILED',
                    request: $request
                );
            }

            $tenant = $this->tenants->findById($tenantId);
            if ($tenant === null || (string) ($tenant['status'] ?? '') !== 'active') {
                return $this->error(
                    message: 'tenant_id is invalid',
                    errors: ['tenant_id' => 'tenant_id is invalid'],
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
                    'quote_fee_cents' => (int) ($delivery['quote_fee_cents'] ?? 0),
                    'quote_currency' => (string) ($delivery['quote_currency'] ?? 'CAD'),
                    'payment_status' => (string) ($delivery['payment_status'] ?? 'unpaid'),
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

    public function cancelMarketplace(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $deliveryId = (int) $request->attribute('id');
            $updated = $this->deliveryService->cancelMarketplaceForUser(
                $auth,
                $deliveryId,
                (string) ($request->input('reason') ?? 'Cancelled by marketplace user')
            );

            return $this->success('Marketplace order cancelled', ['delivery' => $updated], request: $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    public function payMarketplace(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $deliveryId = (int) $request->attribute('id');
            $updated = $this->deliveryService->payMarketplaceForUser(
                $auth,
                $deliveryId,
                (string) ($request->input('payment_method') ?? 'wallet'),
                (string) ($request->input('payment_reference') ?? '')
            );

            return $this->success('Marketplace order paid', ['delivery' => $updated], request: $request);
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
