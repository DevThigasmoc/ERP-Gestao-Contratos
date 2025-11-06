<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_auth(['admin', 'gestor']);

$companyId = current_company_id();
$user = current_user();
if ($companyId <= 0 && $user) {
    $companyId = (int) ($user['company_id'] ?? 0);
}

$pdo = db();
$currentMonth = date('Y-m');

$metrics = [
    'receber_mes' => 0.0,
    'recebido_mes' => 0.0,
    'aberto_total' => 0.0,
    'inadimplencia' => 0.0,
    'comissoes_mes' => 0.0,
    'comissoes_pagas' => 0.0,
];

$stmt = $pdo->prepare("SELECT SUM(amount_net) AS total FROM invoices WHERE company_id = :company_id AND status = 'pending' AND DATE_FORMAT(due_date, '%Y-%m') = :month");
$stmt->execute([':company_id' => $companyId, ':month' => $currentMonth]);
$metrics['receber_mes'] = (float) ($stmt->fetchColumn() ?: 0);

$stmt = $pdo->prepare("SELECT SUM(amount_net) AS total FROM invoices WHERE company_id = :company_id AND status = 'paid' AND DATE_FORMAT(paid_at, '%Y-%m') = :month");
$stmt->execute([':company_id' => $companyId, ':month' => $currentMonth]);
$metrics['recebido_mes'] = (float) ($stmt->fetchColumn() ?: 0);

$stmt = $pdo->prepare("SELECT SUM(amount_net) AS total FROM invoices WHERE company_id = :company_id AND status IN ('pending','overdue')");
$stmt->execute([':company_id' => $companyId]);
$metrics['aberto_total'] = (float) ($stmt->fetchColumn() ?: 0);

$stmt = $pdo->prepare("SELECT SUM(amount_net) FROM invoices WHERE company_id = :company_id AND status = 'overdue'");
$stmt->execute([':company_id' => $companyId]);
$overdue = (float) ($stmt->fetchColumn() ?: 0);
$totalBase = $metrics['recebido_mes'] + $overdue;
$metrics['inadimplencia'] = $totalBase > 0 ? ($overdue / $totalBase) * 100 : 0;

$stmt = $pdo->prepare("SELECT SUM(amount) FROM commission_installments ci INNER JOIN commissions c ON c.id = ci.commission_id WHERE c.company_id = :company_id AND ci.status = 'open' AND DATE_FORMAT(ci.due_date, '%Y-%m') = :month");
$stmt->execute([':company_id' => $companyId, ':month' => $currentMonth]);
$metrics['comissoes_mes'] = (float) ($stmt->fetchColumn() ?: 0);

$stmt = $pdo->prepare("SELECT SUM(amount) FROM commission_installments ci INNER JOIN commissions c ON c.id = ci.commission_id WHERE c.company_id = :company_id AND ci.status = 'paid' AND DATE_FORMAT(ci.paid_at, '%Y-%m') = :month");
$stmt->execute([':company_id' => $companyId, ':month' => $currentMonth]);
$metrics['comissoes_pagas'] = (float) ($stmt->fetchColumn() ?: 0);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Dashboard Financeiro</title>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css'); ?>">
</head>
<body>
<header class="app-header">
    <div class="logo">Financeiro</div>
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
        <div class="cards-row">
            <div class="stat-card">
                <span>A receber (<?= date('m/Y'); ?>)</span>
                <strong><?= format_currency($metrics['receber_mes']); ?></strong>
            </div>
            <div class="stat-card">
                <span>Recebido (<?= date('m/Y'); ?>)</span>
                <strong><?= format_currency($metrics['recebido_mes']); ?></strong>
            </div>
            <div class="stat-card">
                <span>Aberto total</span>
                <strong><?= format_currency($metrics['aberto_total']); ?></strong>
            </div>
        </div>
        <div class="cards-row">
            <div class="stat-card">
                <span>Inadimplência</span>
                <strong><?= number_format($metrics['inadimplencia'], 2, ',', '.'); ?>%</strong>
            </div>
            <div class="stat-card">
                <span>Comissões a pagar (<?= date('m/Y'); ?>)</span>
                <strong><?= format_currency($metrics['comissoes_mes']); ?></strong>
            </div>
            <div class="stat-card">
                <span>Comissões pagas (<?= date('m/Y'); ?>)</span>
                <strong><?= format_currency($metrics['comissoes_pagas']); ?></strong>
            </div>
        </div>
    </section>
</main>
</body>
</html>
