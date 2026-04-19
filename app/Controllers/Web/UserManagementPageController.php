<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Services\TenantUserService;
use Throwable;

final class UserManagementPageController extends BaseWebController
{
    public function __construct(
        \App\Core\View $view,
        private readonly TenantUserService $users
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
            $items = $this->users->list($auth);
            $organizations = $this->users->organizations($auth);

            return $this->render('users.index', [
                'auth' => $auth,
                'items' => $items,
                'organizations' => $organizations,
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
            $created = $this->users->create($auth, $request->body());
            $this->pushFlash('success', 'User created: ' . (string) ($created['email'] ?? '#'));
        } catch (Throwable $e) {
            $this->pushFlash('error', $e->getMessage());
        }

        return $this->redirect('/users');
    }

    private function pushFlash(string $type, string $message): void
    {
        $_SESSION['user_mgmt_flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array<string, string>|null
     */
    private function pullFlash(): ?array
    {
        $flash = $_SESSION['user_mgmt_flash'] ?? null;
        if (!is_array($flash)) {
            return null;
        }

        unset($_SESSION['user_mgmt_flash']);
        return [
            'type' => (string) ($flash['type'] ?? ''),
            'message' => (string) ($flash['message'] ?? ''),
        ];
    }
}

