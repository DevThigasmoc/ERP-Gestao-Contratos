<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_auth(['admin', 'gestor', 'vendedor']);

$companyId = current_company_id();
$user = current_user();
if ($companyId <= 0 && $user) {
    $companyId = (int) ($user['company_id'] ?? 0);
}

$invoiceRepository = new \App\Repositories\InvoiceRepository();
$subscriptionRepository = new \App\Repositories\SubscriptionRepository();
$efiClient = new \App\Finance\EfiClient($appConfig['finance']['efi']);
$invoiceService = new \App\Finance\InvoiceService($invoiceRepository, $subscriptionRepository, $efiClient, $appConfig['finance']['efi']['pix_key'] ?? '');

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['_token'] ?? '');
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'generate') {
            $reference = $_POST['reference'] ?? date('Y-m');
            $created = $invoiceService->generateMonthlyInvoices($companyId, $reference);
            $message = count($created) . ' faturas geradas para ' . $reference . '.';
        } elseif ($action === 'sync') {
            $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
            $invoice = $invoiceRepository->findById($companyId, $invoiceId);
            if ($invoice) {
                $charge = $efiClient->getChargeStatus($invoice->efiChargeId ?? $invoice->number ?? '');
                $status = $charge['status'] ?? 'ATIVA';
                if (in_array($status, ['CONCLUIDA', 'PAID'], true)) {
                    $invoiceRepository->updateStatus($companyId, $invoiceId, 'paid');
                } elseif (in_array($status, ['REMOVIDA_PELO_USUARIO_RECEBEDOR', 'REMOVIDA_PELO_PSP'], true)) {
                    $invoiceRepository->updateStatus($companyId, $invoiceId, 'canceled');
                }
                $message = 'Cobrança sincronizada com sucesso.';
            }
        } elseif ($action === 'cancel') {
            $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
            $invoiceRepository->updateStatus($companyId, $invoiceId, 'canceled');
            $message = 'Cobrança cancelada.';
        }
    } catch (\Throwable $e) {
        $error = 'Erro: ' . $e->getMessage();
    }
}

$filters = [
    'status' => $_GET['status'] ?? '',
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? '',
    'customer' => trim($_GET['customer'] ?? ''),
];

$pdo = db();
$sql = "SELECT i.*, s.customer_name FROM invoices i INNER JOIN subscriptions s ON s.id = i.subscription_id WHERE i.company_id = :company_id";
$params = [':company_id' => $companyId];

if ($filters['status'] !== '') {
    $sql .= " AND i.status = :status";
    $params[':status'] = $filters['status'];
}
if ($filters['from'] !== '') {
    $sql .= " AND i.due_date >= :from";
    $params[':from'] = $filters['from'];
}
if ($filters['to'] !== '') {
    $sql .= " AND i.due_date <= :to";
    $params[':to'] = $filters['to'];
}
if ($filters['customer'] !== '') {
    $sql .= " AND s.customer_name LIKE :customer";
    $params[':customer'] = '%' . $filters['customer'] . '%';
}
if ($user && $user['perfil'] === 'vendedor') {
    $sql .= " AND i.vendor_user_id = :vendor";
    $params[':vendor'] = (int) $user['id'];
}

$sql .= ' ORDER BY i.due_date DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$token = csrf_token();
$statuses = ['pending' => 'Pendente', 'paid' => 'Paga', 'overdue' => 'Vencida', 'canceled' => 'Cancelada'];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Contas a Receber</title>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css'); ?>">
</head>
<body>
<header class="app-header">
    <div class="logo">A Receber</div>
    <nav>
        <a href="<?= route('/financeiro/dashboard.php'); ?>">Dashboard</a>
        <a href="<?= route('/financeiro/receber.php'); ?>">A Receber</a>
        <a href="<?= route('/financeiro/pagar.php'); ?>">Comissões</a>
        <a href="<?= route('/financeiro/assinaturas.php'); ?>">Assinaturas</a>
        <a href="<?= route('/financeiro/config.php'); ?>">Configurações</a>
        <a href="<?= route('/auth/logout.php'); ?>">Sair</a>
    </nav>
</header>
<main class="layout">
    <section class="dashboard">
        <div class="card">
            <h1>Contas a Receber</h1>
            <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= sanitize($error); ?></div><?php endif; ?>
            <?php if (in_array($user['perfil'], ['admin', 'gestor'], true)): ?>
                <form method="post" class="inline-form">
                    <input type="hidden" name="_token" value="<?= sanitize($token); ?>">
                    <input type="month" name="reference" value="<?= sanitize($_GET['reference'] ?? date('Y-m')); ?>">
                    <button type="submit" name="action" value="generate" class="btn-primary">Gerar faturamento</button>
                </form>
            <?php endif; ?>
            <form method="get" class="filters">
                <label>Status
                    <select name="status">
                        <option value="">Todos</option>
                        <?php foreach ($statuses as $key => $label): ?>
                            <option value="<?= $key; ?>" <?= $filters['status'] === $key ? 'selected' : ''; ?>><?= $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>De
                    <input type="date" name="from" value="<?= sanitize($filters['from']); ?>">
                </label>
                <label>Até
                    <input type="date" name="to" value="<?= sanitize($filters['to']); ?>">
                </label>
                <label>Cliente
                    <input type="text" name="customer" value="<?= sanitize($filters['customer']); ?>">
                </label>
                <button type="submit" class="btn-secondary">Filtrar</button>
            </form>
            <table class="items-table">
                <thead><tr><th>Cobrança</th><th>Cliente</th><th>Vencimento</th><th>Valor</th><th>Status</th><th>Ações</th></tr></thead>
                <tbody>
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><?= sanitize($invoice['number'] ?? $invoice['efi_charge_id']); ?></td>
                        <td><?= sanitize($invoice['customer_name']); ?></td>
                        <td><?= date('d/m/Y', strtotime($invoice['due_date'])); ?></td>
                        <td><?= format_currency((float) $invoice['amount_net']); ?></td>
                        <td><span class="badge badge-status-<?= sanitize($invoice['status']); ?>"><?= sanitize($statuses[$invoice['status']] ?? $invoice['status']); ?></span></td>
                        <td>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="_token" value="<?= sanitize($token); ?>">
                                <input type="hidden" name="invoice_id" value="<?= (int) $invoice['id']; ?>">
                                <button type="submit" name="action" value="sync" class="btn-link">Conciliar</button>
                                <?php if (in_array($user['perfil'], ['admin', 'gestor'], true) && $invoice['status'] !== 'canceled'): ?>
                                    <button type="submit" name="action" value="cancel" class="btn-link">Cancelar</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
