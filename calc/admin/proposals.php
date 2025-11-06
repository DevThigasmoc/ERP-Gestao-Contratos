<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_auth(['admin', 'gestor', 'vendedor']);

$user = current_user();
$repo = new ProposalRepository();
$service = new ProposalService($appConfig);
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['_token'] ?? '');
    $action = $_POST['action'] ?? '';
    $proposalId = (int) ($_POST['proposal_id'] ?? 0);
    try {
        if ($action === 'close') {
            $service->closeProposal($proposalId, $user);
            $message = 'Proposta marcada como fechada.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$filters = [
    'status' => $_GET['status'] ?? '',
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? '',
];
if ($user['perfil'] !== 'vendedor') {
    $filters['user_id'] = $_GET['user_id'] ?? '';
}

$proposals = $repo->list($filters, $user);
$usersList = [];
if ($user['perfil'] !== 'vendedor') {
    $userRepo = new UserRepository();
    $usersList = $userRepo->all();
}
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Propostas - Painel KAVVI</title>
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
        <a href="/admin/contracts.php">Contratos</a>
        <a href="/auth/logout.php">Sair</a>
    </nav>
</header>
<main class="layout">
    <section class="dashboard">
        <div class="card">
            <h1>Propostas</h1>
            <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= sanitize($error); ?></div><?php endif; ?>
            <form method="get" class="filters">
                <div class="grid">
                    <label>Status
                        <select name="status">
                            <option value="">Todos</option>
                            <option value="rascunho" <?= (($filters['status'] ?? '') === 'rascunho') ? 'selected' : ''; ?>>Rascunho</option>
                            <option value="enviada" <?= (($filters['status'] ?? '') === 'enviada') ? 'selected' : ''; ?>>Enviada</option>
                            <option value="aceita" <?= (($filters['status'] ?? '') === 'aceita') ? 'selected' : ''; ?>>Aceita</option>
                            <option value="fechada" <?= (($filters['status'] ?? '') === 'fechada') ? 'selected' : ''; ?>>Fechada</option>
                        </select>
                    </label>
                    <label>De
                        <input type="date" name="from" value="<?= sanitize($filters['from'] ?? ''); ?>">
                    </label>
                    <label>Até
                        <input type="date" name="to" value="<?= sanitize($filters['to'] ?? ''); ?>">
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
                <thead><tr><th>ID</th><th>Cliente</th><th>Vendedor</th><th>Status</th><th>Atualizada</th><th>Ações</th></tr></thead>
                <tbody>
                    <?php foreach ($proposals as $proposal): ?>
                        <tr>
                            <td>#<?= (int) $proposal['id']; ?></td>
                            <td><?= sanitize($proposal['empresa_nome']); ?></td>
                            <td><?= sanitize($proposal['vendedor_nome']); ?></td>
                            <td><span class="badge badge-status-<?= sanitize($proposal['status']); ?>"><?= sanitize($proposal['status']); ?></span></td>
                            <td><?= date('d/m/Y H:i', strtotime($proposal['updated_at'])); ?></td>
                            <td>
                                <a class="btn-link" href="/index.php?proposal=<?= (int) $proposal['id']; ?>">Abrir</a>
                                <a class="btn-link" href="/propostas/ver.php?token=<?= urlencode($proposal['share_token']); ?>" target="_blank">Link público</a>
                                <?php if ($proposal['status'] !== 'fechada'): ?>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="_token" value="<?= sanitize($token); ?>">
                                        <input type="hidden" name="proposal_id" value="<?= (int) $proposal['id']; ?>">
                                        <button type="submit" name="action" value="close" class="btn-link">Marcar fechada</button>
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
