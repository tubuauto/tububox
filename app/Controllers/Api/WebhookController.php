<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\WebhookRepository;
use Throwable;

final class WebhookController extends BaseApiController
{
    public function __construct(private readonly WebhookRepository $webhooks)
    {
    }

    public function deliveryStatus(Request $request): Response
    {
        try {
            $payload = $request->body();
            $delivery = is_array($payload['delivery'] ?? null) ? $payload['delivery'] : [];
            $deliveryId = isset($delivery['id']) ? (int) $delivery['id'] : null;

            $this->webhooks->createLog(
                deliveryId: $deliveryId,
                endpointId: null,
                payload: $payload,
                response: 'received',
                status: 'received'
            );

            return $this->success('Webhook received', [], request: $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }
}
