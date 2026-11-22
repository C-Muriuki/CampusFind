<?php
declare(strict_types=1);

/**
 * Stage 6.2: shared public layout header.
 *
 * Expects bootstrap.php to have been loaded. Renders the public site
 * header (brand, navigation, auth actions) matching index.php.
 *
 * Optional variables before include:
 *   $pageTitle  string  document title
 *   $activeNav  string  nav key to mark active (home|about|items|info|contact)
 */

$base = rtrim(BASE_URL, '/');
$pageTitle = $pageTitle ?? 'CampusFind';
$activeNav = $activeNav ?? '';

$seoDescription = $seoDescription
    ?? 'Report, search and recover lost items on campus with CampusFind.';

// Prompt 13: allow short public caching for anonymous-safe pages only.
// Authenticated users and pages with CSRF forms ($seoNoCache) stay private.
if (!isLoggedIn() && empty($seoNoCache)) {
    setCachePolicy(true, 60);
}

$navItems = [
    'home'    => ['label' => 'Home',         'url' => $base . '/index.php'],
    'about'   => ['label' => 'About',        'url' => $base . '/pages/about.php'],
    'items'   => ['label' => 'Browse Items', 'url' => $base . '/pages/items.php'],
    'info'    => ['label' => 'Information',  'url' => $base . '/pages/information.php'],
    'contact' => ['label' => 'Contact Us',   'url' => $base . '/pages/contact.php'],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e(CURRENT_THEME) ?>"
      data-base-url="<?= e(BASE_URL) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= e($pageTitle) ?></title>

    <meta name="description" content="<?= e($seoDescription) ?>">
    <meta name="theme-color" content="<?= CURRENT_THEME === 'dark' ? '#080F2B' : '#FFFFFF' ?>">

    <?php renderSeoMeta([
        'title'       => $pageTitle,
        'description' => $seoDescription,
        'canonical'   => canonicalUrl(),
        'structured_data' => defaultStructuredData($seoDescription),
    ]); ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/responsive.css">
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/stage6-pages.css">
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/toast.css">
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/stage8-polish.css">
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/palette.css">

    <script src="<?= e($base) ?>/assets/js/theme.js" defer></script>
    <script src="<?= e($base) ?>/assets/js/nav.js" defer></script>
    <script src="<?= e($base) ?>/assets/js/toast.js" defer></script>
    <script src="<?= e($base) ?>/assets/js/palette.js" defer></script>
</head>
<body>

<a class="skip-link" href="#main-content">Skip to content</a>

<header class="site-header">
    <div class="container header-inner">

        <a class="brand" href="<?= e($base) ?>/index.php" aria-label="CampusFind home">
            <img src="<?= e($base) ?>/assets/images/campusfind-backpack-search.png"
                 alt="" class="brand-artwork">
            <span class="brand-copy">
                <span class="brand-name">Campus<span>Find</span></span>
                <span class="brand-caption">Lost &amp; Found</span>
            </span>
        </a>

        <button class="menu-toggle" type="button"
                aria-label="Open navigation" aria-expanded="false"
                data-breakpoint="680" aria-controls="primary-navigation">
            <span></span><span></span><span></span>
        </button>

        <nav class="primary-navigation" id="primary-navigation"
             aria-label="Main navigation">

            <?php foreach ($navItems as $key => $item): ?>
                <a href="<?= e($item['url']) ?>"
                   class="<?= $activeNav === $key ? 'active' : '' ?>"
                   <?= $activeNav === $key ? 'aria-current="page"' : '' ?>>
                    <?= e($item['label']) ?>
                </a>
            <?php endforeach; ?>

            <div class="mobile-auth-actions">
                <?php if (isLoggedIn()): ?>
                    <form method="POST"
                          action="<?= e($base) ?>/auth/logout.php"
                          class="logout-form">
                        <input type="hidden" name="csrf_token"
                               value="<?= e(csrfToken()) ?>">
                        <button type="submit" class="button button-outline">
                            Logout
                        </button>
                    </form>
                    <a class="button button-primary"
                       href="<?= e($base) ?>/student/dashboard.php">Dashboard</a>
                <?php else: ?>
                    <a class="button button-outline"
                       href="<?= e($base) ?>/auth/login.php">Login</a>
                    <a class="button button-primary"
                       href="<?= e($base) ?>/auth/register.php">Register</a>
                <?php endif; ?>
            </div>
        </nav>

        <div class="header-actions">
            <button class="theme-toggle" type="button"
                    aria-label="Switch to dark mode" aria-pressed="false"
                    title="Toggle colour theme">
                <span class="theme-icon theme-icon-moon"><?= icon('moon', 18) ?></span>
                <span class="theme-icon theme-icon-sun"><?= icon('sun', 18) ?></span>
            </button>

            <?php if (isLoggedIn()): ?>
                <form method="POST"
                      action="<?= e($base) ?>/auth/logout.php"
                      class="logout-form">
                    <input type="hidden" name="csrf_token"
                           value="<?= e(csrfToken()) ?>">
                    <button type="submit" class="button button-outline">
                        Logout
                    </button>
                </form>
                <a class="button button-primary"
                   href="<?= e($base) ?>/student/dashboard.php">Dashboard</a>
            <?php else: ?>
                <a class="button button-outline"
                   href="<?= e($base) ?>/auth/login.php">Login</a>
                <a class="button button-primary"
                   href="<?= e($base) ?>/auth/register.php">Register</a>
            <?php endif; ?>
        </div>

    </div>
</header>

<main id="main-content">