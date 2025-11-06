<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$secret = $_GET['secret'] ?? '';
if (!$secret || $secret !== ($appConfig['finance']['webhook_secret'] ?? '')) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo 'Invalid payload';
    exit;
}

$invoiceRepository = new \App\Repositories\InvoiceRepository();
$subscriptionRepository = new \App\Repositories\SubscriptionRepository();
$commissionRepository = new \App\Repositories\CommissionRepository();
$commissionService = new \App\Finance\CommissionService(
    $commissionRepository,
    $appConfig['finance']['commission']['default_rate'],
    $appConfig['finance']['commission']['splits'],
    $appConfig['finance']['commission']['due_day'],
);
$service = new \App\Finance\ReconciliationService($invoiceRepository, $subscriptionRepository, $commissionService);

$service->handleEfiWebhook($payload);

echo json_encode(['status' => 'ok']);
