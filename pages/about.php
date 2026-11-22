<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = 'About | CampusFind';
$activeNav = 'about';
$seoDescription = 'About CampusFind, a secure lost and found service for the campus community. Learn how it works and get involved.';
require_once __DIR__ . '/../includes/public_header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow">BUILT FOR CAMPUS COMMUNITIES</p>
        <h1>About CampusFind</h1>
        <p class="page-hero-lead">
            A simple, secure way to report and recover lost items on campus.
        </p>
    </div>
</section>

<section class="page-section">
    <div class="container">

        <div class="about-grid">
            <div class="about-main">
                <h2>What is CampusFind?</h2>
                <p>
                    CampusFind is a lost-and-found service for the Zetech University
                    community. Students report items they have lost or found, and the
                    campus community reconnects belongings with their owners through a
                    simple, moderated process.
                </p>

                <h2>How it works</h2>
                <ol class="about-steps">
                    <li><strong>Report.</strong> Share a few details about the item.</li>
                    <li><strong>Browse.</strong> Search approved lost and found reports.</li>
                    <li><strong>Reconnect.</strong> Submit a claim and arrange a safe return.</li>
                </ol>

                <h2>Our commitment</h2>
                <p>
                    Every report is reviewed by an administrator before it appears
                    publicly. Private contact details are never shown. Claims are
                    verified with proof of ownership before items are handed over.
                    We handle your data responsibly and in line with our
                    <a href="<?= e(rtrim(BASE_URL, '/')) ?>/pages/privacy.php">privacy policy</a>.
                </p>
            </div>

            <aside class="about-aside">
                <div class="info-card">
                    <h2>Get involved</h2>
                    <p>
                        Create an account to report items, browse listings and
                        track your submissions.
                    </p>
                    <a class="button button-primary"
                       href="<?= e(rtrim(BASE_URL, '/')) ?>/auth/register.php">
                        Create account
                    </a>
                </div>

                <div class="info-card">
                    <h2>Need help?</h2>
                    <p>
                        Have a question or found something valuable? Get in
                        touch with the campus team.
                    </p>
                    <a class="button button-outline"
                       href="<?= e(rtrim(BASE_URL, '/')) ?>/pages/contact.php">
                        Contact us
                    </a>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/public_footer.php'; ?>