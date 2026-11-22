<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Stage 3.2: authentication logic and access guards.
 */

function requireLogin(): void
{
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] =
            $_SERVER['REQUEST_URI'] ?? '';

        redirect('auth/login.php');
    }
}

function requireRole(string $requiredRole): void
{
    requireLogin();

    $userRole = $_SESSION['role'] ?? '';

    if ($userRole !== $requiredRole) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function requireStudent(): void
{
    requireRole('student');
}

function requireAdmin(): void
{
    requireRole('admin');
}

/**
 * Validate registration input and create a new student account.
 * Returns ['ok' => bool, 'errors' => array, 'user_id' => ?int].
 */
function registerUser(
    string $fullName,
    string $email,
    string $password,
    string $confirmPassword,
    ?string $studentNumber,
    ?string $phone
): array {
    $errors = [];

    $fullName      = sanitizeInput($fullName);
    $email         = strtolower(sanitizeInput($email));
    $studentNumber = ($studentNumber !== null && trim($studentNumber) !== '')
        ? sanitizeInput($studentNumber)
        : null;
    $phone         = ($phone !== null && trim($phone) !== '')
        ? sanitizeInput($phone)
        : null;

    if ($fullName === '') {
        $errors['full_name'] = 'Full name is required.';
    } elseif (strlen($fullName) > 120) {
        $errors['full_name'] = 'Full name is too long.';
    }

    if ($studentNumber === null) {
        $errors['student_number'] = 'Student number is required.';
    } elseif (strlen($studentNumber) > 60) {
        $errors['student_number'] = 'Student number is too long.';
    }

    if ($email === '') {
        $errors['email'] = 'Email is required.';
    } elseif (!validateEmail($email)) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif (strlen($email) > 190) {
        $errors['email'] = 'Email is too long.';
    } elseif (!str_ends_with($email, '@zetech.ac.ke')) {
        $errors['email'] = 'Student email must end with @zetech.ac.ke.';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 200) {
        $errors['password'] = 'Password is too long.';
    }

    if ($password !== $confirmPassword) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }

    $pdo = db();

    if (!$pdo) {
        $errors['_general'] = 'Service temporarily unavailable. Please try again later.';

        return ['ok' => false, 'errors' => $errors, 'user_id' => null];
    }

    if (!$errors) {
        if (fetchUserByEmail($email) !== null) {
            $errors['email'] = 'An account with this email already exists.';
        }

        if ($studentNumber !== null) {
            $stmt = $pdo->prepare(
                'SELECT id FROM users WHERE student_number = ? LIMIT 1'
            );

            $stmt->execute([$studentNumber]);

            if ($stmt->fetch() !== false) {
                $errors['student_number'] =
                    'An account with this student number already exists.';
            }
        }
    }

    if ($errors) {
        return ['ok' => false, 'errors' => $errors, 'user_id' => null];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO users
                (full_name, email, student_number, phone, password_hash, role, account_status)
             VALUES (?, ?, ?, ?, ?, "student", "active")'
        );

        $stmt->execute([
            $fullName,
            $email,
            $studentNumber,
            $phone,
            $hash
        ]);
    } catch (Throwable $exception) {
        error_log('[CampusFind register] ' . $exception->getMessage());

        return [
            'ok'      => false,
            'errors'  => ['_general' => 'Could not create your account. Please try again.'],
            'user_id' => null
        ];
    }

    $userId = (int) $pdo->lastInsertId();

    logActivityDb('register', 'Registered new account: ' . $email);

    return ['ok' => true, 'errors' => [], 'user_id' => $userId];
}

/**
 * Verify credentials and start a session.
 * Returns ['ok' => bool, 'error' => ?string].
 */
function attemptLogin(
    string $email,
    string $password,
    bool $remember
): array {
    $email = strtolower(sanitizeInput($email));

    if ($email === '' || $password === '') {
        return ['ok' => false, 'error' => 'Enter your email and password.'];
    }

    $pdo = db();

    if (!$pdo) {
        return [
            'ok'    => false,
            'error' => 'Service temporarily unavailable. Please try again later.'
        ];
    }

    $isWeb = (PHP_SAPI !== 'cli' && isset($_SERVER['REMOTE_ADDR']));

    if ($isWeb) {
        $ip = clientIp();

        try {
            $pdo->exec(
                'DELETE FROM login_attempts
                 WHERE attempted_at < (NOW() - INTERVAL 1 DAY)'
            );

            $recent = $pdo->prepare(
                "SELECT COUNT(*) FROM login_attempts
                 WHERE ip_address = ?
                   AND email = ?
                   AND successful = 0
                   AND attempted_at > (NOW() - INTERVAL 15 MINUTE)"
            );
            $recent->execute([$ip, $email]);

            if ((int) $recent->fetchColumn() >= 8) {
                return [
                    'ok'    => false,
                    'error' => 'Too many failed attempts. Please try again in 15 minutes.'
                ];
            }
        } catch (Throwable $exception) {
            error_log('[CampusFind rate limit] ' . $exception->getMessage());
        }
    }

    $user = fetchUserByEmail($email);

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        if ($isWeb) {
            try {
                $pdo->prepare(
                    'INSERT INTO login_attempts (ip_address, email, successful)
                     VALUES (?, ?, 0)'
                )->execute([clientIp(), $email]);
            } catch (Throwable $exception) {
                error_log('[CampusFind rate limit log] ' . $exception->getMessage());
            }
        }

        return ['ok' => false, 'error' => 'Invalid email or password.'];
    }

    if ($user['account_status'] !== 'active') {
        return [
            'ok'    => false,
            'error' => 'This account is suspended. Contact the campus office.'
        ];
    }

    if ($isWeb) {
        try {
            $pdo->prepare(
                'INSERT INTO login_attempts (ip_address, email, successful)
                 VALUES (?, ?, 1)'
            )->execute([clientIp(), $email]);

            $pdo->prepare(
                'DELETE FROM login_attempts
                 WHERE ip_address = ? AND email = ? AND successful = 0'
            )->execute([clientIp(), $email]);
        } catch (Throwable $exception) {
            error_log('[CampusFind rate limit reset] ' . $exception->getMessage());
        }
    }

    if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
        try {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([$newHash, $user['id']]);
        } catch (Throwable $exception) {
            error_log('[CampusFind rehash] ' . $exception->getMessage());
        }
    }

    applyLoginSession(
        (int) $user['id'],
        (string) $user['role'],
        (string) $user['full_name'],
        (string) $user['email'],
        true,
        (string) $user['account_status']
    );

    try {
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')
            ->execute([$user['id']]);
    } catch (Throwable $exception) {
        error_log('[CampusFind last login] ' . $exception->getMessage());
    }

    if ($remember) {
        createRememberToken((int) $user['id']);
    } else {
        clearRememberToken();
    }

    logActivityDb('login', 'User logged in');

    return ['ok' => true, 'error' => null, 'user' => $user];
}

