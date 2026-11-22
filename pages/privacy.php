<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth-template.php';

$pageTitle = 'Data Privacy Policy';

auth_header($pageTitle);
?>
<div class="auth-card privacy-card">
    <h1 class="page-title">Data Privacy Policy</h1>
    <p class="auth-lead">How CampusFind collects, uses and protects your information.</p>

    <p class="privacy-date"><em>Last updated: <?= e(date('d F Y')) ?></em></p>

    <section class="privacy-section">
        <h2>1. Who we are</h2>
        <p>
            CampusFind is a lost-and-found service for the Zetech University community.
            This policy explains what data we collect when you create an account,
            submit reports or interact with the service, and how we handle it.
        </p>
    </section>

    <section class="privacy-section">
        <h2>2. Information we collect</h2>
        <ul>
            <li><strong>Account details:</strong> your full name, student email, student number and (optionally) phone number.</li>
            <li><strong>Reports:</strong> the items you report, descriptions, locations, dates and any photos you upload.</li>
            <li><strong>Activity:</strong> sign-in records, moderation decisions and account changes kept for security and auditing.</li>
            <li><strong>Technical data:</strong> IP address and browser information recorded in activity logs.</li>
        </ul>
    </section>

    <section class="privacy-section">
        <h2>3. How we use your information</h2>
        <ul>
            <li>To operate the lost-and-found service and match items with their owners.</li>
            <li>To notify you about your reports, claims and account status.</li>
            <li>To keep the platform secure (rate limiting, fraud and abuse prevention).</li>
            <li>To meet legal and university obligations.</li>
        </ul>
        <p>We do not sell your personal information to third parties.</p>
    </section>

    <section class="privacy-section">
        <h2>4. Claims and verification</h2>
        <p>
            When you claim an item, the proof you provide and your claim decision history
            are shared with administrators so ownership can be verified before an item is
            handed over. Approved reports and resolved claims may be visible to other
            signed-in users.
        </p>
    </section>

    <section class="privacy-section">
        <h2>5. Data security</h2>
        <p>
            Passwords are stored as salted hashes. Sessions use HTTP-only, SameSite cookies
            and CSRF protection. Input is validated and output is escaped. Access to
            administrative data is restricted to authorised staff.
        </p>
    </section>

    <section class="privacy-section">
        <h2>6. Your rights</h2>
        <p>
            You may request access to, correction of, or deletion of your personal data by
            contacting the campus administration office. You can update your name, email
            and contact details from your profile at any time.
        </p>
    </section>

    <section class="privacy-section">
        <h2>7. Data retention</h2>
        <p>
            We keep personal data only as long as necessary to provide the service and meet
            legal obligations, after which it is deleted or anonymised.
        </p>
    </section>

    <section class="privacy-section">
        <h2>8. Changes to this policy</h2>
        <p>
            We may update this policy from time to time. Material changes will be
            communicated through the service.
        </p>
    </section>

    <section class="privacy-section">
        <h2>9. Contact</h2>
        <p>
            Questions about this policy or your data can be directed to the CampusFind
            administrators via the campus IT office.
        </p>
    </section>

    <p class="auth-footer">
        <a href="<?= e(auth_base()) ?>/auth/register.php">Back to registration</a>
    </p>
</div>
<?php auth_footer(); ?>