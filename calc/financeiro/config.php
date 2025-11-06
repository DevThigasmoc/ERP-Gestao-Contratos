<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_auth(['admin', 'gestor']);

$companyId = current_company_id();
$user = current_user();
if ($companyId <= 0 && $user) {
    $companyId = (int) ($user['company_id'] ?? 0);
}

$current = load_company_finance_config($companyId, [
    'rate' => $appConfig['finance']['commission']['default_rate'],
    'splits' => $appConfig['finance']['commission']['splits'],
    'due_day' => $appConfig['finance']['commission']['due_day'],
    'pague_em_dia_default' => 0,
]);

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['_token'] ?? '');
    $data = [
        'rate' => (float) ($_POST['rate'] ?? $current['rate']),
        'splits' => (int) ($_POST['splits'] ?? $current['splits']),
        'due_day' => (int) ($_POST['due_day'] ?? $current['due_day']),
        'pague_em_dia_default' => (float) ($_POST['pague_em_dia_default'] ?? $current['pague_em_dia_default']),
    ];
    save_company_finance_config($companyId, $data);
    $current = array_merge($current, $data);
    $message = 'Configurações atualizadas.';
}

$token = csrf_token();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Configurações Financeiras</title>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css'); ?>">
</head>
<body>
<header class="app-header">
    <div class="logo">Configurações</div>
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
            <h1>Parâmetros da empresa</h1>
            <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message); ?></div><?php endif; ?>
            <form method="post" class="grid">
                <input type="hidden" name="_token" value="<?= sanitize($token); ?>">
                <label>Taxa de comissão padrão (%)
                    <input type="number" step="0.01" name="rate" value="<?= sanitize((string) $current['rate']); ?>">
                </label>
                <label>Número de parcelas
                    <input type="number" name="splits" value="<?= sanitize((string) $current['splits']); ?>" min="1" max="12">
                </label>
                <label>Dia de pagamento
                    <input type="number" name="due_day" value="<?= sanitize((string) $current['due_day']); ?>" min="1" max="28">
                </label>
                <label>Pague em Dia padrão (%)
                    <input type="number" step="0.01" name="pague_em_dia_default" value="<?= sanitize((string) $current['pague_em_dia_default']); ?>">
                </label>
                <button type="submit" class="btn-primary">Salvar</button>
            </form>
            <p class="muted">Esses valores são utilizados como padrão ao calcular comissões e reajustes. Ajustes específicos podem ser feitos diretamente na assinatura ou proposta.</p>
        </div>
    </section>
</main>
</body>
</html>
