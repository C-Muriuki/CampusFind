<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/student_guard.php';

$userId = currentUserId();

$stmt = $pdo->prepare(
    "SELECT id, full_name, email, student_number, phone
     FROM users
     WHERE id = :id
     LIMIT 1"
);
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    exit('Account not found.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Refresh the page and try again.';
    }

    $formType = ($_POST['form_type'] ?? 'profile') === 'password' ? 'password' : 'profile';

    if ($formType === 'password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword     = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['new_password_confirm'] ?? '');

        $hashStmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
        $hashStmt->execute(['id' => $userId]);
        $account = $hashStmt->fetch();

        if (!$account || !password_verify($currentPassword, (string) $account['password_hash'])) {
            $errors[] = 'Your current password is incorrect.';
        }

        if ($newPassword === '') {
            $errors[] = 'Enter a new password.';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif (strlen($newPassword) > 200) {
            $errors[] = 'New password is too long.';
        }

        if ($newPassword !== '' && $newPassword !== $confirmPassword) {
            $errors[] = 'New password and confirmation do not match.';
        }

        if ($newPassword !== '' && $newPassword === $currentPassword) {
            $errors[] = 'New password must be different from your current password.';
        }

        if (!$errors) {
            try {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
                $update->execute(['hash' => $newHash, 'id' => $userId]);

                logActivityDb('password_changed', 'Student changed account password.');
                flash('success', 'Your password has been changed.');
                redirect('/student/profile.php');
                exit;
            } catch (PDOException $exception) {
                error_log('CampusFind password change error: ' . $exception->getMessage());
                $errors[] = 'Your password could not be changed. Please try again.';
            }
        }
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');

        if ($fullName === '' || student_text_length($fullName) > 120) {
            $errors[] = 'Enter a valid full name.';
        }

        if (!validateEmail($email) || student_text_length($email) > 190) {
            $errors[] = 'Enter a valid email address.';
        } elseif (!str_ends_with($email, '@zetech.ac.ke')) {
            $errors[] = 'Email must end with @zetech.ac.ke.';
        }

        if (student_text_length($phone) > 30) {
            $errors[] = 'Phone number is too long.';
        }

        if (!$errors) {
            $duplicate = $pdo->prepare(
                "SELECT id FROM users
                 WHERE email = :email AND id != :id
                 LIMIT 1"
            );
            $duplicate->execute([
                'email' => $email,
                'id' => $userId,
            ]);

            if ($duplicate->fetch()) {
                $errors[] = 'That email address is already in use.';
            }
        }

        if (!$errors) {
            try {
                $update = $pdo->prepare(
                    "UPDATE users
                     SET full_name = :full_name,
                         email = :email,
                         phone = :phone
                     WHERE id = :id"
                );

                $update->execute([
                    'full_name' => $fullName,
                    'email' => $email,
                    'phone' => $phone ?: null,
                    'id' => $userId,
                ]);

                // Keep the displayed session information in sync.
                $_SESSION['full_name'] = $fullName;
                $_SESSION['email'] = $email;

                logActivityDb('profile_updated', 'Student updated profile.');

                flash('success', 'Your profile has been updated.');
                redirect('/student/profile.php');
                exit;

            } catch (PDOException $exception) {
                error_log('CampusFind profile update error: ' . $exception->getMessage());
                $errors[] = 'Your profile could not be updated.';
            }
        }

        $user['full_name'] = $fullName;
        $user['email'] = $email;
        $user['phone'] = $phone;
    }
}

$successMessage = flash('success');

$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/student_header.php';
?>

<main class="student-main form-page">
    <div class="page-heading">
        <p class="eyebrow">ACCOUNT SETTINGS</p>
        <h1>My profile</h1>
        <p class="muted">Manage your personal and contact information.</p>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success">
            <?= e($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="report-form">
        <input type="hidden" name="csrf_token"
               value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="form_type" value="profile">

        <div class="form-section">
            <h2>Personal information</h2>

            <div class="form-group">
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name"
                       maxlength="120" required
                       value="<?= e($user['full_name']) ?>">
            </div>

            <div class="form-group">
                <label for="student_number">Student number</label>
                <input type="text" id="student_number"
                       value="<?= e($user['student_number']) ?>"
                       disabled>
                <small>Contact the administrator if this needs correction.</small>
            </div>
        </div>

        <div class="form-section">
            <h2>Contact information</h2>

            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email"
                       maxlength="190" required
                       value="<?= e($user['email']) ?>">
            </div>

            <div class="form-group">
                <label for="phone">Phone number</label>
                <input type="tel" id="phone" name="phone"
                       maxlength="30"
                       value="<?= e($user['phone'] ?? '') ?>">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                Save profile
            </button>
        </div>
    </form>

    <form method="POST" class="report-form password-change-form">
        <input type="hidden" name="csrf_token"
               value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="form_type" value="password">

        <div class="form-section">
            <h2>Change password</h2>

            <div class="form-group">
                <label for="current_password">Current password</label>
                <div class="password-field">
                    <input type="password" id="current_password" name="current_password"
                           required autocomplete="current-password">
                    <button type="button" class="password-toggle" data-target="current_password"
                            aria-label="Show password" aria-pressed="false">
                        <span data-icon-show><?= icon('eye', 18) ?></span>
                        <span data-icon-hide style="display:none"><?= icon('eye-off', 18) ?></span>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="new_password">New password</label>
                <div class="password-field">
                    <input type="password" id="new_password" name="new_password"
                           required autocomplete="new-password" minlength="8" maxlength="200">
                    <button type="button" class="password-toggle" data-target="new_password"
                            aria-label="Show password" aria-pressed="false">
                        <span data-icon-show><?= icon('eye', 18) ?></span>
                        <span data-icon-hide style="display:none"><?= icon('eye-off', 18) ?></span>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="new_password_confirm">Confirm new password</label>
                <div class="password-field">
                    <input type="password" id="new_password_confirm" name="new_password_confirm"
                           required autocomplete="new-password"
                           data-match="new_password" aria-describedby="new_password_confirm_hint">
                    <button type="button" class="password-toggle" data-target="new_password_confirm"
                            aria-label="Show password" aria-pressed="false">
                        <span data-icon-show><?= icon('eye', 18) ?></span>
                        <span data-icon-hide style="display:none"><?= icon('eye-off', 18) ?></span>
                    </button>
                </div>
                <span class="field-hint" id="new_password_confirm_hint"></span>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                Update password
            </button>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>