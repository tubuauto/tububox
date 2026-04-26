<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\DeliveryService;
use App\Services\DriverFulfillmentService;
use Throwable;

final class DriverController extends BaseApiController
{
    public function __construct(
        private readonly DriverFulfillmentService $driverService,
        private readonly DeliveryService $deliveryService
    ) {
    }

    public function accept(Request $request): Response
    {
        return $this->runAction($request, fn (array $auth, int $id): array => $this->driverService->accept($auth, $id), 'Accepted');
    }

    public function grabPool(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $items = $this->driverService->listGrabPool($auth);
            return $this->success('OK', ['items' => $items], request: $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    public function claim(Request $request): Response
    {
        return $this->runAction($request, fn (array $auth, int $id): array => $this->driverService->claim($auth, $id), 'Claimed');
    }

    public function arrivePickup(Request $request): Response
    {
        return $this->runAction($request, fn (array $auth, int $id): array => $this->driverService->arrivePickup($auth, $id), 'Arrived pickup');
    }

    public function pickup(Request $request): Response
    {
        return $this->runAction(
            $request,
            fn (array $auth, int $id): array => $this->driverService->pickup(
                $auth,
                $id,
                (string) $request->input('note', ''),
                (string) $request->input('pickup_code', '')
            ),
            'Pickup confirmed'
        );
    }

    public function arriveDropoff(Request $request): Response
    {
        return $this->runAction($request, fn (array $auth, int $id): array => $this->driverService->arriveDropoff($auth, $id), 'Arrived dropoff');
    }

    public function returnDispatch(Request $request): Response
    {
        return $this->runAction(
            $request,
            fn (array $auth, int $id): array => $this->driverService->returnToDispatch($auth, $id, (string) $request->input('reason', '')),
            'Returned to dispatch'
        );
    }

    public function sign(Request $request): Response
    {
        return $this->runAction(
            $request,
            fn (array $auth, int $id): array => $this->driverService->sign($auth, $id, $request->body()),
            'Signed'
        );
    }

    public function complete(Request $request): Response
    {
        return $this->runAction($request, fn (array $auth, int $id): array => $this->driverService->complete($auth, $id), 'Completed');
    }

    public function codCollect(Request $request): Response
    {
        return $this->runAction(
            $request,
            fn (array $auth, int $id): array => $this->driverService->collectCod($auth, $id, $request->body()),
            'COD collected'
        );
    }

    public function location(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $deliveryId = (int) ($request->input('delivery_id') ?? 0);
            $lat = (float) ($request->input('lat') ?? 0);
            $lng = (float) ($request->input('lng') ?? 0);

            if ($deliveryId <= 0 || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                return $this->error('Invalid tracking payload', [], 422, 'VALIDATION_FAILED', $request);
            }

            $this->deliveryService->createTracking($auth, $deliveryId, $lat, $lng);
            return $this->success('Location uploaded', [], request: $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    /**
     * @param callable(array<string, mixed>, int): array<string, mixed> $callback
     */
    private function runAction(Request $request, callable $callback, string $message): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $deliveryId = (int) $request->attribute('id');
            $delivery = $callback($auth, $deliveryId);

            return $this->success($message, ['delivery' => $delivery], request: $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }
}
