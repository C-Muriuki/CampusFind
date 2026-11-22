<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth-template.php';
require_once __DIR__ . '/../includes/security_helpers.php';

if (isLoggedIn()) {
    redirect(($_SESSION['role'] ?? '') === 'admin'
        ? 'admin/dashboard.php'
        : 'student/dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        http_response_code(419);
        exit('Invalid CSRF token. Please refresh the page and try again.');
    }

    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $pdo      = db();

    if ($pdo && cfLoginIsBlocked($pdo, $email, $clientIp)) {
        $error = 'Too many unsuccessful attempts. Please try again later.';
    } else {
        $result = attemptLogin(
            $email,
            $password,
            isset($_POST['remember'])
        );

        if ($result['ok']) {
            if ($pdo) {
                cfClearLoginAttempts($pdo, $email, $clientIp);
            }

            $redirectTo = $_SESSION['redirect_after_login'] ?? '';
            unset($_SESSION['redirect_after_login']);

            if (
                is_string($redirectTo) &&
                str_starts_with($redirectTo, '/') &&
                !str_starts_with($redirectTo, '//')
            ) {
                redirect(ltrim($redirectTo, '/'));
            }

            redirect(($_SESSION['role'] ?? '') === 'admin'
                ? 'admin/dashboard.php'
                : 'student/dashboard.php');
        }

        if ($pdo) {
            cfRecordFailedLogin($pdo, $email, $clientIp);
        }

        $error = $result['error'];
    }
}

$loggedOut = isset($_GET['logged_out']);

auth_header('Login');
?>
<div class="auth-card">
    <h1 class="page-title">Login</h1>
    <p class="auth-lead">Access your CampusFind dashboard.</p>

    <?php $flashSuccess = flash('success'); ?>
    <?php if ($flashSuccess): ?>
        <p class="flash flash-success"><?= e($flashSuccess) ?></p>
    <?php endif; ?>

    <?php if ($loggedOut): ?>
        <p class="flash flash-success">You have been logged out.</p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="flash flash-error"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="POST" action="<?= e(auth_base()) ?>/auth/login.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email"
                   value="<?= e($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <div class="password-field">
                <input type="password" id="password" name="password" required>
                <button type="button" class="password-toggle" data-target="password"
                        aria-label="Show password" aria-pressed="false">
                    <span data-icon-show><?= icon('eye', 18) ?></span>
                    <span data-icon-hide style="display:none"><?= icon('eye-off', 18) ?></span>
                </button>
            </div>
        </div>

        <div class="form-group form-check">
            <label>
                <input type="checkbox" name="remember" value="1">
                Remember me for 30 days
            </label>
        </div>

        <button type="submit" class="button button-primary">Login</button>
    </form>

    <p class="auth-links">
        <a href="<?= e(auth_base()) ?>/auth/forgot-password.php">Forgot password?</a>
    </p>

    <p class="auth-legal">
        By signing in you acknowledge our
        <a href="<?= e(auth_base()) ?>/pages/privacy.php" target="_blank" rel="noopener">Data Privacy Policy</a>.
    </p>

    <p class="auth-footer">
        Don't have an account?
        <a href="<?= e(auth_base()) ?>/auth/register.php">Register</a>
    </p>
</div>
<?php auth_footer(); ?>