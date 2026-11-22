<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function sanitizeInput(mixed $value): string
{
    if (!is_string($value)) {
        return '';
    }

    return trim($value);
}

function validateEmail(string $email): bool
{
    return filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    ) !== false;
}

/**
 * Interpret a submitted consent value (checkbox) as a boolean.
 * Accepts the usual checkbox encodings so the check is robust.
 */
function consentGiven(mixed $value): bool
{
    if ($value === true || $value === 1) {
        return true;
    }

    if (is_string($value)) {
        $normalised = strtolower(trim($value));

        return in_array($normalised, ['1', 'on', 'true', 'yes'], true);
    }

    return false;
}

function redirect(string $path): never
{
    header(
        'Location: ' .
        BASE_URL . '/' . ltrim($path, '/')
    );

    exit;
}

/**
 * Stage 5 hardening: send standard security headers.
 * Safe to call in CLI (header() is a no-op there).
 */
function sendSecurityHeaders(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Cross-Origin-Opener-Policy: same-origin');

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    $csp = "default-src 'self'; "
        . "base-uri 'self'; "
        . "object-src 'none'; "
        . "frame-ancestors 'self'; "
        . "img-src 'self' data:; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; "
        . "script-src 'self'; "
        . "form-action 'self'; "
        . "connect-src 'self'";

    header('Content-Security-Policy: ' . $csp);
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(
            random_bytes(32)
        );
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrf(?string $submitted = null): bool
{
    if ($submitted === null) {
        $submitted = $_POST['csrf_token'] ?? '';
    }

    $stored = $_SESSION['csrf_token'] ?? '';

    return (
        is_string($submitted) &&
        is_string($stored) &&
        $stored !== '' &&
        hash_equals($stored, $submitted)
    );
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function currentUserId(): ?int
{
    return isLoggedIn()
        ? (int) $_SESSION['user_id']
        : null;
}

function logActivity(
    string $action,
    string $details = ''
): void {
    $entry = [
        'time' => date('Y-m-d H:i:s'),
        'user_id' => currentUserId(),
        'action' => $action,
        'details' => $details,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];

    $line = json_encode(
        $entry,
        JSON_UNESCAPED_SLASHES
    );

    if ($line !== false) {
        file_put_contents(
            LOG_PATH . '/activity.log',
            $line . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}

function parseForm(
    array $data,
    array $requiredFields,
    array $maxLengths = []
): array {
    $values = [];
    $errors = [];

    foreach ($data as $key => $value) {
        if (!is_string($key)) {
            continue;
        }

        $values[$key] = sanitizeInput($value);
    }

    foreach ($requiredFields as $field) {
        if (
            !isset($values[$field]) ||
            $values[$field] === ''
        ) {
            $errors[$field] = ucfirst(
                str_replace('_', ' ', $field)
            ) . ' is required.';
        }
    }

    foreach ($maxLengths as $field => $limit) {
        if (
            isset($values[$field]) &&
            strlen($values[$field]) > $limit
        ) {
            $errors[$field] = ucfirst($field)
                . ' exceeds the maximum length.';
        }
    }

    return [
        'values' => $values,
        'errors' => $errors,
        'valid' => count($errors) === 0
    ];
}

function formatDate(?string $date): string
{
    if (!$date) {
        return 'Not available';
    }

    return date('d M Y', strtotime($date));
}

function currentPage(): string
{
    return basename(
        $_SERVER['PHP_SELF'] ?? 'index.php'
    );
}

/**
 * Stage 3 helpers: database access, current user, flash/old input,
 * database-backed activity logging and remember-me cookie handling.
 */

function db(): ?PDO
{
    global $pdo;

    return $pdo instanceof PDO ? $pdo : null;
}

function clientIp(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function userAgentString(): string
{
    return substr(
        (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        0,
        255
    );
}

function fetchUserById(int $id): ?array
{
    $pdo = db();

    if (!$pdo) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function fetchUserByEmail(string $email): ?array
{
    $pdo = db();

    if (!$pdo) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function currentUser(): ?array
{
    static $cache = null;
    static $cacheId = null;

    $id = currentUserId();

    if ($id === null) {
        return null;
    }

    if ($cache !== null && $cacheId === $id) {
        return $cache;
    }

    $cache = fetchUserById($id);
    $cacheId = $id;

    return $cache;
}

/**
 * Re-check the current account status against the database.
 * Used by the access guards so that suspending an account takes effect
 * immediately instead of waiting for the user's next login.
 */
function accountIsActive(): bool
{
    static $status = null;
    static $checkedId = null;

    $id = currentUserId();

    if ($id === null) {
        return false;
    }

    if ($checkedId !== $id) {
        $status = null;
        $checkedId = $id;
    }

    if ($status === null) {
        $user = fetchUserById($id);
        $status = $user !== null && $user['account_status'] === 'active';
    }

    return $status;
}

function flash(string $key, mixed $message = null): mixed
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;

        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);

    return $value;
}

function old(string $key, string $default = ''): string
{
    return isset($_SESSION['_form_old'][$key])
        ? (string) $_SESSION['_form_old'][$key]
        : $default;
}

function logActivityDb(
    string $action,
    string $description = ''
): void {
    // Always mirror to the private file log (spec: "write login/activity
    // events to a log file") in addition to the database audit table.
    logActivity($action, $description);

    $pdo = db();

    if (!$pdo) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs
                (user_id, action, description, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            currentUserId(),
            $action,
            $description !== '' ? $description : null,
            clientIp(),
            userAgentString()
        ]);
    } catch (Throwable $exception) {
        error_log('[CampusFind activity] ' . $exception->getMessage());
    }
}

function applyLoginSession(
    int $userId,
    string $role,
    string $fullName,
    string $email,
    bool $regenerate = true,
    string $accountStatus = 'active'
): void {
    if ($regenerate && session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id']        = $userId;
    $_SESSION['role']           = $role;
    $_SESSION['full_name']      = $fullName;
    $_SESSION['email']          = $email;
    $_SESSION['account_status'] = $accountStatus;
}

function rememberCookieName(): string
{
    return 'campusfind_remember';
}

function createRememberToken(int $userId): void
{
    $pdo = db();

    if (!$pdo) {
        return;
    }

    $selector  = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $validator);
    $expires   = (new DateTime('+30 days'))->format('Y-m-d H:i:s');

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO remember_tokens
                (user_id, selector, token_hash, expires_at)
             VALUES (?, ?, ?, ?)'
        );

        $stmt->execute([
            $userId,
            $selector,
            $tokenHash,
            $expires
        ]);
    } catch (Throwable $exception) {
        error_log('[CampusFind remember] ' . $exception->getMessage());

        return;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    setcookie(
        rememberCookieName(),
        $selector . ':' . $validator,
        [
            'expires'  => time() + (30 * 24 * 60 * 60),
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );

    $_COOKIE[rememberCookieName()] = $selector . ':' . $validator;
}

function clearRememberToken(): void
{
    $pdo    = db();
    $cookie = $_COOKIE[rememberCookieName()] ?? '';

    if ($cookie !== '' && str_contains($cookie, ':') && $pdo) {
        $selector = explode(':', $cookie, 2)[0];

        try {
            $stmt = $pdo->prepare(
                'DELETE FROM remember_tokens WHERE selector = ?'
            );

            $stmt->execute([$selector]);
        } catch (Throwable $exception) {
            error_log('[CampusFind remember clear] ' . $exception->getMessage());
        }
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    setcookie(
        rememberCookieName(),
        '',
        [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );

    unset($_COOKIE[rememberCookieName()]);
}

function loginFromRememberCookie(): void
{
    if (isLoggedIn()) {
        return;
    }

    $cookie = $_COOKIE[rememberCookieName()] ?? '';

    if ($cookie === '') {
        return;
    }

    if (!str_contains($cookie, ':')) {
        clearRememberToken();

        return;
    }

    [$selector, $validator] = explode(':', $cookie, 2);

    if ($selector === '' || $validator === '') {
        clearRememberToken();

        return;
    }

    $pdo = db();

    if (!$pdo) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT rt.user_id, rt.token_hash,
                    u.role, u.full_name, u.email, u.account_status
             FROM remember_tokens rt
             JOIN users u ON u.id = rt.user_id
             WHERE rt.selector = ?
               AND rt.expires_at > NOW()
               AND u.account_status = 'active'
             LIMIT 1"
        );

        $stmt->execute([$selector]);
        $row = $stmt->fetch();
    } catch (Throwable $exception) {
        error_log('[CampusFind remember login] ' . $exception->getMessage());

        return;
    }

    if ($row === false) {
        clearRememberToken();

        return;
    }

    if (!hash_equals((string) $row['token_hash'], hash('sha256', $validator))) {
        clearRememberToken();

        return;
    }

    applyLoginSession(
        (int) $row['user_id'],
        (string) $row['role'],
        (string) $row['full_name'],
        (string) $row['email'],
        true,
        (string) ($row['account_status'] ?? 'active')
    );

    logActivityDb('remember_login', 'Logged in via remember-me token');
}

/**
 * Stage 4: create a notification for a user. Optionally links it to a report.
 * Failures are logged but never bubble up to the caller.
 */
function createNotification(
    int $userId,
    string $title,
    string $message,
    string $type = 'general',
    ?int $reportId = null
): void {
    $pdo = db();

    if (!$pdo) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO notifications
                (user_id, title, message, notification_type, related_report_id)
             VALUES (?, ?, ?, ?, ?)'
        );

        $stmt->execute([$userId, $title, $message, $type, $reportId]);
    } catch (Throwable $exception) {
        error_log('[CampusFind notification] ' . $exception->getMessage());
    }
}

/**
 * Stage 6: UTF-8 safe string length.
 * Used instead of mb_strlen() which is unavailable (no mbstring extension).
 */
function cf_text_length(string $string): int
{
    if (preg_match_all('/./us', $string, $matches) === false) {
        return strlen($string);
    }

    return count($matches[0]);
}

/**
 * Stage 6: UTF-8 safe truncation that appends an ellipsis when the string
 * exceeds $maxChars characters.
 * Used instead of mb_strimwidth() which is unavailable (no mbstring extension).
 */
function cf_strimwidth(string $string, int $maxChars = 80, string $ellipsis = '...'): string
{
    if (cf_text_length($string) <= $maxChars) {
        return $string;
    }

    $budget = max(0, $maxChars - cf_text_length($ellipsis));
    $chars = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);

    if ($chars === false) {
        return $ellipsis;
    }

    return implode('', array_slice($chars, 0, $budget)) . $ellipsis;
}

/**
 * Prompt 13: Cache-Control policy helper.
 *
 * Sends Cache-Control and CDN-Cache-Control headers appropriate to the
 * page sensitivity. Defaults to private/no-store in bootstrap so that
 * authenticated HTML and CSRF forms are never shared-cached. Public,
 * anonymous-safe pages may opt into short public caching.
 */
function setCachePolicy(bool $public = false, int $maxAge = 60): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    if ($public) {
        header('Cache-Control: public, max-age=' . $maxAge . ', must-revalidate');
        header('CDN-Cache-Control: public, max-age=' . $maxAge);
    } else {
        header('Cache-Control: private, no-store, max-age=0, must-revalidate');
        header('CDN-Cache-Control: private, no-store');
    }
}

