<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Services\WebhookService;
use Throwable;

final class WebhookPageController extends BaseWebController
{
    public function __construct(
        \App\Core\View $view,
        private readonly WebhookService $webhookService
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
            $items = $this->webhookService->listEndpoints($auth);
            $flash = $this->pullFlash();

            return $this->render('webhooks.index', [
                'auth' => $auth,
                'items' => $items,
                'events' => $this->events(),
                'flash' => $flash,
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

        try {
            $this->webhookService->createEndpoint($auth, $request->body());
            $this->pushFlash('success', 'Webhook endpoint created.');
            return $this->redirect('/webhooks');
        } catch (Throwable $e) {
            $this->pushFlash('error', $e->getMessage());
            return $this->redirect('/webhooks');
        }
    }

    public function update(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        try {
            $id = (int) $request->attribute('id');
            $this->webhookService->updateEndpoint($auth, $id, $request->body());
            $this->pushFlash('success', 'Webhook endpoint updated.');
            return $this->redirect('/webhooks');
        } catch (Throwable $e) {
            $this->pushFlash('error', $e->getMessage());
            return $this->redirect('/webhooks');
        }
    }

    public function destroy(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        try {
            $id = (int) $request->attribute('id');
            $this->webhookService->deleteEndpoint($auth, $id);
            $this->pushFlash('success', 'Webhook endpoint deleted.');
            return $this->redirect('/webhooks');
        } catch (Throwable $e) {
            $this->pushFlash('error', $e->getMessage());
            return $this->redirect('/webhooks');
        }
    }

    /**
     * @return array<int, string>
     */
    private function events(): array
    {
        return [
            'delivery.created',
            'delivery.assigned',
            'delivery.picked_up',
            'delivery.in_transit',
            'delivery.signed',
            'delivery.completed',
            'delivery.failed',
            'delivery.cancelled',
            'delivery.cod_collected',
        ];
    }

    private function pushFlash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array<string, string>|null
     */
    private function pullFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        if (!is_array($flash)) {
            return null;
        }

        unset($_SESSION['flash']);
        return [
            'type' => (string) ($flash['type'] ?? ''),
            'message' => (string) ($flash['message'] ?? ''),
        ];
    }
}

