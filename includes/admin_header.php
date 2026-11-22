<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_guard.php';

$pageTitle = $pageTitle ?? 'Administrator';
$currentAdminName = $_SESSION['full_name'] ?? 'Administrator';

if (!function_exists('adminStatusClass')) {
    function adminStatusClass(string $status): string
    {
        return match ($status) {
            'pending', 'new' => 'status-pending',
            'approved', 'in_progress' => 'status-approved',
            'rejected' => 'status-rejected',
            'resolved' => 'status-resolved',
            'archived' => 'status-archived',
            default => 'status-default'
        };
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e(CURRENT_THEME) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="<?= CURRENT_THEME === 'dark' ? '#080F2B' : '#FFFFFF' ?>">
    <title><?= e($pageTitle) ?> | CampusFind Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/stage8-polish.css">
    <script src="<?= BASE_URL ?>/assets/js/theme.js" defer></script>
</head>
<body class="admin-body">

<div class="admin-layout">
    <aside class="admin-sidebar" id="adminSidebar">
        <a class="admin-brand" href="<?= BASE_URL ?>/admin/dashboard.php">
            <img class="brand-logo"
                 src="<?= BASE_URL ?>/assets/images/campusfind-backpack-search.png"
                 alt="CampusFind logo">
            <span>CampusFind <small>ADMINISTRATION</small></span>
        </a>

        <nav class="admin-navigation">
            <p class="nav-heading">WORKSPACE</p>

            <a href="<?= BASE_URL ?>/admin/dashboard.php"
               class="<?= currentPage() === 'dashboard.php' ? 'active' : '' ?>">
                Dashboard
            </a>

            <a href="<?= BASE_URL ?>/admin/reports.php"
               class="<?= in_array(currentPage(), ['reports.php', 'report-review.php'], true) ? 'active' : '' ?>">
                Report moderation
            </a>

            <a href="<?= BASE_URL ?>/admin/claims.php"
               class="<?= currentPage() === 'claims.php' ? 'active' : '' ?>">
                Item claims
            </a>

            <p class="nav-heading">MANAGEMENT</p>

            <a href="<?= BASE_URL ?>/admin/users.php"
               class="<?= currentPage() === 'users.php' ? 'active' : '' ?>">
                Student accounts
            </a>

            <a href="<?= BASE_URL ?>/admin/categories.php"
               class="<?= currentPage() === 'categories.php' ? 'active' : '' ?>">
                Categories
            </a>

            <a href="<?= BASE_URL ?>/admin/contact-submissions.php"
               class="<?= currentPage() === 'contact-submissions.php' ? 'active' : '' ?>">
                Contact submissions
            </a>

            <p class="nav-heading">SYSTEM</p>

            <a href="<?= BASE_URL ?>/admin/activity-logs.php"
               class="<?= currentPage() === 'activity-logs.php' ? 'active' : '' ?>">
                Activity logs
            </a>

            <a href="<?= BASE_URL ?>/admin/exports.php"
               class="<?= currentPage() === 'exports.php' ? 'active' : '' ?>">
                Data exports
            </a>
        </nav>

        <div class="admin-sidebar-bottom">
            <span><?= e($currentAdminName) ?></span>
            <small>Administrator</small>

            <form action="<?= BASE_URL ?>/auth/logout.php" method="POST">
                <input type="hidden" name="csrf_token"
                       value="<?= e(csrfToken()) ?>">
                <button type="submit" class="sidebar-logout">Sign out</button>
            </form>
        </div>
    </aside>

    <main class="admin-main">
        <header class="admin-topbar">
            <div class="admin-topbar-head">
                <button class="menu-toggle admin-menu-toggle" type="button"
                        aria-label="Open navigation" aria-expanded="false"
                        aria-controls="adminSidebar">
                    <span></span><span></span><span></span>
                </button>
                <div>
                    <span class="topbar-label">CAMPUSFIND / ADMIN</span>
                    <h1><?= e($pageTitle) ?></h1>
                </div>
            </div>

            <div class="admin-topbar-actions">
                <button class="theme-toggle" type="button"
                        aria-label="Switch to dark mode" aria-pressed="false">
                    Dark mode
                </button>

                <a class="topbar-link" href="<?= BASE_URL ?>/index.php">
                    View website
                </a>
            </div>
        </header>
        <div class="nav-backdrop" aria-hidden="true"></div>

        <section class="admin-content">
            <?php if ($success = flash('success')): ?>
                <div class="alert alert-success"><?= e($success) ?></div>
            <?php endif; ?>

            <?php if ($error = flash('error')): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>