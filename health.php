<?php
declare(strict_types=1);

/**
 * Lightweight health check endpoint.
 * Returns JSON and a 200 (healthy) or 503 (unhealthy) status code.
 * Does not start a session and does not depend on the full bootstrap.
 */

require_once __DIR__ . '/config/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$status = 'ok';
$http = 200;
$database = 'ok';
$detail = null;

try {
    $configFile = __DIR__ . '/config/database.local.php';

    if (!file_exists($configFile)) {
        throw new RuntimeException('Database configuration missing.');
    }

    require_once $configFile;

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2
        ]
    );

    $pdo->query('SELECT 1');
} catch (Throwable $exception) {
    $status = 'error';
    $http = 503;
    $database = 'down';

    if (defined('APP_DEBUG') && APP_DEBUG) {
        $detail = $exception->getMessage();
    }
}

http_response_code($http);

echo json_encode(
    [
        'status' => $status,
        'database' => $database,
        'application' => defined('APP_NAME') ? APP_NAME : 'CampusFind',
        'time' => date('c'),
        'detail' => $detail
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);