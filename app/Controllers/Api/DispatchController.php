<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\DispatchService;
use Throwable;

final class DispatchController extends BaseApiController
{
    public function __construct(private readonly DispatchService $dispatchService)
    {
    }

    public function assign(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $delivery = $this->dispatchService->assign($auth, $request->body());
            return $this->success('Assigned', ['delivery' => $delivery], request: $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    public function reassign(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $delivery = $this->dispatchService->reassign($auth, $request->body());
            return $this->success('Reassigned', ['delivery' => $delivery], request: $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    public function markFailed(Request $request): Response
    {
        try {
            $auth = $request->attribute('auth');
            if (!is_array($auth)) {
                return $this->error('Unauthorized', [], 401, 'UNAUTHORIZED', $request);
            }

            $delivery = $this->dispatchService->markFailed($auth, $request->body());
            return $this->success('Marked failed', ['delivery' => $delivery], request: $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }
}
