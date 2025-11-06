<?php
declare(strict_types=1);

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__);
    $path = ltrim($path, '/');
    return $path !== '' ? $base . '/' . $path : $base;
}

function app_base_path(): string
{
    return defined('APP_BASE_PATH') ? rtrim(APP_BASE_PATH, '/') : '/calc';
}

function asset(string $path): string
{
    $base = app_base_path();
    return $base . '/' . ltrim($path, '/');
}

function route(string $path): string
{
    return asset(ltrim($path, '/'));
}

function current_company_id(): int
{
    $user = current_user();
    if ($user && isset($user['company_id'])) {
        return (int) $user['company_id'];
    }

    return (int) ($_SESSION['company_id'] ?? 0);
}

function load_company_finance_config(int $companyId, array $defaults = []): array
{
    if ($companyId <= 0) {
        return $defaults;
    }

    $path = base_path('storage/company_settings');
    ensure_dir($path);
    $file = $path . '/company_' . $companyId . '.json';

    if (!is_file($file)) {
        return $defaults;
    }

    $content = file_get_contents($file);
    if ($content === false) {
        return $defaults;
    }

    $data = json_decode($content, true);
    if (!is_array($data)) {
        return $defaults;
    }

    return array_merge($defaults, $data);
}

function save_company_finance_config(int $companyId, array $data): void
{
    if ($companyId <= 0) {
        return;
    }

    $path = base_path('storage/company_settings');
    ensure_dir($path);
    $file = $path . '/company_' . $companyId . '.json';
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function redirect(string $location, bool $absolute = false): void
{
    if (!$absolute && str_starts_with($location, '/')) {
        $location = route($location);
    }

    header('Location: ' . $location);
    exit;
}

function sanitize(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function format_currency(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function parse_decimal($value): float
{
    if (is_numeric($value)) {
        return (float) $value;
    }
    $value = str_replace(['R$', ' '], '', (string) $value);
    $value = str_replace(['.', ','], ['', '.'], $value);
    return (float) $value;
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function random_token(int $length = 40): string
{
    return bin2hex(random_bytes((int) ceil($length / 2)));
}

function slugify(string $text): string
{
    $text = preg_replace('~[\p{L}\d]+~u', ' $0 ', $text);
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }
    $text = preg_replace('~[^\w]+~', '-', $text);
    $text = strtolower(trim($text, '-'));
    return $text ?: 'documento';
}

function request_post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

function request_get(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

function old(string $key, $default = ''): string
{
    return sanitize($_POST[$key] ?? $default);
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
