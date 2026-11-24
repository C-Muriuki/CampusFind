<?php
declare(strict_types=1);

define('APP_NAME', 'CampusFind');

// Environment resolution. Resolution order:
//   1. CAMPUSFIND_ENV environment variable ('production' or 'development').
//   2. Auto-detect: any real (non-local) host is treated as production so a
//      live deploy never leaks stack traces or DB details to the browser;
//      localhost/127.0.0.1, CLI and the built-in dev server stay development.
// Debug is ON only for non-production.
$appEnv = getenv('CAMPUSFIND_ENV');
$appEnv = ($appEnv === false) ? '' : strtolower(trim($appEnv));

if ($appEnv === '') {
    $requestHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    $isLocalHost = (
        $requestHost === ''
        || $requestHost === 'localhost'
        || str_starts_with($requestHost, 'localhost:')
        || $requestHost === '127.0.0.1'
        || str_starts_with($requestHost, '127.0.0.1:')
        || $requestHost === '[::1]'
        || str_starts_with($requestHost, '[::1]:')
    );
    $appEnv = $isLocalHost ? 'development' : 'production';
}

define('APP_ENV', $appEnv === 'production' ? 'production' : 'development');
define('APP_DEBUG', APP_ENV !== 'production');

if (APP_ENV === 'production') {
    @ini_set('display_errors', '0');
    @ini_set('log_errors', '1');
    error_reporting(E_ALL);
} else {
    @ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// ---------------------------------------------------------------------------
// Base URL resolution.
//
// BASE_URL is the ORIGIN (scheme://host[:port]) the app is served from; every
// internal link, asset URL, redirect and canonical tag is built as
// BASE_URL . '/path'. Resolution order:
//
//   1. CAMPUSFIND_BASE_URL environment variable (e.g.
//      https://campusfind.zetech.ac.ke). Recommended in production and the only
//      way to deploy the app under a sub-path.
//   2. Auto-detect from the incoming request: scheme + Host header. Honours
//      X-Forwarded-Proto so it stays correct behind a TLS-terminating proxy.
//   3. Fallback http://localhost:8000 for CLI scripts (seeds, tests) where no
//      HTTP request exists.
//
// With the built-in dev server this yields http://localhost:8000 (unchanged),
// and on a real HTTPS domain it yields that domain automatically.
// ---------------------------------------------------------------------------
$baseUrl = getenv('CAMPUSFIND_BASE_URL');
$baseUrl = ($baseUrl === false) ? '' : trim($baseUrl);

if ($baseUrl === '') {
    $host = isset($_SERVER['HTTP_HOST'])
        ? trim((string) $_SERVER['HTTP_HOST'])
        : '';

    // Only trust HTTP_HOST when it is a bare host[:port]; this blocks
    // header-injection oddities and keeps the fallback predictable.
    if ($host !== '' && preg_match('#^[A-Za-z0-9._:\[\]-]+$#', $host)) {
        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || (
                isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
                && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
            )
        );

        $baseUrl = ($isHttps ? 'https' : 'http') . '://' . $host;
    } else {
        $baseUrl = 'http://localhost:8000';
    }
}

define('BASE_URL', rtrim($baseUrl, '/'));

define('APP_TIMEZONE', 'Africa/Nairobi');

date_default_timezone_set(APP_TIMEZONE);

define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('LOG_PATH', ROOT_PATH . '/logs');

define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);

define(
    'ALLOWED_IMAGE_TYPES',
    ['image/jpeg', 'image/png', 'image/webp']
);

define('SESSION_NAME', 'CAMPUSFIND_SESSION');

// Public support contact details (surfaced via the floating WhatsApp button).
define('SUPPORT_WHATSAPP', '254110550085');
define('SUPPORT_EMAIL', 'llinscohub@gmail.com');

// Outgoing mail. Uses PHP's built-in mail() (works with a local sendmail/MTA).
// Set MAIL_DRIVER to 'log' to only write emails to the error log (useful when
// no MTA is configured, e.g. local dev), or 'mail' to actually send.
define('MAIL_DRIVER', 'mail');
define('MAIL_FROM_EMAIL', 'no-reply@campusfind.local');
define('MAIL_FROM_NAME', 'CampusFind');