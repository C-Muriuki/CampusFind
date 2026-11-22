<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/**
 * Stage 6.3: Contact Us page.
 *
 * Demonstrates required PHP concepts:
 *  - multidimensional array for subject options (rendered with foreach)
 *  - filter_var() for email validation
 *  - server-side validation rules
 *  - PDO prepared statement persistence
 *  - link to the authenticated user when signed in
 */

$subjects = [
    'general'      => 'General inquiry',
    'lost_item'    => 'I lost an item',
    'found_item'   => 'I found an item',
    'claim_help'   => 'Help with a claim',
    'account'      => 'Account or login issue',
    'feedback'     => 'Feedback or suggestion',
    'other'        => 'Other',
];

$name = sanitizeInput($_POST['name'] ?? '');
$email = sanitizeInput($_POST['email'] ?? '');
$subjectKey = sanitizeInput($_POST['subject'] ?? '');
$message = sanitizeInput($_POST['message'] ?? '');

$errors = [];

// Pre-fill from the session user when signed in and the form was not posted.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isLoggedIn()) {
    $user = currentUser();
    if ($user !== null) {
        $name = $name !== '' ? $name : (string) ($user['full_name'] ?? '');
        $email = $email !== '' ? $email : (string) ($user['email'] ?? '');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors['_form'] = 'Your session expired. Please try again.';
    }

    if ($name === '' || cf_text_length($name) > 120) {
        $errors['name'] = 'Please enter your name (up to 120 characters).';
    }

    if ($email === '') {
        $errors['email'] = 'Please enter your email address.';
    } elseif (!validateEmail($email)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (cf_text_length($email) > 190) {
        $errors['email'] = 'Email address is too long.';
    }

    if ($subjectKey === '' || !array_key_exists($subjectKey, $subjects)) {
        $errors['subject'] = 'Please choose a subject.';
    }

    if ($message === '' || cf_text_length($message) < 10) {
        $errors['message'] = 'Please enter a message of at least 10 characters.';
    } elseif (cf_text_length($message) > 5000) {
        $errors['message'] = 'Message is too long (maximum 5000 characters).';
    }

    if (!$errors) {
        $pdo = db();
        $saved = false;
        $submissionId = null;

        if ($pdo) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO contact_submissions
                        (user_id, name, email, subject, message)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    currentUserId(),
                    $name,
                    $email,
                    $subjects[$subjectKey],
                    $message,
                ]);
                $submissionId = (int) $pdo->lastInsertId();
                $saved = true;
            } catch (Throwable $exception) {
                error_log('[CampusFind contact] ' . $exception->getMessage());
            }
        }

        if ($saved) {
            logActivityDb('contact_submitted', 'Contact form: ' . $subjects[$subjectKey]);

            // Email the support inbox so the message is visible in email too.
            // The visitor's address becomes Reply-To; failure here never blocks
            // the on-site confirmation because the row is already persisted.
            $supportEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '';
            if ($supportEmail !== '') {
                $subjectLine = 'New contact message: ' . $subjects[$subjectKey];
                $body = buildContactNotificationEmail(
                    $name,
                    $email,
                    $subjects[$subjectKey],
                    $message,
                    $submissionId
                );
                sendMail($supportEmail, $subjectLine, $body, $email);
            }

            flash('success', 'Thank you. Your message has been received.');
            redirect('pages/contact.php');
        }

        $errors['_form'] = 'We could not save your message right now. Please try again.';
    }
}

$pageTitle = 'Contact Us | CampusFind';
$activeNav = 'contact';
$seoDescription = 'Contact the CampusFind team with questions about lost items, claims, accounts or feedback.';
$seoNoCache = true; // form contains a per-session CSRF token — never shared-cache

require_once __DIR__ . '/../includes/public_header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow">GET IN TOUCH</p>
        <h1>Contact us</h1>
        <p class="page-hero-lead">
            Send a message to the CampusFind team.
        </p>
    </div>
</section>

<section class="page-section">
    <div class="container">

        <div class="contact-grid">
            <div class="contact-info">
                <h2>How we can help</h2>
                <p>
                    Use the form to report a problem, ask about a claim,
                    or share feedback about CampusFind.
                </p>
                <ul class="contact-info-list">
                    <li>Lost or found an item? Browse the listings first.</li>
                    <li>Need help with a claim? Choose the claim subject.</li>
                    <li>Account issues? We can help you get back in.</li>
                </ul>

                <div class="contact-direct">
                    <p class="contact-direct-title">Reach the team directly</p>
                    <?php if (defined('SUPPORT_WHATSAPP') && SUPPORT_WHATSAPP !== ''): ?>
                        <a class="contact-direct-link" href="https://wa.me/<?= e(SUPPORT_WHATSAPP) ?>"
                           target="_blank" rel="noopener noreferrer">
                            <span class="contact-direct-ico">
                                <?= icon('whatsapp', 20) ?>
                            </span>
                            WhatsApp: <?= e(SUPPORT_WHATSAPP) ?>
                        </a>
                    <?php endif; ?>
                    <?php if (defined('SUPPORT_EMAIL') && SUPPORT_EMAIL !== ''): ?>
                        <a class="contact-direct-link" href="mailto:<?= e(SUPPORT_EMAIL) ?>">
                            <span class="contact-direct-ico">
                                <?= icon('mail', 20) ?>
                            </span>
                            Email: <?= e(SUPPORT_EMAIL) ?>
                        </a>
                    <?php endif; ?>
                </div>

                <p class="contact-note">
                    Please do not include passwords or payment details in your message.
                </p>
            </div>

            <div class="contact-form-wrap">
                <form method="POST" class="contact-form" novalidate>
                    <input type="hidden" name="csrf_token"
                           value="<?= e(csrfToken()) ?>">

                    <div class="form-field">
                        <label for="contact-name">Name</label>
                        <input type="text" id="contact-name" name="name"
                               value="<?= e($name) ?>"
                               maxlength="120"
                               required
                               autocomplete="name"
                               aria-describedby="contact-name-error">
                        <?php if (isset($errors['name'])): ?>
                            <p class="field-error" id="contact-name-error">
                                <?= e($errors['name']) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label for="contact-email">Email</label>
                        <input type="email" id="contact-email" name="email"
                               value="<?= e($email) ?>"
                               maxlength="190"
                               required
                               autocomplete="email"
                               aria-describedby="contact-email-error">
                        <?php if (isset($errors['email'])): ?>
                            <p class="field-error" id="contact-email-error">
                                <?= e($errors['email']) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label for="contact-subject">Subject</label>
                        <select id="contact-subject" name="subject"
                                required
                                aria-describedby="contact-subject-error">
                            <option value="">Choose a subject...</option>
                            <?php foreach ($subjects as $key => $label): ?>
                                <option value="<?= e($key) ?>"
                                    <?= $subjectKey === $key ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['subject'])): ?>
                            <p class="field-error" id="contact-subject-error">
                                <?= e($errors['subject']) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label for="contact-message">Message</label>
                        <textarea id="contact-message" name="message"
                                  rows="6" maxlength="5000" required
                                  aria-describedby="contact-message-error"><?= e($message) ?></textarea>
                        <?php if (isset($errors['message'])): ?>
                            <p class="field-error" id="contact-message-error">
                                <?= e($errors['message']) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if (isset($errors['_form'])): ?>
                        <div class="alert alert-error"><?= e($errors['_form']) ?></div>
                    <?php endif; ?>

                    <button class="button button-primary" type="submit">
                        <?= icon('mail', 16) ?> Send message
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/public_footer.php'; ?>