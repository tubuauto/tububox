<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Services\ApiKeyService;
use Throwable;

final class ApiKeyPageController extends BaseWebController
{
    public function __construct(
        \App\Core\View $view,
        private readonly ApiKeyService $apiKeys
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
            $items = $this->apiKeys->list($auth);
            return $this->render('apikeys.index', [
                'auth' => $auth,
                'items' => $items,
                'flash' => $this->pullFlash(),
            ]);
        } catch (Throwable $e) {
            return Response::html('<h1>400</h1><p>' . h($e->getMessage()) . '</p>', 400);
        }
    }

    public function create(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        try {
            $created = $this->apiKeys->create($auth);
            $this->pushFlash('success', sprintf(
                'New API key created: %s / %s',
                (string) ($created['api_key'] ?? ''),
                (string) ($created['api_secret'] ?? '')
            ));
        } catch (Throwable $e) {
            $this->pushFlash('error', $e->getMessage());
        }

        return $this->redirect('/api-keys');
    }

    public function disable(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        try {
            $this->apiKeys->disable($auth, (int) $request->attribute('id'));
            $this->pushFlash('success', 'API key disabled.');
        } catch (Throwable $e) {
            $this->pushFlash('error', $e->getMessage());
        }

        return $this->redirect('/api-keys');
    }

    public function rotate(Request $request): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            return $this->redirect('/login');
        }

        try {
            $newKey = $this->apiKeys->rotate($auth, (int) $request->attribute('id'));
            $this->pushFlash('success', sprintf(
                'API key rotated. New credentials: %s / %s',
                (string) ($newKey['api_key'] ?? ''),
                (string) ($newKey['api_secret'] ?? '')
            ));
        } catch (Throwable $e) {
            $this->pushFlash('error', $e->getMessage());
        }

        return $this->redirect('/api-keys');
    }

    private function pushFlash(string $type, string $message): void
    {
        $_SESSION['apikey_flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array<string, string>|null
     */
    private function pullFlash(): ?array
    {
        $flash = $_SESSION['apikey_flash'] ?? null;
        if (!is_array($flash)) {
            return null;
        }

        unset($_SESSION['apikey_flash']);
        return [
            'type' => (string) ($flash['type'] ?? ''),
            'message' => (string) ($flash['message'] ?? ''),
        ];
    }
}

