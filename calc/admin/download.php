<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_auth(['admin', 'gestor', 'vendedor']);

$user = current_user();
$type = $_GET['type'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

$path = null;
$filename = null;

if ($type === 'contract') {
    $repo = new ContractRepository();
    $contract = $repo->findById($id);
    if (!$contract) {
        http_response_code(404);
        exit('Contrato não encontrado.');
    }
    $proposalRepo = new ProposalRepository();
    $proposal = $proposalRepo->findById((int) $contract['proposal_id']);
    if ($user['perfil'] === 'vendedor' && (int) $proposal['user_id'] !== (int) $user['id']) {
        http_response_code(403);
        exit('Acesso negado.');
    }
    $path = $contract['pdf_path'];
    $filename = 'contrato-' . $contract['proposal_id'] . '.html';
} elseif ($type === 'proposal') {
    $repo = new ProposalRepository();
    $proposal = $repo->findById($id);
    if (!$proposal) {
        http_response_code(404);
        exit('Proposta não encontrada.');
    }
    if ($user['perfil'] === 'vendedor' && (int) $proposal['user_id'] !== (int) $user['id']) {
        http_response_code(403);
        exit('Acesso negado.');
    }
    $path = $proposal['pdf_path'];
    $filename = 'proposta-' . $proposal['id'] . '.html';
} else {
    http_response_code(400);
    exit('Tipo inválido.');
}

if (!$path || !file_exists($path)) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
