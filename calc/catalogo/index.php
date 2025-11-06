<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_auth(['admin', 'gestor', 'vendedor']);

$user = current_user();
$companyId = current_company_id();
if ($companyId <= 0 && $user) {
    $companyId = (int) ($user['company_id'] ?? 0);
}

$repo = new \App\Repositories\ProductRepository();
$type = $_GET['type'] ?? '';
$products = $repo->allActiveByCompany($companyId);

if ($type !== '') {
    $products = array_values(array_filter($products, static fn(array $product) => $product['type'] === $type));
}

$types = [
    '' => 'Todos',
    'plan' => 'Planos',
    'addon' => 'Add-ons',
    'service' => 'Serviços',
    'setup' => 'Implantação',
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Catálogo - KAVVI</title>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css'); ?>">
</head>
<body>
<header class="app-header">
    <div class="logo">Catálogo</div>
    <nav>
        <a href="<?= route('/index.php'); ?>">Calculadora</a>
        <a href="<?= route('/catalogo/index.php'); ?>">Catálogo</a>
        <?php if (in_array($user['perfil'], ['admin', 'gestor'], true)): ?>
            <a href="<?= route('/catalogo/form.php'); ?>">Novo item</a>
            <a href="<?= route('/catalogo/import.php'); ?>">Importar CSV</a>
        <?php endif; ?>
        <a href="<?= route('/auth/logout.php'); ?>">Sair</a>
    </nav>
</header>
<main class="layout">
    <section class="dashboard">
        <div class="card">
            <h1>Produtos da empresa</h1>
            <form method="get" class="filters">
                <label>Tipo
                    <select name="type">
                        <?php foreach ($types as $key => $label): ?>
                            <option value="<?= $key; ?>" <?= ($type === $key) ? 'selected' : ''; ?>><?= $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="btn-secondary">Filtrar</button>
            </form>
            <table class="items-table">
                <thead>
                <tr>
                    <th>Chave</th>
                    <th>Descrição</th>
                    <th>Preço</th>
                    <th>Cobrança</th>
                    <th>Máx. Desconto</th>
                    <th>Status</th>
                    <?php if (in_array($user['perfil'], ['admin', 'gestor'], true)): ?>
                        <th></th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= sanitize($product['key_slug']); ?></td>
                        <td><?= sanitize($product['label']); ?></td>
                        <td><?= format_currency((float) $product['unit_price']); ?><?= $product['per_user'] ? ' / usuário' : ''; ?></td>
                        <td><?= $product['recurring'] ? 'Recorrente' : 'Único'; ?></td>
                        <td><?= number_format((float) $product['max_discount_percent'], 2, ',', '.'); ?>%</td>
                        <td><?= $product['active'] ? 'Ativo' : 'Inativo'; ?></td>
                        <?php if (in_array($user['perfil'], ['admin', 'gestor'], true)): ?>
                            <td><a class="btn-link" href="<?= route('/catalogo/form.php'); ?>?slug=<?= urlencode($product['key_slug']); ?>">Editar</a></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
