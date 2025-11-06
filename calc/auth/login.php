<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$error = null;
$email = '';

if (is_authenticated()) {
    redirect('/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['_token'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';

    if (!login_attempts_allowed($ip)) {
        $error = 'Muitas tentativas de login. Aguarde alguns minutos e tente novamente.';
    } else {
        $repo = new UserRepository();
        $user = $repo->findByEmail($email);
        if (!$user || !$user['ativo']) {
            $error = 'Credenciais inválidas.';
            record_failed_login($ip);
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Credenciais inválidas.';
            record_failed_login($ip);
        } else {
            clear_login_attempts($ip);
            $repo->updateLastLogin((int) $user['id']);
            login_user($user);
            record_audit((int) $user['id'], 'login', 'user', (int) $user['id'], []);
            redirect('/index.php');
        }
    }
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Entrar - KAVVI Calculadora</title>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css'); ?>">
</head>
<body class="auth-bg">
    <div class="auth-container">
        <div class="auth-logo">KAVVI</div>
        <h1>Acessar</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= sanitize($error); ?></div>
        <?php endif; ?>
        <form method="post" class="auth-form" autocomplete="off">
            <input type="hidden" name="_token" value="<?= sanitize($token); ?>">
            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" name="email" id="email" value="<?= sanitize($email); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" name="password" id="password" required>
            </div>
            <button type="submit" class="btn-primary">Entrar</button>
        </form>
    </div>
</body>
</html>
