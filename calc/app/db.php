<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if (!isset($appConfig)) {
    $appConfig = require __DIR__ . '/config.php';
}

function db(): PDO
{
    static $pdo = null;
    global $appConfig;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
        $appConfig['db']['host'],
        $appConfig['db']['name'],
        $appConfig['db']['charset']
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $appConfig['db']['user'], $appConfig['db']['pass'], $options);

    return $pdo;
}
