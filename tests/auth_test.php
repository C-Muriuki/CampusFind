<?php
declare(strict_types=1);

/**
 * Stage 3.2 authentication test harness (CLI).
 *
 * Boots the application (config + functions + database), exercises
 * registration, login and password-reset logic against the real database,
 * and cleans up every user it creates.
 *
 * Run with the pdo_mysql driver flags on this host:
 *   php \
 *     -d extension=/tmp/php-mysql-ext/usr/lib/php/20230831/mysqlnd.so \
 *     -d extension=/tmp/php-mysql-ext/usr/lib/php/20230831/pdo_mysql.so \
 *     tests/auth_test.php
 *
 * Exit code 0 = all assertions passed, 1 = one or more failed.
 */

require_once dirname(__DIR__) . '/includes/auth.php';

// ---------------------------------------------------------------------------
// Minimal test runner
// ---------------------------------------------------------------------------

$results = ['pass' => 0, 'fail' => 0, 'failures' => []];
$createdUserIds = [];

// In CLI there are no HTTP headers, so setcookie()/session_regenerate_id()
// emit "headers already sent"-style warnings that are irrelevant here. Let
// those through silently, but turn every other PHP error into a hard failure.
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    $ignored = [
        'headers already sent',
        'Cannot modify header information',
        'session_regenerate_id',
        'session_start',
        'setcookie',
    ];

    foreach ($ignored as $needle) {
        if (stripos($message, $needle) !== false) {
            return true;
        }
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

function check(string $label, bool $condition, string $detail = ''): void
{
    global $results;

    if ($condition) {
        $results['pass']++;
        return;
    }

    $results['fail']++;
    $results['failures'][] = $detail !== '' ? $label . ' :: ' . $detail : $label;
}

function cleanupUserIds(array $ids): void
{
    $pdo = db();

    if (!$pdo || !$ids) {
        return;
    }

    $ids = array_values(array_unique(array_map('intval', $ids)));

    $in = implode(',', array_fill(0, count($ids), '?'));

    foreach (['remember_tokens', 'password_resets', 'activity_logs'] as $table) {
        try {
            $pdo->prepare("DELETE FROM $table WHERE user_id IN ($in)")->execute($ids);
        } catch (Throwable $e) {
            // table may not exist in a given schema; ignore
        }
    }

    try {
        $pdo->prepare("DELETE FROM users WHERE id IN ($in)")->execute($ids);
    } catch (Throwable $e) {
        fwrite(STDERR, "cleanup failed: " . $e->getMessage() . "\n");
    }
}

$pdo = db();

if (!$pdo) {
    fwrite(STDERR, "FATAL: no database connection\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// Baseline cleanup: remove any users left over from a previous run.
// ---------------------------------------------------------------------------

$prefix = 'autotest';

$stmt = $pdo->prepare(
    "SELECT id FROM users WHERE email LIKE ?"
);
$stmt->execute([$prefix . '%@zetech.ac.ke']);

foreach ($stmt->fetchAll() as $row) {
    cleanupUserIds([(int) $row['id']]);
}

$pass = 'StrongPass!234';
$otherPass = 'DifferentPass!9';

// ===========================================================================
// REGISTRATION
// ===========================================================================

// 1. Valid registration succeeds.
$res = registerUser(
    'Auto Test Student',
    $prefix . '_valid@zetech.ac.ke',
    $pass,
    $pass,
    'AUTOTEST-001',
    '+254700000001'
);
check('register: valid returns ok', $res['ok'] === true, json_encode($res['errors'] ?? []));
check('register: valid returns user_id', is_int($res['user_id']) && $res['user_id'] > 0);

if (is_int($res['user_id']) && $res['user_id'] > 0) {
    $createdUserIds[] = $res['user_id'];
}

// 2. Duplicate student number is rejected.
$res = registerUser(
    'Auto Test Duplicate Sn',
    $prefix . '_dupsn@zetech.ac.ke',
    $pass,
    $pass,
    'AUTOTEST-001',
    null
);
check('register: duplicate student number rejected', isset($res['errors']['student_number']));

if (is_int($res['user_id']) && $res['user_id'] > 0) {
    $createdUserIds[] = $res['user_id'];
}

// 3. Duplicate email is rejected.
$res = registerUser(
    'Auto Test Duplicate Email',
    $prefix . '_valid@zetech.ac.ke',
    $pass,
    $pass,
    'AUTOTEST-003',
    null
);
check('register: duplicate email rejected', isset($res['errors']['email']));

if (is_int($res['user_id']) && $res['user_id'] > 0) {
    $createdUserIds[] = $res['user_id'];
}

// 4. Missing student number (null) is rejected.
$res = registerUser(
    'Auto Test No Sn',
    $prefix . '_nosn@zetech.ac.ke',
    $pass,
    $pass,
    null,
    null
);
check(
    'register: null student number rejected',
    isset($res['errors']['student_number'])
        && $res['errors']['student_number'] === 'Student number is required.'
);

if (is_int($res['user_id']) && $res['user_id'] > 0) {
    $createdUserIds[] = $res['user_id'];
}

// 5. Empty-string student number is rejected.
$res = registerUser(
    'Auto Test Empty Sn',
    $prefix . '_emptysn@zetech.ac.ke',
    $pass,
    $pass,
    '   ',
    null
);
check(
    'register: blank student number rejected',
    isset($res['errors']['student_number'])
);

if (is_int($res['user_id']) && $res['user_id'] > 0) {
    $createdUserIds[] = $res['user_id'];
}

// 6. Non-zetech email domain is rejected.
$res = registerUser(
    'Auto Test Gmail',
    $prefix . '_gmail@gmail.com',
    $pass,
    $pass,
    'AUTOTEST-006',
    null
);
check(
    'register: non-zetech email rejected',
    isset($res['errors']['email'])
        && stripos((string) $res['errors']['email'], '@zetech.ac.ke') !== false
);

if (is_int($res['user_id']) && $res['user_id'] > 0) {
    $createdUserIds[] = $res['user_id'];
}

// 7. Uppercase zetech email is normalised and accepted.
$res = registerUser(
    'Auto Test Upper Email',
    strtoupper($prefix . '_upper@zetech.ac.ke'),
    $pass,
    $pass,
    'AUTOTEST-007',
    null
);
check('register: uppercase zetech email accepted', $res['ok'] === true, json_encode($res['errors'] ?? []));

if (is_int($res['user_id']) && $res['user_id'] > 0) {
    $createdUserIds[] = $res['user_id'];

    $stored = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $stored->execute([$res['user_id']]);
    $storedEmail = $stored->fetchColumn();
    check(
        'register: uppercase email stored lowercase',
        is_string($storedEmail) && $storedEmail === strtolower($prefix . '_upper@zetech.ac.ke'),
        var_export($storedEmail, true)
    );
}

// 8. Password mismatch is rejected.
$res = registerUser(
    'Auto Test Mismatch',
    $prefix . '_mismatch@zetech.ac.ke',
    $pass,
    $otherPass,
    'AUTOTEST-008',
    null
);
check('register: password mismatch rejected', isset($res['errors']['password_confirm']));

if (is_int($res['user_id']) && $res['user_id'] > 0) {
    $createdUserIds[] = $res['user_id'];
}

// 9. Short password is rejected.
$res = registerUser(
    'Auto Test Short',
    $prefix . '_short@zetech.ac.ke',
    'short',
    'short',
    'AUTOTEST-009',
    null
);
check('register: short password rejected', isset($res['errors']['password']));

if (is_int($res['user_id']) && $res['user_id'] > 0) {
    $createdUserIds[] = $res['user_id'];
}

// 10. Over-length student number is rejected.
$res = registerUser(
    'Auto Test Long Sn',
    $prefix . '_longsn@zetech.ac.ke',
    $pass,
    $pass,
    str_repeat('X', 61),
    null
);
check('register: long student number rejected', isset($res['errors']['student_number']));

if (is_int($res['user_id']) && $res['user_id'] > 0) {
    $createdUserIds[] = $res['user_id'];
}

// ===========================================================================
// LOGIN
// ===========================================================================

// 11. Correct credentials succeed.
$res = attemptLogin($prefix . '_valid@zetech.ac.ke', $pass, false);
check('login: correct credentials ok', $res['ok'] === true);

// 12. Wrong password fails with generic error.
$res = attemptLogin($prefix . '_valid@zetech.ac.ke', 'WrongPass!1', false);
check(
    'login: wrong password rejected',
    $res['ok'] === false && $res['error'] === 'Invalid email or password.'
);

// 13. Nonexistent email fails with generic error.
$res = attemptLogin($prefix . '_nobody@zetech.ac.ke', $pass, false);
check(
    'login: nonexistent email rejected',
    $res['ok'] === false && $res['error'] === 'Invalid email or password.'
);

// 14. Empty credentials fail fast.
$res = attemptLogin('', '', false);
check('login: empty credentials rejected', $res['ok'] === false);

// ===========================================================================
// CONSENT (data privacy)
// ===========================================================================

check('consent: "1" accepted', consentGiven('1') === true);
check('consent: "on" accepted', consentGiven('on') === true);
check('consent: true accepted', consentGiven(true) === true);
check('consent: empty string rejected', consentGiven('') === false);
check('consent: null rejected', consentGiven(null) === false);
check('consent: "0" rejected', consentGiven('0') === false);

// ===========================================================================
// PASSWORD RESET
// ===========================================================================

// 15. Reset token is created for an existing account.
$reset = createPasswordReset($prefix . '_valid@zetech.ac.ke');
check('reset: token created', $reset['ok'] === true && is_string($reset['token']));
check('reset: token is 64 hex chars', is_string($reset['token']) && strlen($reset['token']) === 64);
$validToken = is_string($reset['token']) ? $reset['token'] : '';

// 16. Resetting with a valid token sets a new password.
$reset2 = resetPassword($validToken, $otherPass, $otherPass);
check('reset: valid token applied', $reset2['ok'] === true, (string) $reset2['error']);

$res = attemptLogin($prefix . '_valid@zetech.ac.ke', $otherPass, false);
check('reset: login with new password ok', $res['ok'] === true);

$res = attemptLogin($prefix . '_valid@zetech.ac.ke', $pass, false);
check('reset: old password no longer works', $res['ok'] === false);

// 17. A used token cannot be reused.
$reset3 = resetPassword($validToken, 'AnotherPass!7', 'AnotherPass!7');
check('reset: used token rejected', $reset3['ok'] === false);

// 18. Reset request for an unknown email reports success without a token.
$reset4 = createPasswordReset($prefix . '_ghost@zetech.ac.ke');
check(
    'reset: unknown email no token, still ok',
    $reset4['ok'] === true && $reset4['token'] === null
);

// ===========================================================================
// REPORT
// ===========================================================================

cleanupUserIds($createdUserIds);

$total = $results['pass'] + $results['fail'];

fwrite(STDOUT, "\nStage 3.2 auth tests\n");
fwrite(STDOUT, str_repeat('-', 40) . "\n");
fwrite(STDOUT, sprintf("Assertions: %d\n", $total));
fwrite(STDOUT, sprintf("Passed:     %d\n", $results['pass']));
fwrite(STDOUT, sprintf("Failed:     %d\n", $results['fail']));

if ($results['fail'] > 0) {
    fwrite(STDOUT, "\nFailures:\n");

    foreach ($results['failures'] as $failure) {
        fwrite(STDOUT, ' - ' . $failure . "\n");
    }

    fwrite(STDOUT, "\nRESULT: FAIL\n");
    exit(1);
}

fwrite(STDOUT, "\nRESULT: PASS\n");
exit(0);