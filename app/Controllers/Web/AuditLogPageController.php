<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditLogRepository;
use Throwable;

final class AuditLogPageController extends BaseWebController
{
    public function __construct(
        \App\Core\View $view,
        private readonly AuditLogRepository $auditLogs
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
            $tenantRaw = $auth['tenant_id'] ?? null;
            $tenantId = $tenantRaw !== null ? (int) $tenantRaw : null;
            $isAdmin = (bool) ($auth['is_admin'] ?? false);
            $items = $this->auditLogs->listRecent($tenantId, $isAdmin, 200);
        } catch (Throwable $e) {
            return Response::html('<h1>400</h1><p>' . h($e->getMessage()) . '</p>', 400);
        }

        return $this->render('auditlogs.index', [
            'auth' => $auth,
            'items' => $items,
        ]);
    }
}
