<?php
declare(strict_types=1);

require_once __DIR__ . '/Config/Config.php';

use App\Config\Config;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

date_default_timezone_set('America/Sao_Paulo');

$configLoader = new Config(BASE_PATH . '/.env');

$appConfig = [
    'name' => 'KAVVI Calculadora',
    'session_name' => 'kavvi_session',
    'db' => [
        'host' => 'localhost',
        'name' => 'kavvi',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'storage_path' => BASE_PATH . '/storage',
    'proposal_storage' => BASE_PATH . '/storage/propostas_pdf',
    'contract_storage' => BASE_PATH . '/storage/contratos_pdf',
    'login_attempt_window' => 900,
    'login_attempt_limit' => 5,
    'csrf_token_key' => '_csrf_token',
];

$localConfigPath = __DIR__ . '/config.local.php';
if (file_exists($localConfigPath)) {
    $local = include $localConfigPath;
    if (is_array($local)) {
        $appConfig = array_replace_recursive($appConfig, $local);
    }
}

$appConfig['db']['host'] = $configLoader->getEnv('DB_HOST', $appConfig['db']['host']);
$appConfig['db']['name'] = $configLoader->getEnv('DB_NAME', $appConfig['db']['name']);
$appConfig['db']['user'] = $configLoader->getEnv('DB_USER', $appConfig['db']['user']);
$appConfig['db']['pass'] = $configLoader->getEnv('DB_PASS', $appConfig['db']['pass']);
$appConfig['db']['charset'] = $configLoader->getEnv('DB_CHARSET', $appConfig['db']['charset']);

$appConfig['app_base_path'] = rtrim($configLoader->getEnv('APP_BASE_PATH', '/calc'), '/');

if (!defined('APP_BASE_PATH')) {
    define('APP_BASE_PATH', $appConfig['app_base_path'] ?: '/calc');
}

$appConfig['finance'] = [
    'efi' => [
        'client_id' => $configLoader->getEnv('EFI_CLIENT_ID', ''),
        'client_secret' => $configLoader->getEnv('EFI_CLIENT_SECRET', ''),
        'sandbox' => filter_var($configLoader->getEnv('EFI_SANDBOX', 'true'), FILTER_VALIDATE_BOOL),
        'cert_path' => $configLoader->getEnv('EFI_CERT_PATH'),
        'cert_pass' => $configLoader->getEnv('EFI_CERT_PASS'),
        'pix_key' => $configLoader->getEnv('EFI_PIX_KEY', ''),
    ],
    'commission' => [
        'default_rate' => (float) $configLoader->getEnv('DEFAULT_COMMISSION_RATE', '0.10'),
        'splits' => (int) $configLoader->getEnv('COMMISSION_SPLITS', '6'),
        'due_day' => (int) $configLoader->getEnv('COMMISSION_DUE_DAY', '5'),
    ],
    'webhook_secret' => $configLoader->getEnv('WEBHOOK_SHARED_SECRET', ''),
];

$appConfig['plans'] = [
    'kavvi_start' => [
        'label' => 'KAVVI Start',
        'base_price' => 149.90,
        'description' => 'Plano ideal para pequenos negócios com até 5 usuários.',
    ],
    'kavvi_pro' => [
        'label' => 'KAVVI Pro',
        'base_price' => 229.90,
        'description' => 'Plano completo para equipes em crescimento.',
    ],
    'kavvi_enterprise' => [
        'label' => 'KAVVI Enterprise',
        'base_price' => 349.90,
        'description' => 'Plano corporativo com recursos avançados.',
    ],
];

$appConfig['addons'] = [
    [
        'key' => 'impressora_termica',
        'label' => 'Impressora Térmica',
        'unit_price' => 850.00,
        'max_discount' => 0.15,
    ],
    [
        'key' => 'leitor_codigo',
        'label' => 'Leitor de Código de Barras',
        'unit_price' => 420.00,
        'max_discount' => 0.10,
    ],
    [
        'key' => 'gaveta',
        'label' => 'Gaveta Automática',
        'unit_price' => 310.00,
        'max_discount' => 0.10,
    ],
    [
        'key' => 'monitor',
        'label' => 'Monitor Touch 15"',
        'unit_price' => 1890.00,
        'max_discount' => 0.12,
    ],
];

foreach (['storage_path', 'proposal_storage', 'contract_storage'] as $dirKey) {
    if (!is_dir($appConfig[$dirKey])) {
        @mkdir($appConfig[$dirKey], 0775, true);
    }
}

return $appConfig;
