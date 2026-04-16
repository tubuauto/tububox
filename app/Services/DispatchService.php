<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\DeliveryAssignmentRepository;
use App\Repositories\DriverRepository;
use App\Requests\DispatchAssignRequest;

final class DispatchService
{
    public function __construct(
        private readonly DeliveryService $deliveryService,
        private readonly DriverRepository $drivers,
        private readonly DeliveryAssignmentRepository $assignments
    ) {
    }

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function assign(array $auth, array $payload): array
    {
        $errors = DispatchAssignRequest::validate($payload);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $driverId = (int) $payload['driver_id'];
        $driver = $this->drivers->findById($driverId);
        if ($driver === null) {
            throw new NotFoundException('Driver not found.');
        }

        if (($auth['is_admin'] ?? false) !== true && (int) $driver['tenant_id'] !== (int) $auth['tenant_id']) {
            throw new ForbiddenException('Driver out of tenant scope.');
        }

        $deliveryId = (int) $payload['delivery_id'];
        $note = isset($payload['note']) ? (string) $payload['note'] : null;

        $delivery = $this->deliveryService->assignDriver($auth, $deliveryId, $driverId, $note);
        $this->assignments->create($deliveryId, $driverId, $auth['id'] ?? null, $note);

        return $delivery;
    }

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function reassign(array $auth, array $payload): array
    {
        return $this->assign($auth, $payload);
    }

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function markFailed(array $auth, array $payload): array
    {
        $deliveryId = (int) ($payload['delivery_id'] ?? 0);
        if ($deliveryId <= 0) {
            throw new BadRequestException('delivery_id is required.', [
                'delivery_id' => 'delivery_id is required.',
            ]);
        }

        return $this->deliveryService->transition(
            auth: $auth,
            deliveryId: $deliveryId,
            toStatus: 'failed',
            note: (string) ($payload['reason'] ?? 'Marked failed by dispatcher')
        );
    }
}
