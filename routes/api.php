<?php

declare(strict_types=1);

use App\Controllers\Api\DeliveryController;
use App\Controllers\Api\DispatchController;
use App\Controllers\Api\DriverController;
use App\Controllers\Api\WebhookEndpointController;
use App\Controllers\Api\WebhookController;
use App\Middlewares\ApiKeyAuthMiddleware;
use App\Middlewares\RoleMiddleware;
use App\Middlewares\SessionAuthMiddleware;
use App\Middlewares\TenantScopeMiddleware;
use App\Policies\DeliveryPolicy;
use App\Policies\TenantPolicy;
use App\Repositories\ApiKeyRepository;
use App\Repositories\CodCollectionRepository;
use App\Repositories\DeliveryAssignmentRepository;
use App\Repositories\DeliveryLogRepository;
use App\Repositories\DeliveryRepository;
use App\Repositories\DeliveryTrackingRepository;
use App\Repositories\DriverRepository;
use App\Repositories\ProofOfDeliveryRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\UserRepository;
use App\Repositories\WebhookRepository;
use App\Services\AuthService;
use App\Services\DeliveryService;
use App\Services\DispatchService;
use App\Services\DriverFulfillmentService;
use App\Services\WebhookService;

$users = new UserRepository($db);
$apiKeys = new ApiKeyRepository($db);
$drivers = new DriverRepository($db);
$organizations = new OrganizationRepository($db);
$deliveries = new DeliveryRepository($db);
$deliveryLogs = new DeliveryLogRepository($db);
$tracking = new DeliveryTrackingRepository($db);
$assignments = new DeliveryAssignmentRepository($db);
$proofs = new ProofOfDeliveryRepository($db);
$codCollections = new CodCollectionRepository($db);
$webhooks = new WebhookRepository($db);

$authService = new AuthService($users);
$webhookService = new WebhookService($webhooks);
$deliveryService = new DeliveryService($deliveries, $deliveryLogs, $tracking, $organizations, new DeliveryPolicy(new TenantPolicy()), $webhookService);
$dispatchService = new DispatchService($deliveryService, $drivers, $assignments);
$driverService = new DriverFulfillmentService($deliveryService, $deliveries, $deliveryLogs, $drivers, $proofs, $codCollections);

$deliveryController = new DeliveryController($deliveryService, $deliveryLogs);
$dispatchController = new DispatchController($dispatchService);
$driverController = new DriverController($driverService, $deliveryService);
$webhookController = new WebhookController($webhooks);
$webhookEndpointController = new WebhookEndpointController($webhookService);

$apiKeyAuth = new ApiKeyAuthMiddleware($apiKeys);
$sessionAuth = new SessionAuthMiddleware($authService);
$tenantScope = new TenantScopeMiddleware();
$merchantRole = new RoleMiddleware(['admin', 'merchant', 'operator']);
$dispatchRole = new RoleMiddleware(['admin', 'merchant', 'operator']);
$riderRole = new RoleMiddleware(['admin', 'rider']);
$marketplaceRole = new RoleMiddleware(['admin', 'user']);

$router->add('POST', '/api/v1/deliveries', [$deliveryController, 'create'], [$apiKeyAuth, $tenantScope]);
$router->add('GET', '/api/v1/deliveries', [$deliveryController, 'index'], [$apiKeyAuth, $tenantScope]);
$router->add('GET', '/api/v1/deliveries/{id}', [$deliveryController, 'show'], [$apiKeyAuth, $tenantScope]);
$router->add('POST', '/api/v1/deliveries/{id}/cancel', [$deliveryController, 'cancel'], [$apiKeyAuth, $tenantScope]);
$router->add('GET', '/api/v1/deliveries/{id}/tracking', [$deliveryController, 'tracking'], [$apiKeyAuth, $tenantScope]);

$router->add('POST', '/api/v1/marketplace/orders', [$deliveryController, 'createMarketplace'], [$sessionAuth, $marketplaceRole]);
$router->add('GET', '/api/v1/marketplace/orders', [$deliveryController, 'indexMarketplace'], [$sessionAuth, $marketplaceRole]);
$router->add('GET', '/api/v1/marketplace/orders/{id}', [$deliveryController, 'showMarketplace'], [$sessionAuth, $marketplaceRole]);

$router->add('POST', '/api/v1/dispatch/assign', [$dispatchController, 'assign'], [$sessionAuth, $tenantScope, $dispatchRole]);
$router->add('POST', '/api/v1/dispatch/reassign', [$dispatchController, 'reassign'], [$sessionAuth, $tenantScope, $dispatchRole]);
$router->add('POST', '/api/v1/dispatch/mark-failed', [$dispatchController, 'markFailed'], [$sessionAuth, $tenantScope, $dispatchRole]);

$router->add('POST', '/api/v1/driver/deliveries/{id}/accept', [$driverController, 'accept'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('POST', '/api/v1/driver/deliveries/{id}/arrive-pickup', [$driverController, 'arrivePickup'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('POST', '/api/v1/driver/deliveries/{id}/pickup', [$driverController, 'pickup'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('POST', '/api/v1/driver/deliveries/{id}/arrive-dropoff', [$driverController, 'arriveDropoff'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('POST', '/api/v1/driver/deliveries/{id}/sign', [$driverController, 'sign'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('POST', '/api/v1/driver/deliveries/{id}/complete', [$driverController, 'complete'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('POST', '/api/v1/driver/deliveries/{id}/cod-collect', [$driverController, 'codCollect'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('POST', '/api/v1/driver/location', [$driverController, 'location'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('POST', '/api/v1/rider/deliveries/{id}/accept', [$driverController, 'accept'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('POST', '/api/v1/rider/deliveries/{id}/arrive-pickup', [$driverController, 'arrivePickup'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('POST', '/api/v1/rider/deliveries/{id}/pickup', [$driverController, 'pickup'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('POST', '/api/v1/rider/deliveries/{id}/arrive-dropoff', [$driverController, 'arriveDropoff'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('POST', '/api/v1/rider/deliveries/{id}/sign', [$driverController, 'sign'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('POST', '/api/v1/rider/deliveries/{id}/complete', [$driverController, 'complete'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('POST', '/api/v1/rider/deliveries/{id}/cod-collect', [$driverController, 'codCollect'], [$sessionAuth, $tenantScope, $riderRole]);
$router->add('POST', '/api/v1/rider/location', [$driverController, 'location'], [$sessionAuth, $tenantScope, $riderRole]);

$router->add('POST', '/api/v1/webhooks/delivery-status', [$webhookController, 'deliveryStatus']);
$router->add('GET', '/api/v1/webhook-endpoints', [$webhookEndpointController, 'index'], [$sessionAuth, $tenantScope, $merchantRole]);
$router->add('POST', '/api/v1/webhook-endpoints', [$webhookEndpointController, 'store'], [$sessionAuth, $tenantScope, $merchantRole]);
$router->add('POST', '/api/v1/webhook-endpoints/{id}/update', [$webhookEndpointController, 'update'], [$sessionAuth, $tenantScope, $merchantRole]);
$router->add('POST', '/api/v1/webhook-endpoints/{id}/delete', [$webhookEndpointController, 'destroy'], [$sessionAuth, $tenantScope, $merchantRole]);