/**
 * Send a plain-text email.
 *
 * Honours the MAIL_DRIVER setting from config/app.php:
 *   - 'log'  : writes the message to the PHP error log instead of sending
 *              (safe default for local/dev boxes with no MTA).
 *   - 'mail' : sends via PHP's built-in mail() (requires a configured MTA).
 *
 * Returns true when the message was accepted (sent or logged), false on a
 * hard failure. Header-injection is prevented by rejecting newlines in
 * address/header fields.
 */
function sendMail(string $to, string $subject, string $body, string $replyTo = ''): bool
{
    $driver = getenv('CAMPUSFIND_MAIL_DRIVER') ?: (defined('MAIL_DRIVER') ? MAIL_DRIVER : 'log');

    // Guard against header injection in any address field.
    $clean = static function (string $v): ?string {
        if (preg_match('/[\r\n]/', $v)) {
            return null;
        }

        return $v;
    };

    $toClean = $clean($to);
    if ($toClean === null || $toClean === '') {
        return false;
    }

    $fromEmail = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'no-reply@campusfind.local';
    $fromName  = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'CampusFind';

    if ($driver === 'log') {
        error_log(
            '[CampusFind mail:LOG] To: ' . $toClean
            . ' | Subject: ' . $subject
            . ' | Body: ' . str_replace(["\r", "\n"], ' ', $body)
        );

        return true;
    }

    $headers = [];
    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';

    $replyClean = $replyTo !== '' ? $clean($replyTo) : null;
    if ($replyClean !== null && $replyClean !== '') {
        $headers[] = 'Reply-To: ' . $replyClean;
    }

    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'MIME-Version: 1.0';

    $ok = @mail($toClean, $subject, $body, implode("\r\n", $headers));

    if (!$ok) {
        error_log('[CampusFind mail] mail() failed for ' . $toClean);
    }

    return $ok;
}

