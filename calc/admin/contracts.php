<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_auth(['admin', 'gestor', 'vendedor']);

$user = current_user();
$repo = new ContractRepository();
$filters = [
    'status' => $_GET['status'] ?? '',
];
if ($user['perfil'] !== 'vendedor') {
    $filters['user_id'] = $_GET['user_id'] ?? '';
}
$contracts = $repo->list($filters, $user);
$usersList = [];
if ($user['perfil'] !== 'vendedor') {
    $userRepo = new UserRepository();
    $usersList = $userRepo->all();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Contratos - Painel KAVVI</title>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css'); ?>">
</head>
<body>
<header class="app-header">
    <div class="logo">Painel KAVVI</div>
    <nav>
        <a href="/admin/index.php">Dashboard</a>
        <?php if (in_array($user['perfil'], ['admin'], true)): ?>
            <a href="/admin/users.php">Usuários</a>
        <?php endif; ?>
        <a href="/admin/proposals.php">Propostas</a>
        <a href="/auth/logout.php">Sair</a>
    </nav>
</header>
<main class="layout">
    <section class="dashboard">
        <div class="card">
            <h1>Contratos</h1>
            <form method="get" class="filters">
                <div class="grid">
                    <label>Status
                        <select name="status">
                            <option value="">Todos</option>
                            <option value="gerado" <?= (($filters['status'] ?? '') === 'gerado') ? 'selected' : ''; ?>>Gerado</option>
                            <option value="assinado" <?= (($filters['status'] ?? '') === 'assinado') ? 'selected' : ''; ?>>Assinado</option>
                        </select>
                    </label>
                    <?php if ($user['perfil'] !== 'vendedor'): ?>
                        <label>Usuário
                            <select name="user_id">
                                <option value="">Todos</option>
                                <?php foreach ($usersList as $u): ?>
                                    <option value="<?= (int) $u['id']; ?>" <?= (($filters['user_id'] ?? '') == $u['id']) ? 'selected' : ''; ?>><?= sanitize($u['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn-secondary">Filtrar</button>
            </form>
            <table class="items-table">
                <thead><tr><th>ID</th><th>Proposta</th><th>Cliente</th><th>Vendedor</th><th>Status</th><th>Criado em</th><th>Arquivo</th></tr></thead>
                <tbody>
                    <?php foreach ($contracts as $contract): ?>
                        <tr>
                            <td>#<?= (int) $contract['id']; ?></td>
                            <td><a href="/index.php?proposal=<?= (int) $contract['proposal_id']; ?>">#<?= (int) $contract['proposal_id']; ?></a></td>
                            <td><?= sanitize($contract['empresa_nome']); ?></td>
                            <td><?= sanitize($contract['vendedor_nome']); ?></td>
                            <td><?= sanitize($contract['status']); ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($contract['created_at'])); ?></td>
                            <td>
                                <?php if (!empty($contract['pdf_path']) && file_exists($contract['pdf_path'])): ?>
                                    <a class="btn-link" href="/admin/download.php?id=<?= (int) $contract['id']; ?>&type=contract">Baixar</a>
                                <?php else: ?>
                                    <span class="badge">Arquivo não encontrado</span>
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
