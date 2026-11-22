<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Stage 4.1: access guard and shared helpers for the student portal.
 *
 * A student page must be loaded by a logged-in user with the student role.
 * Admin accounts are redirected to the admin area, and suspended accounts
 * are forced back to the login screen.
 */

if (!isLoggedIn()) {
    redirect('/auth/login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'student') {
    if (($_SESSION['role'] ?? '') === 'admin') {
        redirect('/admin/dashboard.php');
    } else {
        redirect('/auth/login.php');
    }
    exit;
}

if (!accountIsActive()) {
    $_SESSION = [];
    session_destroy();
    redirect('/auth/login.php');
    exit;
}

/**
 * Count characters in a UTF-8 string without relying on the mbstring
 * extension (not installed on all hosts). Falls back to a byte count when
 * the value is not valid UTF-8.
 */
function student_text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    $count = preg_match_all('/./us', $value, $matches);

    return $count === false ? strlen($value) : $count;
}

/**
 * Map a report status to its CSS badge modifier class.
 */
function student_status_class(string $status): string
{
    return match ($status) {
        'approved' => 'status-approved',
        'resolved' => 'status-resolved',
        'rejected' => 'status-rejected',
        'archived' => 'status-archived',
        default => 'status-pending',
    };
}

/**
 * Stage 4: validate the text fields of a lost/found report.
 *
 * Returns ['errors' => string[], 'clean' => array]. Image handling is left
 * to the calling page because create and edit treat uploads differently.
 */
function validateReportInput(array $input): array
{
    $errors = [];

    $clean = [
        'item_name'      => trim((string) ($input['item_name'] ?? '')),
        'item_type'      => (string) ($input['item_type'] ?? 'lost'),
        'category_id'    => (int) filter_var($input['category_id'] ?? '', FILTER_VALIDATE_INT),
        'description'    => trim((string) ($input['description'] ?? '')),
        'location'       => trim((string) ($input['location'] ?? '')),
        'incident_date'  => trim((string) ($input['incident_date'] ?? '')),
        'contact_phone'  => trim((string) ($input['contact_phone'] ?? '')),
        'contact_email'  => trim((string) ($input['contact_email'] ?? '')),
    ];

    if ($clean['item_name'] === '' || student_text_length($clean['item_name']) > 150) {
        $errors[] = 'Enter an item name of no more than 150 characters.';
    }

    if (!in_array($clean['item_type'], ['lost', 'found'], true)) {
        $errors[] = 'Select whether the item was lost or found.';
    }

    if (!$clean['category_id']) {
        $errors[] = 'Select a valid category.';
    } else {
        $pdo = db();

        if ($pdo) {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
            $stmt->execute([$clean['category_id']]);

            if (!$stmt->fetch()) {
                $errors[] = 'The selected category does not exist.';
            }
        }
    }

    if ($clean['description'] === '' || student_text_length($clean['description']) > 5000) {
        $errors[] = 'Enter a description of no more than 5,000 characters.';
    }

    if ($clean['location'] === '' || student_text_length($clean['location']) > 180) {
        $errors[] = 'Enter the campus location where the item was lost or found.';
    }

    $incidentDate = DateTime::createFromFormat('Y-m-d', $clean['incident_date']);
    $dateIsValid = $incidentDate &&
        $incidentDate->format('Y-m-d') === $clean['incident_date'];

    if (!$dateIsValid) {
        $errors[] = 'Enter a valid date.';
    } elseif ($incidentDate > new DateTime('today')) {
        $errors[] = 'The item date cannot be in the future.';
    }

    if (
        $clean['contact_email'] !== '' &&
        !filter_var($clean['contact_email'], FILTER_VALIDATE_EMAIL)
    ) {
        $errors[] = 'Enter a valid contact email address.';
    }

    if (student_text_length($clean['contact_phone']) > 30) {
        $errors[] = 'The contact phone number is too long.';
    }

    return ['errors' => $errors, 'clean' => $clean];
}