/**
 * Build the plain-text email delivered to the support inbox when a visitor
 * submits the public Contact Us form. The visitor's address is used as
 * Reply-To so the team can answer straight from their mail client, while the
 * admin dashboard remains the canonical place to respond.
 */
function buildContactNotificationEmail(
    string $name,
    string $email,
    string $subject,
    string $message,
    ?int $reference = null
): string {
    $site = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

    $lines = [
        'New CampusFind contact message',
        '================================',
        '',
    ];

    if ($reference !== null) {
        $lines[] = 'Reference: #' . $reference;
    }

    $lines[] = 'From:     ' . $name . ' <' . $email . '>';
    $lines[] = 'Subject:  ' . $subject;
    $lines[] = 'Received: ' . date('d M Y, H:i');
    $lines[] = '';
    $lines[] = 'Message';
    $lines[] = '-------';
    $lines[] = $message;
    $lines[] = '';
    $lines[] = '------------------------------------------------------------';
    $lines[] = 'Reply to this email to respond directly to ' . $name . ',';
    $lines[] = 'or answer from the admin dashboard:';

    if ($site !== '') {
        $lines[] = $site . '/admin/contact-submissions.php';
    }

    return implode("\n", $lines);
}

/**
 * Build the plain-text reply email delivered to a user when an administrator
 * answers their contact message from the dashboard.
 */
function buildContactReplyEmail(
    string $userName,
    string $subject,
    string $response,
    ?int $reference = null
): string {
    $greeting = $userName !== '' ? $userName : 'there';

    $lines = [
        'Hi ' . $greeting . ',',
        '',
        'Thank you for contacting CampusFind. The team has replied to your message:',
        '',
        'Your subject: ' . $subject,
    ];

    if ($reference !== null) {
        $lines[] = 'Reference: #' . $reference;
    }

    $lines[] = '';
    $lines[] = 'Reply from the CampusFind team';
    $lines[] = '------------------------------------------------------------';
    $lines[] = $response;
    $lines[] = '------------------------------------------------------------';
    $lines[] = '';
    $lines[] = 'If you need to follow up, simply reply to this email or send';
    $lines[] = 'another message from the Contact Us page.';
    $lines[] = '';
    $lines[] = '- The CampusFind Team';

    return implode("\n", $lines);
}