<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth-template.php';

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$mode  = ($token !== '') ? 'reset' : 'request';

$error   = null;
$success = null;
$devLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        http_response_code(419);
        exit('Invalid CSRF token. Please refresh the page and try again.');
    }

    if ($mode === 'request') {
        $result = createPasswordReset($_POST['email'] ?? '');

        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            // Only show a dev link when an account existed and mail could not be
            // delivered. When mail() accepted the message we tell the user to
            // check their inbox instead of leaking the token on screen.
            if (!empty($result['token']) && empty($result['sent'])) {
                $devLink = (string) $result['resetLink'];
                $success = 'A password reset link has been created. Email delivery is not configured on this server, so use the link below.';
            } else {
                $success = 'If an account exists for that email, a password reset link is on its way. Please check your inbox.';
            }
        }
    } else {
        $result = resetPassword(
            $token,
            $_POST['password'] ?? '',
            $_POST['password_confirm'] ?? ''
        );

        if ($result['ok']) {
            flash('success', 'Your password has been reset. You can now log in.');
            redirect('auth/login.php');
        }

        $error = $result['error'];
    }
}

auth_header($mode === 'reset' ? 'Reset password' : 'Forgot password');
?>
<div class="auth-card">
    <?php if ($mode === 'reset'): ?>
        <h1 class="page-title">Reset password</h1>
        <p class="auth-lead">Choose a new password for your account.</p>

        <?php if ($error): ?>
            <p class="flash flash-error"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="<?= e(auth_base()) ?>/auth/forgot-password.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="form-group">
                <label for="password">New password</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="password-toggle" data-target="password"
                            aria-label="Show password" aria-pressed="false">
                        <span data-icon-show><?= icon('eye', 18) ?></span>
                        <span data-icon-hide style="display:none"><?= icon('eye-off', 18) ?></span>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm new password</label>
                <div class="password-field">
                    <input type="password" id="password_confirm" name="password_confirm"
                           data-match="password" aria-describedby="password_confirm_hint" required>
                    <button type="button" class="password-toggle" data-target="password_confirm"
                            aria-label="Show password" aria-pressed="false">
                        <span data-icon-show><?= icon('eye', 18) ?></span>
                        <span data-icon-hide style="display:none"><?= icon('eye-off', 18) ?></span>
                    </button>
                </div>
                <span class="field-hint" id="password_confirm_hint"></span>
            </div>

            <button type="submit" class="button button-primary">Reset password</button>
        </form>

        <p class="auth-footer">
            <a href="<?= e(auth_base()) ?>/auth/login.php">Back to login</a>
        </p>
    <?php else: ?>
        <h1 class="page-title">Forgot password</h1>
        <p class="auth-lead">Enter your account email and we'll create a password reset link.</p>

        <?php $flashSuccess = flash('success'); ?>
        <?php if ($flashSuccess): ?>
            <p class="flash flash-success"><?= e($flashSuccess) ?></p>
        <?php endif; ?>

        <?php if ($error): ?>
            <p class="flash flash-error"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if ($success): ?>
            <p class="flash flash-success"><?= e($success) ?></p>
            <?php if ($devLink): ?>
                <p class="reset-link-box">
                    Dev reset link:
                    <a href="<?= e($devLink) ?>"><?= e($devLink) ?></a>
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" action="<?= e(auth_base()) ?>/auth/forgot-password.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?= e($_POST['email'] ?? '') ?>" required>
            </div>

            <button type="submit" class="button button-primary">Create reset link</button>
        </form>

        <p class="auth-footer">
            <a href="<?= e(auth_base()) ?>/auth/login.php">Back to login</a>
        </p>
    <?php endif; ?>
</div>
<?php auth_footer(); ?>