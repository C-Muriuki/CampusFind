<?php
declare(strict_types=1);

/**
 * Stage 4.1: shared student portal header.
 *
 * Expects bootstrap.php (via student_guard.php) to have been loaded first.
 */

$pageTitle = $pageTitle ?? 'Student Portal';
$currentFile = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e(CURRENT_THEME) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="CampusFind student portal for reporting and recovering lost items.">
    <meta name="theme-color" content="<?= CURRENT_THEME === 'dark' ? '#080F2B' : '#FFFFFF' ?>">
    <title><?= e($pageTitle) ?> | CampusFind</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/student.css">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/stage8-polish.css">

    <script src="<?= e(BASE_URL) ?>/assets/js/theme.js" defer></script>
    <script src="<?= e(BASE_URL) ?>/assets/js/auth.js" defer></script>
</head>
<body>

<header class="student-header">
    <div class="student-nav">
        <a class="student-brand" href="<?= e(BASE_URL) ?>/student/dashboard.php">
            <img class="brand-logo"
                 src="<?= e(BASE_URL) ?>/assets/images/campusfind-backpack-search.png"
                 alt="CampusFind logo">
            <span>CampusFind</span>
        </a>

        <nav class="student-nav-links" id="studentNav" aria-label="Student navigation">
            <a class="<?= $currentFile === 'dashboard.php' ? 'active' : '' ?>"
               href="<?= e(BASE_URL) ?>/student/dashboard.php">Dashboard</a>

            <a class="<?= $currentFile === 'my-reports.php' ? 'active' : '' ?>"
               href="<?= e(BASE_URL) ?>/student/my-reports.php">My Reports</a>

            <a class="<?= $currentFile === 'browse.php' ? 'active' : '' ?>"
               href="<?= e(BASE_URL) ?>/student/browse.php">Browse Items</a>

            <a class="<?= in_array($currentFile, ['claim.php', 'my-claims.php'], true) ? 'active' : '' ?>"
               href="<?= e(BASE_URL) ?>/student/my-claims.php">My Claims</a>

            <a class="<?= $currentFile === 'report-create.php' ? 'active' : '' ?>"
               href="<?= e(BASE_URL) ?>/student/report-create.php">Report Item</a>

            <a class="<?= $currentFile === 'notifications.php' ? 'active' : '' ?>"
               href="<?= e(BASE_URL) ?>/student/notifications.php">Notifications</a>

            <a class="<?= $currentFile === 'my-messages.php' ? 'active' : '' ?>"
               href="<?= e(BASE_URL) ?>/student/my-messages.php">My Messages</a>

            <a class="<?= $currentFile === 'profile.php' ? 'active' : '' ?>"
               href="<?= e(BASE_URL) ?>/student/profile.php">Profile</a>
        </nav>

        <div class="student-nav-account">
            <button class="theme-toggle" type="button"
                    aria-label="Switch to dark mode" aria-pressed="false">
                Dark mode
            </button>

            <span><?= e($_SESSION['full_name'] ?? 'Student') ?></span>

            <form action="<?= e(BASE_URL) ?>/auth/logout.php" method="POST">
                <input type="hidden" name="csrf_token"
                       value="<?= e(csrfToken()) ?>">
                <button type="submit" class="logout-link">Log out</button>
            </form>

            <button class="menu-toggle student-menu-toggle" type="button"
                    aria-label="Open navigation" aria-expanded="false"
                    aria-controls="studentNav">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>