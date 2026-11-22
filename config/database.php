<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

$localConfig = __DIR__ . '/database.local.php';

if (!file_exists($localConfig)) {
    throw new RuntimeException(
        'Database configuration missing. Copy database.example.php to database.local.php.'
    );
}

require_once $localConfig;

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    DB_HOST,
    DB_PORT,
    DB_NAME,
    DB_CHARSET
);

try {
    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false
        ]
    );

} catch (PDOException $exception) {

    error_log(
        '[CampusFind database] ' . $exception->getMessage()
    );

    http_response_code(500);

    if (APP_DEBUG) {
        exit('Database connection failed. Check your local database settings.');
    }

    exit('CampusFind is temporarily unavailable.');
}