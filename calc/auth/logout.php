<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (is_authenticated()) {
    $user = current_user();
    record_audit($user['id'], 'logout', 'user', $user['id'], []);
}

logout_user();
redirect('/auth/login.php');
