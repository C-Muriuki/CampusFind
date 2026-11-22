<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

$isHttps = (
    isset($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== 'off'
);

ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_trans_sid', '0');

session_name(SESSION_NAME);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once INCLUDES_PATH . '/functions.php';

sendSecurityHeaders();

setCachePolicy(false);

require_once INCLUDES_PATH . '/seo.php';
require_once INCLUDES_PATH . '/icons.php';
require_once INCLUDES_PATH . '/charts.php';

require_once CONFIG_PATH . '/database.php';

$theme = $_COOKIE['campusfind_theme'] ?? 'light';

if (!in_array($theme, ['light', 'dark'], true)) {
    $theme = 'light';
}

define('CURRENT_THEME', $theme);

// Stage 3: restore a logged-in session from a valid remember-me cookie.
loginFromRememberCookie();