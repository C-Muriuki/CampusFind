<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth-template.php';

if (isLoggedIn()) {
    redirect('student/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        http_response_code(419);
        exit('Invalid CSRF token. Please refresh the page and try again.');
    }

    if (!consentGiven($_POST['consent'] ?? null)) {
        $errors['consent'] = 'You must agree to the data privacy policy to create an account.';
    }

    if (!$errors) {
        $result = registerUser(
            $_POST['full_name'] ?? '',
            $_POST['email'] ?? '',
            $_POST['password'] ?? '',
            $_POST['password_confirm'] ?? '',
            $_POST['student_number'] ?? null,
            $_POST['phone'] ?? null
        );

        if ($result['ok']) {
            $user = fetchUserById((int) $result['user_id']);

            if ($user !== null) {
                applyLoginSession(
                    (int) $user['id'],
                    (string) $user['role'],
                    (string) $user['full_name'],
                    (string) $user['email'],
                    true,
                    (string) ($user['account_status'] ?? 'active')
                );

                logActivityDb('login', 'Auto-login after registration');
            }

            flash('success', 'Welcome to CampusFind! Your account has been created.');
            redirect('student/dashboard.php');
        }

        $errors = $result['errors'];
    }
}

auth_header('Create account');
?>
<div class="auth-card">
    <h1 class="page-title">Create your account</h1>
    <p class="auth-lead">Register to report and recover lost items on campus.</p>

    <?php if (!empty($errors['_general'])): ?>
        <p class="flash flash-error"><?= e($errors['_general']) ?></p>
    <?php endif; ?>

    <form method="POST" action="<?= e(auth_base()) ?>/auth/register.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

        <div class="form-group">
            <label for="full_name">Full name</label>
            <input type="text" id="full_name" name="full_name"
                   value="<?= e($_POST['full_name'] ?? '') ?>" required>
            <?php if (isset($errors['full_name'])): ?>
                <span class="form-error"><?= e($errors['full_name']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="email">Student email (@zetech.ac.ke)</label>
            <input type="email" id="email" name="email"
                   value="<?= e($_POST['email'] ?? '') ?>" required>
            <?php if (isset($errors['email'])): ?>
                <span class="form-error"><?= e($errors['email']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="student_number">Student number</label>
            <input type="text" id="student_number" name="student_number"
                   value="<?= e($_POST['student_number'] ?? '') ?>" required>
            <?php if (isset($errors['student_number'])): ?>
                <span class="form-error"><?= e($errors['student_number']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="phone">Phone (optional)</label>
            <input type="text" id="phone" name="phone"
                   value="<?= e($_POST['phone'] ?? '') ?>">
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
            <?php if (isset($errors['password'])): ?>
                <span class="form-error"><?= e($errors['password']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="password_confirm">Confirm password</label>
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
            <?php if (isset($errors['password_confirm'])): ?>
                <span class="form-error"><?= e($errors['password_confirm']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group form-check">
            <label>
                <input type="checkbox" name="consent" value="1" required>
                I agree to the
                <a href="<?= e(auth_base()) ?>/pages/privacy.php" target="_blank" rel="noopener">Data Privacy Policy</a>.
            </label>
            <?php if (isset($errors['consent'])): ?>
                <span class="form-error"><?= e($errors['consent']) ?></span>
            <?php endif; ?>
        </div>

        <button type="submit" class="button button-primary">Create account</button>
    </form>

    <p class="auth-footer">
        Already have an account?
        <a href="<?= e(auth_base()) ?>/auth/login.php">Login</a>
    </p>
</div>
<?php auth_footer(); ?>