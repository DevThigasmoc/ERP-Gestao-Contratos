<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_auth(['admin', 'gestor', 'vendedor']);

$user = current_user();
$pdo = db();

$totalClients = (int) $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
$totalProposals = (int) $pdo->query('SELECT COUNT(*) FROM proposals')->fetchColumn();
$totalContracts = (int) $pdo->query('SELECT COUNT(*) FROM contracts')->fetchColumn();

$proposalRepo = new ProposalRepository();
$recentProposals = $proposalRepo->list([], $user);
$recentProposals = array_slice($recentProposals, 0, 10);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Painel - KAVVI</title>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css'); ?>">
</head>
<body>
<header class="app-header">
    <div class="logo">Painel KAVVI</div>
    <nav>
        <a href="<?= route('/index.php'); ?>">Calculadora</a>
        <?php if (in_array($user['perfil'], ['admin', 'gestor'], true)): ?>
            <a href="<?= route('/admin/users.php'); ?>">Usuários</a>
        <?php endif; ?>
        <a href="<?= route('/admin/proposals.php'); ?>">Propostas</a>
        <a href="<?= route('/admin/contracts.php'); ?>">Contratos</a>
        <a href="<?= route('/auth/logout.php'); ?>">Sair</a>
    </nav>
</header>
<main class="layout">
    <section class="dashboard">
        <div class="cards-row">
            <div class="stat-card">
                <span>Total de Clientes</span>
                <strong><?= $totalClients; ?></strong>
            </div>
            <div class="stat-card">
                <span>Total de Propostas</span>
                <strong><?= $totalProposals; ?></strong>
            </div>
            <div class="stat-card">
                <span>Contratos Gerados</span>
                <strong><?= $totalContracts; ?></strong>
            </div>
        </div>
        <div class="card">
            <h2>Últimas propostas</h2>
            <table class="items-table">
                <thead><tr><th>ID</th><th>Cliente</th><th>Vendedor</th><th>Status</th><th>Criada em</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($recentProposals as $proposal): ?>
                        <tr>
                            <td>#<?= (int) $proposal['id']; ?></td>
                            <td><?= sanitize($proposal['empresa_nome']); ?></td>
                            <td><?= sanitize($proposal['vendedor_nome']); ?></td>
                            <td><span class="badge badge-status-<?= sanitize($proposal['status']); ?>"><?= sanitize($proposal['status']); ?></span></td>
                            <td><?= date('d/m/Y H:i', strtotime($proposal['created_at'])); ?></td>
                            <td><a class="btn-link" href="<?= route('/index.php'); ?>?proposal=<?= (int) $proposal['id']; ?>">Abrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
