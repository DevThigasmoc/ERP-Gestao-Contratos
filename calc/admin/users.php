<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_auth(['admin']);

$repo = new UserRepository();
$user = current_user();
$error = null;
$message = null;
$editingUser = null;

if (isset($_GET['edit'])) {
    $editingUser = $repo->findById((int) $_GET['edit']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['_token'] ?? '');
    $action = $_POST['action'] ?? 'create';
    $payload = [
        'nome' => trim($_POST['nome'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'perfil' => $_POST['perfil'] ?? 'vendedor',
        'ativo' => isset($_POST['ativo']) ? 1 : 0,
    ];
    if ($payload['nome'] === '' || $payload['email'] === '') {
        $error = 'Nome e e-mail são obrigatórios.';
    } else {
        try {
            if ($action === 'create') {
                $password = $_POST['password'] ?? '';
                if (strlen($password) < 6) {
                    throw new RuntimeException('Informe uma senha com pelo menos 6 caracteres.');
                }
                $payload['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
                $newId = $repo->create($payload);
                record_audit($user['id'], 'criou_usuario', 'user', $newId, ['perfil' => $payload['perfil']]);
                $message = 'Usuário criado com sucesso!';
            } else {
                if (!empty($_POST['password'])) {
                    $payload['password_hash'] = password_hash($_POST['password'], PASSWORD_BCRYPT);
                } else {
                    $payload['password_hash'] = null;
                }
                $id = (int) $_POST['id'];
                $repo->update($id, $payload);
                record_audit($user['id'], 'atualizou_usuario', 'user', $id, ['perfil' => $payload['perfil']]);
                $message = 'Usuário atualizado!';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$users = $repo->all();
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Usuários - Painel KAVVI</title>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css'); ?>">
</head>
<body>
<header class="app-header">
    <div class="logo">Painel KAVVI</div>
    <nav>
        <a href="/admin/index.php">Dashboard</a>
        <a href="/admin/proposals.php">Propostas</a>
        <a href="/admin/contracts.php">Contratos</a>
        <a href="/auth/logout.php">Sair</a>
    </nav>
</header>
<main class="layout">
    <section class="dashboard">
        <div class="card">
            <h1><?= $editingUser ? 'Editar Usuário' : 'Novo Usuário'; ?></h1>
            <?php if ($message): ?>
                <div class="alert alert-success"><?= sanitize($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= sanitize($error); ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="_token" value="<?= sanitize($token); ?>">
                <input type="hidden" name="action" value="<?= $editingUser ? 'update' : 'create'; ?>">
                <?php if ($editingUser): ?>
                    <input type="hidden" name="id" value="<?= (int) $editingUser['id']; ?>">
                <?php endif; ?>
                <div class="grid">
                    <label>Nome<input type="text" name="nome" value="<?= sanitize($editingUser['nome'] ?? ''); ?>" required></label>
                    <label>E-mail<input type="email" name="email" value="<?= sanitize($editingUser['email'] ?? ''); ?>" required></label>
                    <label>Perfil
                        <select name="perfil">
                            <option value="admin" <?= (($editingUser['perfil'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option>
                            <option value="gestor" <?= (($editingUser['perfil'] ?? '') === 'gestor') ? 'selected' : ''; ?>>Gestor</option>
                            <option value="vendedor" <?= (($editingUser['perfil'] ?? 'vendedor') === 'vendedor') ? 'selected' : ''; ?>>Vendedor</option>
                        </select>
                    </label>
                    <label>Senha
                        <input type="password" name="password" <?= $editingUser ? '' : 'required'; ?> placeholder="<?= $editingUser ? 'Deixe em branco para manter' : 'Senha inicial'; ?>">
                    </label>
                    <label class="checkbox-inline">
                        <input type="checkbox" name="ativo" value="1" <?= (($editingUser['ativo'] ?? 1) ? 'checked' : ''); ?>> Ativo
                    </label>
                </div>
                <button type="submit" class="btn-primary">Salvar</button>
                <?php if ($editingUser): ?>
                    <a class="btn-link" href="/admin/users.php">Cancelar</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="card">
            <h2>Usuários cadastrados</h2>
            <table class="items-table">
                <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th>Último acesso</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($users as $item): ?>
                        <tr>
                            <td><?= sanitize($item['nome']); ?></td>
                            <td><?= sanitize($item['email']); ?></td>
                            <td><?= sanitize($item['perfil']); ?></td>
                            <td><?= $item['ativo'] ? 'Ativo' : 'Inativo'; ?></td>
                            <td><?= $item['last_login_at'] ? date('d/m/Y H:i', strtotime($item['last_login_at'])) : 'Nunca'; ?></td>
                            <td><a class="btn-link" href="?edit=<?= (int) $item['id']; ?>">Editar</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
