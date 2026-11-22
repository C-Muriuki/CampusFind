<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Stage 5: administrator access guard + shared helpers.
 * Prevents students and logged-out visitors from reaching admin pages.
 */

if (!function_exists('adminTextLength')) {
    /**
     * UTF-8 safe character count without the mbstring extension.
     * Falls back to byte length if the string is not valid UTF-8.
     */
    function adminTextLength(string $value): int
    {
        if ($value === '') {
            return 0;
        }

        $count = preg_match_all('/./us', $value, $matches);

        return $count === false ? strlen($value) : $count;
    }
}

if (!function_exists('adminDateTime')) {
    /**
     * Format a datetime for the admin tables, tolerating empty values.
     */
    function adminDateTime(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? '—' : date('d M Y, H:i', $timestamp);
    }
}

if (!isLoggedIn()) {
    redirect('/auth/login.php');
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    if (($_SESSION['role'] ?? '') === 'student') {
        redirect('/student/dashboard.php');
    }

    redirect('/auth/login.php');
}

if (!accountIsActive()) {
    $_SESSION = [];
    session_destroy();

    redirect('/auth/login.php');
}