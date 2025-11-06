<?php
declare(strict_types=1);

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__);
    return rtrim($base . '/' . ltrim($path, '/'), '/');
}

function asset(string $path): string
{
    return '/' . ltrim($path, '/');
}

function redirect(string $location): void
{
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
