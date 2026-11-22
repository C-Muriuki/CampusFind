<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

$query  = $_SERVER['QUERY_STRING'] ?? '';
$target = BASE_URL . '/auth/forgot-password.php';
if ($query !== '') {
    $target .= '?' . $query;
}

header('Location: ' . $target, true, 302);
exit;