/**
 * Clear the remember-me cookie and destroy the session.
 */
function logoutUser(): void
{
    clearRememberToken();

    logActivityDb('logout', 'User logged out');

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Create a single-use password reset token for the given email.
 * Always reports success to avoid leaking which emails exist.
 * Returns ['ok' => bool, 'token' => ?string, 'error' => ?string].
 */
function createPasswordReset(string $email): array
{
    $email = strtolower(sanitizeInput($email));

    $pdo = db();

    if (!$pdo) {
        return [
            'ok'    => false,
            'token' => null,
            'error' => 'Service temporarily unavailable.'
        ];
    }

    $user = fetchUserByEmail($email);

    if (!$user) {
        return ['ok' => true, 'token' => null, 'error' => null];
    }

    $token     = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expires   = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

    try {
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')
            ->execute([$user['id']]);

        $pdo->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (?, ?, ?)'
        )->execute([$user['id'], $tokenHash, $expires]);
    } catch (Throwable $exception) {
        error_log('[CampusFind password reset] ' . $exception->getMessage());

        return [
            'ok'    => false,
            'token' => null,
            'error' => 'Could not create a reset link. Please try again.'
        ];
    }

    logActivityDb('password_reset_request', 'Requested password reset: ' . $email);

    // Email the reset link to the account owner. The raw token is embedded in
    // the link; only its SHA-256 hash is stored. If mail delivery fails we
    // still return the token so the caller can surface a dev link (see below).
    $resetLink = rtrim(BASE_URL, '/')
        . '/auth/forgot-password.php?token='
        . urlencode($token);

    $subject = 'Reset your CampusFind password';
    $body = "Hi,\n\n"
        . "We received a request to reset your CampusFind password.\n"
        . "Open the link below to choose a new password (valid for 1 hour):\n\n"
        . $resetLink . "\n\n"
        . "If you did not request this, you can safely ignore this email.\n\n"
        . "— The CampusFind Team\n";

    $sent = sendMail($email, $subject, $body);

    return [
        'ok'        => true,
        'token'     => $token,
        'resetLink' => $resetLink,
        'sent'      => $sent,
        'error'     => null,
    ];
}

/**
 * Validate a reset token and set a new password.
 * Returns ['ok' => bool, 'error' => ?string].
 */
function resetPassword(
    string $token,
    string $password,
    string $confirmPassword
): array {
    $token = sanitizeInput($token);

    if (strlen($token) !== 64) {
        return ['ok' => false, 'error' => 'Invalid reset link.'];
    }

    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
    }

    if ($password !== $confirmPassword) {
        return ['ok' => false, 'error' => 'Passwords do not match.'];
    }

    $pdo = db();

    if (!$pdo) {
        return ['ok' => false, 'error' => 'Service temporarily unavailable.'];
    }

    $tokenHash = hash('sha256', $token);

    try {
        $stmt = $pdo->prepare(
            'SELECT pr.id, pr.user_id, u.email
             FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = ?
               AND pr.used_at IS NULL
               AND pr.expires_at > NOW()
             LIMIT 1'
        );

        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();
    } catch (Throwable $exception) {
        error_log('[CampusFind password reset verify] ' . $exception->getMessage());

        return ['ok' => false, 'error' => 'Invalid or expired reset link.'];
    }

    if ($row === false) {
        return ['ok' => false, 'error' => 'Invalid or expired reset link.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();

        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([$hash, $row['user_id']]);

        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
            ->execute([$row['id']]);

        $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?')
            ->execute([$row['user_id']]);

        $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND id != ?')
            ->execute([$row['user_id'], $row['id']]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('[CampusFind password reset apply] ' . $exception->getMessage());

        return ['ok' => false, 'error' => 'Could not reset password. Please try again.'];
    }

    logActivityDb('password_reset', 'Password reset completed: ' . $row['email']);

    return ['ok' => true, 'error' => null];
}