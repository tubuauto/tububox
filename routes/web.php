<?php

declare(strict_types=1);

use App\Controllers\Web\AuthController;
use App\Controllers\Web\ApiKeyPageController;
use App\Controllers\Web\AuditLogPageController;
use App\Controllers\Web\DashboardController;
use App\Controllers\Web\DeliveryPageController;
use App\Controllers\Web\DispatchPageController;
use App\Controllers\Web\DriverPageController;
use App\Controllers\Web\MarketplacePageController;
use App\Controllers\Web\OrganizationPageController;
use App\Controllers\Web\UserManagementPageController;
use App\Controllers\Web\WebhookPageController;
use App\Core\Response;
use App\Middlewares\RoleMiddleware;
use App\Middlewares\SessionAuthMiddleware;
use App\Middlewares\TenantScopeMiddleware;
use App\Policies\DeliveryPolicy;
use App\Policies\TenantPolicy;
use App\Repositories\ApiKeyRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\CodCollectionRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\DeliveryAssignmentRepository;
use App\Repositories\DeliveryLogRepository;
use App\Repositories\DeliveryRepository;
use App\Repositories\DeliveryTrackingRepository;
use App\Repositories\DriverRepository;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\ProofOfDeliveryRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Repositories\WebhookRepository;
use App\Services\ApiKeyService;
use App\Services\AuthService;
use App\Services\AuditService;
use App\Services\DeliveryService;
use App\Services\DispatchService;
use App\Services\DriverFulfillmentService;
use App\Services\LoginSecurityService;
use App\Services\OrganizationService;
use App\Services\TenantUserService;
use App\Services\WebhookService;

$users = new UserRepository($db);
$apiKeys = new ApiKeyRepository($db);
$auditLogs = new AuditLogRepository($db);
$loginAttempts = new LoginAttemptRepository($db);
$organizations = new OrganizationRepository($db);
$tenants = new TenantRepository($db);
$drivers = new DriverRepository($db);
$deliveries = new DeliveryRepository($db);
$deliveryLogs = new DeliveryLogRepository($db);
$tracking = new DeliveryTrackingRepository($db);
$assignments = new DeliveryAssignmentRepository($db);
$proofs = new ProofOfDeliveryRepository($db);
$codCollections = new CodCollectionRepository($db);
$webhooks = new WebhookRepository($db);
$dashboardRepo = new DashboardRepository($db);

$auditService = new AuditService($auditLogs);
$loginSecurity = new LoginSecurityService($loginAttempts, $auditService);
$authService = new AuthService($users);
$apiKeyService = new ApiKeyService($apiKeys, $auditService);
$organizationService = new OrganizationService($organizations, $auditService);
$tenantUserService = new TenantUserService($users, $organizations, $drivers, $auditService);
$webhookService = new WebhookService($webhooks);
$deliveryService = new DeliveryService($deliveries, $deliveryLogs, $tracking, $organizations, new DeliveryPolicy(new TenantPolicy()), $webhookService);
$dispatchService = new DispatchService($deliveryService, $drivers, $assignments);
$driverService = new DriverFulfillmentService($deliveryService, $deliveries, $deliveryLogs, $drivers, $proofs, $codCollections);

$authController = new AuthController($view, $authService, $loginSecurity);
$apiKeyPageController = new ApiKeyPageController($view, $apiKeyService);
$auditLogPageController = new AuditLogPageController($view, $auditLogs);
$organizationPageController = new OrganizationPageController($view, $organizationService);
$userManagementPageController = new UserManagementPageController($view, $tenantUserService);
$dashboardController = new DashboardController($view, $dashboardRepo);
$deliveryPageController = new DeliveryPageController($view, $deliveryService, $deliveryLogs, $organizations);
$dispatchPageController = new DispatchPageController($view, $deliveries, $drivers, $dispatchService);
$driverPageController = new DriverPageController($view, $drivers, $deliveries, $deliveryLogs, $deliveryService, $driverService);
$marketplacePageController = new MarketplacePageController($view, $deliveryService, $deliveryLogs, $organizations, $tenants);
$webhookPageController = new WebhookPageController($view, $webhookService);

$sessionAuth = new SessionAuthMiddleware($authService);
$tenantScope = new TenantScopeMiddleware();
$merchantRole = new RoleMiddleware(['admin', 'merchant', 'operator']);
$ownerRole = new RoleMiddleware(['admin', 'merchant']);
$dispatchRole = new RoleMiddleware(['admin', 'merchant', 'operator']);
$riderRole = new RoleMiddleware(['admin', 'rider']);
$userRole = new RoleMiddleware(['admin', 'user']);

$router->add('GET', '/', static function () use ($authService): Response {
    return $authService->user() === null ? Response::redirect('/login') : Response::redirect('/dashboard');
});

