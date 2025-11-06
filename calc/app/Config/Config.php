<?php
declare(strict_types=1);

namespace App\Config;

final class Config
{
    private array $env = [];

    public function __construct(?string $envPath = null)
    {
        if ($envPath === null) {
            $envPath = dirname(__DIR__, 2) . '/.env';
        }

        if (is_file($envPath)) {
            $this->loadEnvFile($envPath);
        }
    }

    public function getEnv(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $this->env)) {
            return $this->env[$key];
        }

        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        return $value;
    }

    private function loadEnvFile(string $path): void
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if ($value !== '' && $value[0] === '"' && substr($value, -1) === '"') {
                $value = substr($value, 1, -1);
            }

            $this->env[$key] = $value;
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}
