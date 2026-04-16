<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\WebhookService;
use Throwable;

final class WebhookEndpointController extends BaseApiController
{
    public function __construct(private readonly WebhookService $webhookService)
    {
    }

    public function index(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $items = $this->webhookService->listEndpoints($auth);
            return $this->success('OK', ['items' => $items], request: $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    public function store(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $endpoint = $this->webhookService->createEndpoint($auth, $request->body());
            return $this->success('Webhook endpoint created', ['endpoint' => $endpoint], 201, $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    public function update(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $id = (int) $request->attribute('id');
            $endpoint = $this->webhookService->updateEndpoint($auth, $id, $request->body());
            return $this->success('Webhook endpoint updated', ['endpoint' => $endpoint], request: $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    public function destroy(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $id = (int) $request->attribute('id');
            $this->webhookService->deleteEndpoint($auth, $id);
            return $this->success('Webhook endpoint deleted', [], request: $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }
}

