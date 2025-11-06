<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_auth(['admin', 'gestor']);

$companyId = current_company_id();
$user = current_user();
if ($companyId <= 0 && $user) {
    $companyId = (int) ($user['company_id'] ?? 0);
}

$repo = new \App\Repositories\ProductRepository();
$slug = $_GET['slug'] ?? '';
$product = $slug ? $repo->findBySlug($companyId, $slug) : null;
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['_token'] ?? '');

    $data = [
        'type' => $_POST['type'] ?? 'plan',
        'key_slug' => $product['key_slug'] ?? slugify($_POST['key_slug'] ?? ''),
        'label' => trim($_POST['label'] ?? ''),
        'unit_price' => parse_decimal($_POST['unit_price'] ?? '0'),
        'per_user' => isset($_POST['per_user']) ? 1 : 0,
        'recurring' => isset($_POST['recurring']) ? 1 : 0,
        'billing_cycle' => $_POST['billing_cycle'] ?? 'monthly',
        'max_discount_percent' => (float) ($_POST['max_discount_percent'] ?? 0),
        'active' => isset($_POST['active']) ? 1 : 0,
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
    ];

    if ($data['label'] === '') {
        $error = 'Informe o nome do produto.';
    } else {
        if (!$product) {
            $data['key_slug'] = slugify($_POST['key_slug'] ?: $data['label']);
        }

        $product = $repo->upsert($companyId, $data);
        $slug = $product['key_slug'];
        $message = 'Produto salvo com sucesso.';
    }
}

$token = csrf_token();
$types = ['plan' => 'Plano', 'addon' => 'Add-on', 'service' => 'Serviço', 'setup' => 'Implantação'];
$cycles = ['monthly' => 'Mensal', 'one_time' => 'Único'];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title><?= $product ? 'Editar' : 'Novo'; ?> produto - Catálogo</title>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css'); ?>">
</head>
<body>
<header class="app-header">
    <div class="logo">Catálogo</div>
    <nav>
        <a href="<?= route('/catalogo/index.php'); ?>">Voltar</a>
        <a href="<?= route('/auth/logout.php'); ?>">Sair</a>
    </nav>
</header>
<main class="layout">
    <section class="dashboard">
        <div class="card">
            <h1><?= $product ? 'Editar produto' : 'Novo produto'; ?></h1>
            <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= sanitize($error); ?></div><?php endif; ?>
            <form method="post" class="grid">
                <input type="hidden" name="_token" value="<?= sanitize($token); ?>">
                <label>Tipo
                    <select name="type">
                        <?php foreach ($types as $key => $label): ?>
                            <option value="<?= $key; ?>" <?= (($product['type'] ?? '') === $key) ? 'selected' : ''; ?>><?= $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Slug
                    <input type="text" name="key_slug" value="<?= sanitize($product['key_slug'] ?? ''); ?>" <?= $product ? 'readonly' : 'required'; ?>>
                </label>
                <label>Nome do item
                    <input type="text" name="label" value="<?= sanitize($product['label'] ?? ''); ?>" required>
                </label>
                <label>Preço unitário
                    <input type="text" name="unit_price" value="<?= sanitize((string) ($product['unit_price'] ?? '0')); ?>" required>
                </label>
                <label>Desconto máximo (%)
                    <input type="number" step="0.01" name="max_discount_percent" value="<?= sanitize((string) ($product['max_discount_percent'] ?? '0')); ?>">
                </label>
                <label>Ordem
                    <input type="number" name="sort_order" value="<?= sanitize((string) ($product['sort_order'] ?? '0')); ?>">
                </label>
                <label>Recorrência
                    <select name="billing_cycle">
                        <?php foreach ($cycles as $key => $label): ?>
                            <option value="<?= $key; ?>" <?= (($product['billing_cycle'] ?? '') === $key) ? 'selected' : ''; ?>><?= $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="checkbox-inline">
                    <input type="checkbox" name="per_user" value="1" <?= (($product['per_user'] ?? 0) ? 'checked' : ''); ?>> Cobrança por usuário
                </label>
                <label class="checkbox-inline">
                    <input type="checkbox" name="recurring" value="1" <?= (($product['recurring'] ?? 0) ? 'checked' : ''); ?>> Recorrente
                </label>
                <label class="checkbox-inline">
                    <input type="checkbox" name="active" value="1" <?= (($product['active'] ?? 1) ? 'checked' : ''); ?>> Ativo
                </label>
                <button type="submit" class="btn-primary">Salvar</button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
