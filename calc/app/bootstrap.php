<?php
declare(strict_types=1);

$appConfig = require __DIR__ . '/config.php';

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/repositories/UserRepository.php';
require_once __DIR__ . '/repositories/ClientRepository.php';
require_once __DIR__ . '/repositories/ProposalRepository.php';
require_once __DIR__ . '/repositories/ContractRepository.php';
require_once __DIR__ . '/services/ProposalService.php';
