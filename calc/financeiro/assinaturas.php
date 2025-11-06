<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_auth(['admin', 'gestor']);

$companyId = current_company_id();
$user = current_user();
if ($companyId <= 0 && $user) {
    $companyId = (int) ($user['company_id'] ?? 0);
}

$subscriptionRepository = new \App\Repositories\SubscriptionRepository();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['_token'] ?? '');
    $action = $_POST['action'] ?? '';
    $subscriptionId = (int) ($_POST['subscription_id'] ?? 0);

    try {
        if ($subscriptionId > 0) {
            if ($action === 'update-status') {
                $status = $_POST['status'] ?? 'active';
                $endDate = $_POST['end_date'] ?? null;
                $subscriptionRepository->updateStatus($companyId, $subscriptionId, $status, $endDate ?: null);
                $message = 'Assinatura atualizada com sucesso.';
            } elseif ($action === 'update-pricing') {
                $basePrice = parse_decimal($_POST['base_price'] ?? '0');
                $pague = (float) ($_POST['pague_em_dia_percent'] ?? 0);
                $subscriptionRepository->updatePricing($companyId, $subscriptionId, $basePrice, $pague);
                $message = 'Valores atualizados.';
            }
        }
    } catch (\Throwable $e) {
        $error = 'Erro: ' . $e->getMessage();
    }
}

$statusFilter = $_GET['status'] ?? '';
$pdo = db();
$sql = 'SELECT * FROM subscriptions WHERE company_id = :company_id';
$params = [':company_id' => $companyId];
if ($statusFilter !== '') {
    $sql .= ' AND status = :status';
    $params[':status'] = $statusFilter;
}
$sql .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$token = csrf_token();
$statuses = ['active' => 'Ativa', 'suspended' => 'Suspensa', 'canceled' => 'Cancelada'];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Assinaturas</title>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css'); ?>">
</head>
<body>
<header class="app-header">
    <div class="logo">Assinaturas</div>
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
            <h1>Assinaturas</h1>
            <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= sanitize($error); ?></div><?php endif; ?>
            <form method="get" class="filters">
                <label>Status
                    <select name="status">
                        <option value="">Todos</option>
                        <?php foreach ($statuses as $key => $label): ?>
                            <option value="<?= $key; ?>" <?= $statusFilter === $key ? 'selected' : ''; ?>><?= $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="btn-secondary">Filtrar</button>
            </form>
            <table class="items-table">
                <thead><tr><th>Cliente</th><th>Plano</th><th>Usuários</th><th>Valor base</th><th>Pague em dia %</th><th>Status</th><th>Ações</th></tr></thead>
                <tbody>
                <?php foreach ($subscriptions as $subscription): ?>
                    <tr>
                        <td><?= sanitize($subscription['customer_name']); ?></td>
                        <td><?= sanitize($subscription['plan_key']); ?></td>
                        <td><?= (int) $subscription['users_qtd']; ?></td>
                        <td><?= format_currency((float) $subscription['base_price']); ?></td>
                        <td><?= number_format((float) $subscription['pague_em_dia_percent'], 2, ',', '.'); ?>%</td>
                        <td><span class="badge badge-status-<?= sanitize($subscription['status']); ?>"><?= sanitize($statuses[$subscription['status']] ?? $subscription['status']); ?></span></td>
                        <td>
                            <details>
                                <summary>Gerenciar</summary>
                                <form method="post" class="stacked-form">
                                    <input type="hidden" name="_token" value="<?= sanitize($token); ?>">
                                    <input type="hidden" name="subscription_id" value="<?= (int) $subscription['id']; ?>">
                                    <label>Atualizar status
                                        <select name="status">
                                            <?php foreach ($statuses as $key => $label): ?>
                                                <option value="<?= $key; ?>" <?= $subscription['status'] === $key ? 'selected' : ''; ?>><?= $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>Data de término
                                        <input type="date" name="end_date" value="<?= sanitize($subscription['end_date'] ?? ''); ?>">
                                    </label>
                                    <button type="submit" name="action" value="update-status" class="btn-secondary">Salvar status</button>
                                </form>
                                <form method="post" class="stacked-form">
                                    <input type="hidden" name="_token" value="<?= sanitize($token); ?>">
                                    <input type="hidden" name="subscription_id" value="<?= (int) $subscription['id']; ?>">
                                    <label>Valor base
                                        <input type="text" name="base_price" value="<?= sanitize((string) $subscription['base_price']); ?>">
                                    </label>
                                    <label>Pague em Dia %
                                        <input type="number" step="0.01" name="pague_em_dia_percent" value="<?= sanitize((string) $subscription['pague_em_dia_percent']); ?>">
                                    </label>
                                    <button type="submit" name="action" value="update-pricing" class="btn-secondary">Atualizar valores</button>
                                </form>
                            </details>
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
