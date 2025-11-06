<?php
// File: propostas/aceitar.php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Método não permitido']);
  exit;
}

$token = trim($_POST['token'] ?? '');
$name  = trim($_POST['name'] ?? '');
$doc   = preg_replace('/\D+/', '', $_POST['doc'] ?? '');
$email = trim($_POST['email'] ?? '');
$csrf  = $_POST['_token'] ?? ($_POST['_csrf'] ?? '');

try {
  verify_csrf_token($csrf);
} catch (Throwable $e) {
  http_response_code(419);
  echo json_encode(['error' => 'CSRF inválido']);
  exit;
}

if ($token === '' || $name === '' || $doc === '') {
  http_response_code(422);
  echo json_encode(['error' => 'Campos obrigatórios ausentes']);
  exit;
}

// 1) Carrega proposta por token
$propRepo = new ProposalRepository();
$proposal = $propRepo->findByShareToken($token);
if (!$proposal) {
  http_response_code(404);
  echo json_encode(['error' => 'Proposta não encontrada']);
  exit;
}

$proposalId = (int) $proposal['id'];

// 2) Grava aceite e marca como "aceita"
$pdo = db();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? 'n/a', 0, 255);

$hashInput  = $proposalId . '|' . $name . '|' . $doc . '|' . $ip . '|' . $ua . '|' . date('c');
$hashSecret = getenv('APP_SECRET') ?: 'kavvi-secret';
$acceptHash = hash_hmac('sha256', $hashInput, $hashSecret);

$pdo->beginTransaction();
try {
  // Tabela criada pelo patch SQL
  $ins = $pdo->prepare("
    INSERT INTO proposal_acceptances (proposal_id, acceptor_name, acceptor_doc, acceptor_email, ip, user_agent, acceptance_hash)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  ");
  $ins->execute([$proposalId, $name, $doc, ($email ?: null), $ip, $ua, $acceptHash]);

  // Atualiza status + accepted_at
  $upd = $pdo->prepare("UPDATE proposals SET status = 'aceita', accepted_at = NOW(), updated_at = NOW() WHERE id = ?");
  $upd->execute([$proposalId]);

  // 3) Gera contrato com ProposalService (do pacote Kodex)
  $userRepo = new UserRepository();
  $owner    = $userRepo->findById((int)$proposal['user_id']); // dono da proposta
  $userCtx  = $owner ?: ['id' => 0, 'perfil' => 'admin'];     // fallback admin

  $service  = new ProposalService($appConfig);
  $result   = $service->generateContract($proposalId, $userCtx); // retorna ['id'=>.., 'pdf_path'=>..]

  $pdo->commit();
  echo json_encode(['ok' => true, 'contract_path' => $result['pdf_path'] ?? null]);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['error' => 'Falha ao registrar aceite', 'detail' => $e->getMessage()]);
}
