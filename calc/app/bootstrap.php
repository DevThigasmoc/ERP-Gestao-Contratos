<?php
declare(strict_types=1);

$appConfig = require __DIR__ . '/config.php';

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $relative = str_replace('App\\', '', $class);
        $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
        $file = __DIR__ . '/' . $relative . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/repositories/UserRepository.php';
require_once __DIR__ . '/repositories/ClientRepository.php';
require_once __DIR__ . '/repositories/ProposalRepository.php';
require_once __DIR__ . '/repositories/ContractRepository.php';
require_once __DIR__ . '/services/ProposalService.php';
