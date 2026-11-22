<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Stage 3.2: shared layout for the authentication pages.
 */

function auth_base(): string
{
    return rtrim(BASE_URL, '/');
}

function auth_header(string $title): void
{
    $base = auth_base();
    ?>
<!doctype html>
<html lang="en" data-theme="<?= e(CURRENT_THEME) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> &middot; CampusFind</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/auth.css">

    <script src="<?= e($base) ?>/assets/js/theme.js" defer></script>
    <script src="<?= e($base) ?>/assets/js/auth.js" defer></script>
</head>
<body class="auth-body">

<a class="skip-link" href="#main-content">Skip to content</a>

<header class="auth-header">
    <div class="container auth-header-inner">
        <a class="brand" href="<?= e($base) ?>/index.php" aria-label="CampusFind home">
            <img src="<?= e($base) ?>/assets/images/campusfind-backpack-search.png" alt="" class="brand-artwork">
            <span class="brand-copy">
                <span class="brand-name">Campus<span>Find</span></span>
                <span class="brand-caption">Lost &amp; Found</span>
            </span>
        </a>

        <a class="button button-outline" href="<?= e($base) ?>/index.php">Back to home</a>
    </div>
</header>

<main id="main-content" class="auth-main">
    <?php
}

function auth_footer(): void
{
    $base = auth_base();
    ?>
</main>

<footer class="auth-footer-bar">
    <div class="container">
        <p>&copy; <?= date('Y') ?> CampusFind. All rights reserved.</p>
    </div>
</footer>

</body>
</html>
    <?php
}