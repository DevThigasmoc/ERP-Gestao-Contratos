<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_auth(['admin', 'gestor', 'vendedor']);

$companyId = current_company_id();
$user = current_user();
if ($companyId <= 0 && $user) {
    $companyId = (int) ($user['company_id'] ?? 0);
}

$commissionRepository = new \App\Repositories\CommissionRepository();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['_token'] ?? '');
    $installmentId = (int) ($_POST['installment_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    try {
        if ($installmentId > 0) {
            if ($action === 'pay') {
                $commissionRepository->updateInstallmentStatus($companyId, $installmentId, 'paid');
                $message = 'Parcela marcada como paga.';
            } elseif ($action === 'cancel') {
                $commissionRepository->updateInstallmentStatus($companyId, $installmentId, 'canceled');
                $message = 'Parcela cancelada.';
            }
        }
    } catch (\Throwable $e) {
        $error = 'Erro ao atualizar parcela: ' . $e->getMessage();
    }
}

$filters = [
    'status' => $_GET['status'] ?? '',
    'month' => $_GET['month'] ?? date('Y-m'),
    'vendor' => $_GET['vendor'] ?? '',
];

$pdo = db();
$sql = "SELECT ci.*, c.vendor_user_id, c.total_commission, u.nome AS vendor_name FROM commission_installments ci INNER JOIN commissions c ON c.id = ci.commission_id LEFT JOIN users u ON u.id = c.vendor_user_id WHERE c.company_id = :company_id";
$params = [':company_id' => $companyId];

if ($filters['status'] !== '') {
    $sql .= " AND ci.status = :status";
    $params[':status'] = $filters['status'];
}
if ($filters['month'] !== '') {
    $sql .= " AND DATE_FORMAT(ci.due_date, '%Y-%m') = :month";
    $params[':month'] = $filters['month'];
}
if ($filters['vendor'] !== '') {
    $sql .= " AND c.vendor_user_id = :vendor";
    $params[':vendor'] = (int) $filters['vendor'];
}
if ($user && $user['perfil'] === 'vendedor') {
    $sql .= " AND c.vendor_user_id = :vendor_user";
    $params[':vendor_user'] = (int) $user['id'];
}

$sql .= ' ORDER BY ci.due_date ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$installments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$vendors = $pdo->prepare('SELECT id, nome FROM users WHERE company_id = :company_id AND perfil = "vendedor" ORDER BY nome');
$vendors->execute([':company_id' => $companyId]);
$vendorsList = $vendors->fetchAll(PDO::FETCH_ASSOC) ?: [];

$token = csrf_token();
$statuses = ['open' => 'Aberta', 'paid' => 'Paga', 'canceled' => 'Cancelada'];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Comissões</title>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css'); ?>">
</head>
<body>
<header class="app-header">
    <div class="logo">Comissões</div>
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
            <h1>Comissões a pagar</h1>
            <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= sanitize($error); ?></div><?php endif; ?>
            <form method="get" class="filters">
                <label>Status
                    <select name="status">
                        <option value="">Todos</option>
                        <?php foreach ($statuses as $key => $label): ?>
                            <option value="<?= $key; ?>" <?= $filters['status'] === $key ? 'selected' : ''; ?>><?= $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Mês
                    <input type="month" name="month" value="<?= sanitize($filters['month']); ?>">
                </label>
                <?php if (in_array($user['perfil'], ['admin', 'gestor'], true)): ?>
                    <label>Vendedor
                        <select name="vendor">
                            <option value="">Todos</option>
                            <?php foreach ($vendorsList as $vendor): ?>
                                <option value="<?= (int) $vendor['id']; ?>" <?= ((string) $filters['vendor'] === (string) $vendor['id']) ? 'selected' : ''; ?>><?= sanitize($vendor['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
                <button type="submit" class="btn-secondary">Filtrar</button>
            </form>
            <table class="items-table">
                <thead><tr><th>Vendedor</th><th>Parcela</th><th>Vencimento</th><th>Valor</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($installments as $item): ?>
                    <tr>
                        <td><?= sanitize($item['vendor_name'] ?? 'Sem vendedor'); ?></td>
                        <td><?= (int) $item['n']; ?>/<?= (int) $item['splits']; ?></td>
                        <td><?= date('d/m/Y', strtotime($item['due_date'])); ?></td>
                        <td><?= format_currency((float) $item['amount']); ?></td>
                        <td><span class="badge badge-status-<?= sanitize($item['status']); ?>"><?= sanitize($statuses[$item['status']] ?? $item['status']); ?></span></td>
                        <td>
                            <?php if (in_array($user['perfil'], ['admin', 'gestor'], true)): ?>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="_token" value="<?= sanitize($token); ?>">
                                    <input type="hidden" name="installment_id" value="<?= (int) $item['id']; ?>">
                                    <?php if ($item['status'] === 'open'): ?>
                                        <button type="submit" name="action" value="pay" class="btn-link">Marcar pago</button>
                                        <button type="submit" name="action" value="cancel" class="btn-link">Cancelar</button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
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
