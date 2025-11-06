<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

$appConfig = $appConfig ?? require __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name($appConfig['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    global $appConfig;
    if (empty($_SESSION[$appConfig['csrf_token_key']])) {
        $_SESSION[$appConfig['csrf_token_key']] = bin2hex(random_bytes(32));
    }
    return $_SESSION[$appConfig['csrf_token_key']];
}

function verify_csrf_token(string $token): void
{
    global $appConfig;
    $valid = hash_equals($_SESSION[$appConfig['csrf_token_key']] ?? '', $token);
    if (!$valid) {
        http_response_code(419);
        exit('Token CSRF inválido. Atualize a página e tente novamente.');
    }
}

function current_user(): ?array
{
    return $_SESSION['auth_user'] ?? null;
}

function is_authenticated(): bool
{
    return current_user() !== null;
}

function require_auth(?array $roles = null): void
{
    if (!is_authenticated()) {
        redirect('/auth/login.php');
    }

    if ($roles !== null) {
        $user = current_user();
        if ($user === null || !in_array($user['perfil'], $roles, true)) {
            http_response_code(403);
            exit('Acesso negado.');
        }
    }
}

function login_user(array $user): void
{
    $_SESSION['auth_user'] = $user;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function record_audit(int $userId, string $action, string $entity, ?int $entityId, array $meta = []): void
{
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity, entity_id, meta_json, created_at) VALUES (:user_id, :action, :entity, :entity_id, :meta_json, NOW())');
    $stmt->execute([
        ':user_id' => $userId,
        ':action' => $action,
        ':entity' => $entity,
        ':entity_id' => $entityId,
        ':meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
    ]);
}

function login_attempts_allowed(string $ip): bool
{
    global $appConfig;
    $window = (int) $appConfig['login_attempt_window'];
    $limit = (int) $appConfig['login_attempt_limit'];
    $runtimeDir = base_path('storage/runtime/login_attempts');
    ensure_dir($runtimeDir);

    $file = $runtimeDir . '/' . md5($ip) . '.json';
    $attempts = [];
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if ($content !== false) {
            $attempts = json_decode($content, true) ?: [];
        }
    }

    $now = time();
    $attempts = array_filter($attempts, static function ($timestamp) use ($now, $window) {
        return ($now - (int) $timestamp) < $window;
    });

    return count($attempts) < $limit;
}

function record_failed_login(string $ip): void
{
    $runtimeDir = base_path('storage/runtime/login_attempts');
    $file = $runtimeDir . '/' . md5($ip) . '.json';
    $attempts = [];
    if (file_exists($file)) {
        $attempts = json_decode((string) file_get_contents($file), true) ?: [];
    }
    $attempts[] = time();
    file_put_contents($file, json_encode(array_values($attempts)));
}

function clear_login_attempts(string $ip): void
{
    $runtimeDir = base_path('storage/runtime/login_attempts');
    $file = $runtimeDir . '/' . md5($ip) . '.json';
    if (file_exists($file)) {
        unlink($file);
    }
}
