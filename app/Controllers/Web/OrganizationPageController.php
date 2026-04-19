<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Services\OrganizationService;
use Throwable;

final class OrganizationPageController extends BaseWebController
{
    public function __construct(
        \App\Core\View $view,
        private readonly OrganizationService $organizations
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
            $items = $this->organizations->list($auth);
            return $this->render('organizations.index', [
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
            $created = $this->organizations->create($auth, $request->body());
            $this->pushFlash('success', 'Organization created: ' . (string) ($created['name'] ?? '#'));
        } catch (Throwable $e) {
            $this->pushFlash('error', $e->getMessage());
        }

        return $this->redirect('/organizations');
    }

    private function pushFlash(string $type, string $message): void
    {
        $_SESSION['organization_flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array<string, string>|null
     */
    private function pullFlash(): ?array
    {
        $flash = $_SESSION['organization_flash'] ?? null;
        if (!is_array($flash)) {
            return null;
        }

        unset($_SESSION['organization_flash']);
        return [
            'type' => (string) ($flash['type'] ?? ''),
            'message' => (string) ($flash['message'] ?? ''),
        ];
    }
}