$router->add('GET', '/login', [$authController, 'loginForm']);
$router->add('POST', '/login', [$authController, 'login']);
$router->add('POST', '/logout', [$authController, 'logout'], [$sessionAuth]);

$router->add('GET', '/dashboard', [$dashboardController, 'index'], [$sessionAuth]);

$router->add('GET', '/api-keys', [$apiKeyPageController, 'index'], [$sessionAuth, $tenantScope, $ownerRole]);
$router->add('POST', '/api-keys/create', [$apiKeyPageController, 'create'], [$sessionAuth, $tenantScope, $ownerRole]);
$router->add('POST', '/api-keys/{id}/disable', [$apiKeyPageController, 'disable'], [$sessionAuth, $tenantScope, $ownerRole]);
$router->add('POST', '/api-keys/{id}/rotate', [$apiKeyPageController, 'rotate'], [$sessionAuth, $tenantScope, $ownerRole]);

$router->add('GET', '/audit-logs', [$auditLogPageController, 'index'], [$sessionAuth, $tenantScope, $ownerRole]);
$router->add('GET', '/organizations', [$organizationPageController, 'index'], [$sessionAuth, $tenantScope, $ownerRole]);
$router->add('POST', '/organizations/create', [$organizationPageController, 'create'], [$sessionAuth, $tenantScope, $ownerRole]);
$router->add('GET', '/stores', [$organizationPageController, 'index'], [$sessionAuth, $tenantScope, $ownerRole]);
$router->add('POST', '/stores/create', [$organizationPageController, 'create'], [$sessionAuth, $tenantScope, $ownerRole]);
$router->add('GET', '/users', [$userManagementPageController, 'index'], [$sessionAuth, $tenantScope, $ownerRole]);
$router->add('POST', '/users/create', [$userManagementPageController, 'create'], [$sessionAuth, $tenantScope, $ownerRole]);

$router->add('GET', '/deliveries', [$deliveryPageController, 'index'], [$sessionAuth, $tenantScope, $merchantRole]);
$router->add('GET', '/deliveries/create', [$deliveryPageController, 'createForm'], [$sessionAuth, $tenantScope, $merchantRole]);
$router->add('POST', '/deliveries', [$deliveryPageController, 'store'], [$sessionAuth, $tenantScope, $merchantRole]);
$router->add('GET', '/deliveries/{id}', [$deliveryPageController, 'show'], [$sessionAuth, $tenantScope, $merchantRole]);

$router->add('GET', '/marketplace/orders', [$marketplacePageController, 'index'], [$sessionAuth, $userRole]);
$router->add('GET', '/marketplace/orders/create', [$marketplacePageController, 'createForm'], [$sessionAuth, $userRole]);
$router->add('POST', '/marketplace/orders', [$marketplacePageController, 'store'], [$sessionAuth, $userRole]);
$router->add('GET', '/marketplace/orders/{id}', [$marketplacePageController, 'show'], [$sessionAuth, $userRole]);
$router->add('POST', '/marketplace/orders/{id}/cancel', [$marketplacePageController, 'cancel'], [$sessionAuth, $userRole]);

$router->add('GET', '/dispatch', [$dispatchPageController, 'index'], [$sessionAuth, $tenantScope, $dispatchRole]);
$router->add('POST', '/dispatch/assign', [$dispatchPageController, 'assign'], [$sessionAuth, $tenantScope, $dispatchRole]);
$router->add('POST', '/dispatch/reassign', [$dispatchPageController, 'reassign'], [$sessionAuth, $tenantScope, $dispatchRole]);
$router->add('POST', '/dispatch/mark-failed', [$dispatchPageController, 'markFailed'], [$sessionAuth, $tenantScope, $dispatchRole]);

$router->add('GET', '/webhooks', [$webhookPageController, 'index'], [$sessionAuth, $tenantScope, $merchantRole]);
$router->add('POST', '/webhooks', [$webhookPageController, 'store'], [$sessionAuth, $tenantScope, $merchantRole]);
$router->add('POST', '/webhooks/{id}/update', [$webhookPageController, 'update'], [$sessionAuth, $tenantScope, $merchantRole]);
$router->add('POST', '/webhooks/{id}/delete', [$webhookPageController, 'destroy'], [$sessionAuth, $tenantScope, $merchantRole]);

$router->add('GET', '/driver/deliveries', [$driverPageController, 'index'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('GET', '/driver/deliveries/{id}', [$driverPageController, 'show'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('POST', '/driver/deliveries/{id}/{action}', [$driverPageController, 'action'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('GET', '/rider/deliveries', [$driverPageController, 'index'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('GET', '/rider/deliveries/{id}', [$driverPageController, 'show'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('POST', '/rider/deliveries/{id}/{action}', [$driverPageController, 'action'], [$sessionAuth, $tenantScope, $riderRole]);
