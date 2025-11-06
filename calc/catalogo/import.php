<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_auth(['admin', 'gestor']);

$companyId = current_company_id();
$user = current_user();
if ($companyId <= 0 && $user) {
    $companyId = (int) ($user['company_id'] ?? 0);
}

$messages = [];
$errors = [];
$preview = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['_token'] ?? '');

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Envie um arquivo CSV válido.';
    } else {
        $tmpName = $_FILES['file']['tmp_name'];
        $handle = fopen($tmpName, 'r');
        if ($handle === false) {
            $errors[] = 'Não foi possível ler o arquivo enviado.';
        } else {
            $header = fgetcsv($handle, 0, ',');
            if (!$header) {
                $errors[] = 'Arquivo vazio ou inválido.';
            } else {
                $expected = ['type','key_slug','label','unit_price','per_user','recurring','billing_cycle','max_discount_percent','active','sort_order'];
                $header = array_map('trim', $header);
                if ($header !== $expected) {
                    $errors[] = 'Cabeçalho inválido. Utilize o modelo disponível.';
                } else {
                    $repo = new \App\Repositories\ProductRepository();
                    while (($row = fgetcsv($handle, 0, ',')) !== false) {
                        if (count($row) < count($expected)) {
                            continue;
                        }
                        $data = array_combine($expected, array_map('trim', $row));
                        if (!$data) {
                            continue;
                        }
                        $data['unit_price'] = parse_decimal($data['unit_price']);
                        $data['per_user'] = (int) $data['per_user'];
                        $data['recurring'] = (int) $data['recurring'];
                        $data['max_discount_percent'] = (float) $data['max_discount_percent'];
                        $data['active'] = (int) $data['active'];
                        $data['sort_order'] = (int) $data['sort_order'];
                        $preview[] = $repo->upsert($companyId, $data);
                    }
                    $messages[] = count($preview) . ' itens importados/atualizados com sucesso.';
                }
            }
            fclose($handle);
        }
    }
}

$token = csrf_token();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Importar catálogo</title>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css'); ?>">
</head>
<body>
<header class="app-header">
    <div class="logo">Importar catálogo</div>
    <nav>
        <a href="<?= route('/catalogo/index.php'); ?>">Voltar</a>
        <a href="<?= route('/auth/logout.php'); ?>">Sair</a>
    </nav>
</header>
<main class="layout">
    <section class="dashboard">
        <div class="card">
            <h1>Importação de produtos</h1>
            <p>Baixe o modelo CSV em <a href="<?= asset('assets/csv/modelo_produtos.csv'); ?>" download>modelo_produtos.csv</a>.</p>
            <?php foreach ($messages as $msg): ?><div class="alert alert-success"><?= sanitize($msg); ?></div><?php endforeach; ?>
            <?php foreach ($errors as $msg): ?><div class="alert alert-error"><?= sanitize($msg); ?></div><?php endforeach; ?>
            <form method="post" enctype="multipart/form-data" class="grid">
                <input type="hidden" name="_token" value="<?= sanitize($token); ?>">
                <label>Arquivo CSV
                    <input type="file" name="file" accept=".csv" required>
                </label>
                <button type="submit" class="btn-primary">Importar</button>
            </form>
        </div>
        <?php if ($preview): ?>
            <div class="card">
                <h2>Itens processados</h2>
                <table class="items-table">
                    <thead><tr><th>Slug</th><th>Nome</th><th>Tipo</th><th>Preço</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($preview as $item): ?>
                        <tr>
                            <td><?= sanitize($item['key_slug']); ?></td>
                            <td><?= sanitize($item['label']); ?></td>
                            <td><?= sanitize($item['type']); ?></td>
                            <td><?= format_currency((float) $item['unit_price']); ?></td>
                            <td><?= $item['active'] ? 'Ativo' : 'Inativo'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
