<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle       = 'CampusFind | Lost & Found on Zetech Campus';
$seoDescription  = 'Report, search and recover lost belongings across the Zetech University campus community with CampusFind.';
$activeNav       = 'home';

// Live community stats (best-effort; the homepage is cached ~60s for guests).
$stats = ['reported' => 0, 'recovered' => 0, 'students' => 0];
try {
    if ($pdo = db()) {
        $stats['reported']  = (int) $pdo
            ->query("SELECT COUNT(*) FROM reports WHERE status IN ('approved','resolved')")
            ->fetchColumn();
        $stats['recovered'] = (int) $pdo
            ->query("SELECT COUNT(*) FROM reports WHERE status = 'resolved'")
            ->fetchColumn();
        $stats['students']  = (int) $pdo
            ->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND account_status = 'active'")
            ->fetchColumn();
    }
} catch (Throwable $e) {
    // Stats are decorative; never block the homepage on a DB hiccup.
}

require __DIR__ . '/includes/public_header.php';
?>

<section class="hero">
    <div class="container hero-inner">
        <div class="hero-content">
            <p class="eyebrow">ZETECH UNIVERSITY &middot; LOST &amp; FOUND</p>

            <h1>Lost something?<br><span>Find it here.</span></h1>

            <p class="hero-description">
                Report, search and recover lost belongings across the Zetech campus
                community &mdash; all in one place.
            </p>

            <div class="hero-actions">
                <a href="<?= e($base) ?>/pages/items.php"
                   class="button button-primary button-large">
                    Browse Items
                </a>
                <a href="<?= e($base) ?>/auth/login.php"
                   class="button button-outline button-large">
                    Report an Item
                </a>
            </div>

            <ul class="hero-points">
                <li>Free for every Zetech student</li>
                <li>Verified campus community</li>
                <li>Fast claims &amp; recovery</li>
            </ul>
        </div>

        <div class="hero-visual">
            <img
                src="<?= e($base) ?>/assets/images/campusfind-backpack-search.png"
                alt="Magenta backpack with a magnifying glass representing CampusFind"
                class="hero-image"
                width="650"
                height="600"
                fetchpriority="high"
            >
        </div>
    </div>
</section>

<section class="stats-section" aria-label="CampusFind at a glance">
    <div class="container stats-grid">
        <div class="stat-card">
            <span class="stat-num"><?= number_format($stats['reported']) ?></span>
            <span class="stat-label">Items reported</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= number_format($stats['recovered']) ?></span>
            <span class="stat-label">Safely recovered</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= number_format($stats['students']) ?></span>
            <span class="stat-label">Students on board</span>
        </div>
    </div>
</section>

<section class="how-section" id="how-it-works">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">HOW IT WORKS</p>
            <h2>Three steps to <span>reconnect</span></h2>
        </div>

        <div class="cards-grid">
            <article class="info-card">
                <div class="info-card-top">
                    <span class="info-card-icon"><?= icon('edit', 20) ?></span>
                    <span class="info-card-step">01</span>
                </div>
                <h3>Report an item</h3>
                <p>Share what you lost or found with a photo, a location and a few details.</p>
            </article>

            <article class="info-card">
                <div class="info-card-top">
                    <span class="info-card-icon"><?= icon('search', 20) ?></span>
                    <span class="info-card-step">02</span>
                </div>
                <h3>Browse &amp; search</h3>
                <p>Filter campus reports by type, category and keyword to spot a match fast.</p>
            </article>

            <article class="info-card">
                <div class="info-card-top">
                    <span class="info-card-icon"><?= icon('refresh', 20) ?></span>
                    <span class="info-card-step">03</span>
                </div>
                <h3>Claim &amp; reconnect</h3>
                <p>Submit a claim with proof, then collect your item once it is verified.</p>
            </article>
        </div>
    </div>
</section>

<section class="cta-section">
    <div class="container cta-inner">
        <div class="cta-copy">
            <h2>Make lost things easier to find.</h2>
            <p>Join the Zetech lost-and-found community in minutes.</p>
        </div>
        <a href="<?= e($base) ?>/auth/register.php" class="button button-white button-large">
            Get started free
        </a>
    </div>
</section>

<?php require __DIR__ . '/includes/public_footer.php'; ?>