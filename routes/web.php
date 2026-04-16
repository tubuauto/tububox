<?php

declare(strict_types=1);

use App\Controllers\Web\AuthController;
use App\Controllers\Web\DashboardController;
use App\Controllers\Web\DeliveryPageController;
use App\Controllers\Web\DispatchPageController;
use App\Controllers\Web\DriverPageController;
use App\Controllers\Web\WebhookPageController;
use App\Core\Response;
use App\Middlewares\RoleMiddleware;
use App\Middlewares\SessionAuthMiddleware;
use App\Middlewares\TenantScopeMiddleware;
use App\Policies\DeliveryPolicy;
use App\Policies\TenantPolicy;
use App\Repositories\CodCollectionRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\DeliveryAssignmentRepository;
use App\Repositories\DeliveryLogRepository;
use App\Repositories\DeliveryRepository;
use App\Repositories\DeliveryTrackingRepository;
use App\Repositories\DriverRepository;
use App\Repositories\ProofOfDeliveryRepository;
use App\Repositories\UserRepository;
use App\Repositories\WebhookRepository;
use App\Services\AuthService;
use App\Services\DeliveryService;
use App\Services\DispatchService;
use App\Services\DriverFulfillmentService;
use App\Services\WebhookService;

$users = new UserRepository($db);
$drivers = new DriverRepository($db);
$deliveries = new DeliveryRepository($db);
$deliveryLogs = new DeliveryLogRepository($db);
$tracking = new DeliveryTrackingRepository($db);
$assignments = new DeliveryAssignmentRepository($db);
$proofs = new ProofOfDeliveryRepository($db);
$codCollections = new CodCollectionRepository($db);
$webhooks = new WebhookRepository($db);
$dashboardRepo = new DashboardRepository($db);

$authService = new AuthService($users);
$webhookService = new WebhookService($webhooks);
$deliveryService = new DeliveryService($deliveries, $deliveryLogs, $tracking, new DeliveryPolicy(new TenantPolicy()), $webhookService);
$dispatchService = new DispatchService($deliveryService, $drivers, $assignments);
$driverService = new DriverFulfillmentService($deliveryService, $deliveries, $drivers, $proofs, $codCollections);

$authController = new AuthController($view, $authService);
$dashboardController = new DashboardController($view, $dashboardRepo);
$deliveryPageController = new DeliveryPageController($view, $deliveryService, $deliveryLogs);
$dispatchPageController = new DispatchPageController($view, $deliveries, $drivers, $dispatchService);
$driverPageController = new DriverPageController($view, $drivers, $deliveries, $deliveryService, $driverService);
$webhookPageController = new WebhookPageController($view, $webhookService);

$sessionAuth = new SessionAuthMiddleware($authService);
$tenantScope = new TenantScopeMiddleware();
$merchantRole = new RoleMiddleware(['admin', 'tenant_admin', 'operator', 'dispatcher']);
$dispatchRole = new RoleMiddleware(['admin', 'tenant_admin', 'dispatcher']);
$driverRole = new RoleMiddleware(['admin', 'driver']);

$router->add('GET', '/', static function () use ($authService): Response {
    return $authService->user() === null ? Response::redirect('/login') : Response::redirect('/dashboard');
});

$router->add('GET', '/login', [$authController, 'loginForm']);
$router->add('POST', '/login', [$authController, 'login']);
$router->add('POST', '/logout', [$authController, 'logout'], [$sessionAuth]);

$router->add('GET', '/dashboard', [$dashboardController, 'index'], [$sessionAuth]);

$router->add('GET', '/deliveries', [$deliveryPageController, 'index'], [$sessionAuth, $tenantScope, $merchantRole]);
$router->add('GET', '/deliveries/create', [$deliveryPageController, 'createForm'], [$sessionAuth, $tenantScope, $merchantRole]);
$router->add('POST', '/deliveries', [$deliveryPageController, 'store'], [$sessionAuth, $tenantScope, $merchantRole]);
$router->add('GET', '/deliveries/{id}', [$deliveryPageController, 'show'], [$sessionAuth, $tenantScope, $merchantRole]);

$router->add('GET', '/dispatch', [$dispatchPageController, 'index'], [$sessionAuth, $tenantScope, $dispatchRole]);
$router->add('POST', '/dispatch/assign', [$dispatchPageController, 'assign'], [$sessionAuth, $tenantScope, $dispatchRole]);
$router->add('POST', '/dispatch/reassign', [$dispatchPageController, 'reassign'], [$sessionAuth, $tenantScope, $dispatchRole]);
$router->add('POST', '/dispatch/mark-failed', [$dispatchPageController, 'markFailed'], [$sessionAuth, $tenantScope, $dispatchRole]);

$router->add('GET', '/webhooks', [$webhookPageController, 'index'], [$sessionAuth, $tenantScope, $merchantRole]);
$router->add('POST', '/webhooks', [$webhookPageController, 'store'], [$sessionAuth, $tenantScope, $merchantRole]);
$router->add('POST', '/webhooks/{id}/update', [$webhookPageController, 'update'], [$sessionAuth, $tenantScope, $merchantRole]);
$router->add('POST', '/webhooks/{id}/delete', [$webhookPageController, 'destroy'], [$sessionAuth, $tenantScope, $merchantRole]);

$router->add('GET', '/driver/deliveries', [$driverPageController, 'index'], [$sessionAuth, $tenantScope, $driverRole]);
$router->add('GET', '/driver/deliveries/{id}', [$driverPageController, 'show'], [$sessionAuth, $tenantScope, $driverRole]);
$router->add('POST', '/driver/deliveries/{id}/{action}', [$driverPageController, 'action'], [$sessionAuth, $tenantScope, $driverRole]);
