<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';

$pdo = db();

/**
 * Escape a value for CSV output, neutralising spreadsheet formula injection.
 */
function csvCell(mixed $value): string
{
    $string = (string) ($value ?? '');

    if ($string !== '' && in_array($string[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        $string = "'" . $string;
    }

    return $string;
}

$type = $_GET['type'] ?? '';
$allowedExports = ['reports', 'users', 'activity', 'contact'];

/* Stream the CSV download when a valid type is requested. */
if ($type !== '' && in_array($type, $allowedExports, true)) {
    $filename = 'campusfind-' . $type . '-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

    if ($type === 'reports') {
        fputcsv($out, [
            'ID', 'Item', 'Type', 'Category', 'Status', 'Location',
            'Incident date', 'Student', 'Student email', 'Reviewed by', 'Reviewed at', 'Submitted at'
        ]);

        $rows = $pdo->query(
            "SELECT r.id, r.item_name, r.item_type, c.name AS category_name, r.status,
                    r.location, r.incident_date, u.full_name, u.email,
                    reviewer.full_name AS reviewer_name, r.reviewed_at, r.created_at
             FROM reports r
             INNER JOIN users u ON u.id = r.user_id
             INNER JOIN categories c ON c.id = r.category_id
             LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by
             ORDER BY r.created_at DESC"
        );

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['id'], $row['item_name'], $row['item_type'], $row['category_name'],
                $row['status'], $row['location'], $row['incident_date'], $row['full_name'],
                $row['email'], $row['reviewer_name'] ?? '', $row['reviewed_at'] ?? '', $row['created_at']
            ]);
        }
    } elseif ($type === 'users') {
        fputcsv($out, [
            'ID', 'Full name', 'Email', 'Student number', 'Phone', 'Role',
            'Account status', 'Reports', 'Last login', 'Joined'
        ]);

        $rows = $pdo->query(
            "SELECT u.id, u.full_name, u.email, u.student_number, u.phone, u.role,
                    u.account_status, u.last_login_at, u.created_at,
                    (SELECT COUNT(*) FROM reports r WHERE r.user_id = u.id) AS reports_count
             FROM users u
             ORDER BY u.created_at DESC"
        );

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['id'], $row['full_name'], $row['email'], $row['student_number'] ?? '',
                $row['phone'] ?? '', $row['role'], $row['account_status'], $row['reports_count'],
                $row['last_login_at'] ?? '', $row['created_at']
            ]);
        }
    } elseif ($type === 'contact') {
        fputcsv($out, ['ID', 'Name', 'Email', 'Subject', 'Message', 'Status', 'Admin response', 'Submitted']);

        $rows = $pdo->query(
            "SELECT id, name, email, subject, message, status, admin_response, created_at
             FROM contact_submissions
             ORDER BY created_at DESC, id DESC"
        );

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['id'], $row['name'], $row['email'], $row['subject'], $row['message'],
                $row['status'], $row['admin_response'] ?? '', $row['created_at']
            ]);
        }
    } else {
        fputcsv($out, ['ID', 'When', 'User', 'Email', 'Action', 'Details', 'IP address']);

        $rows = $pdo->query(
            "SELECT al.id, al.created_at, al.action, al.description, al.ip_address,
                    u.full_name, u.email
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC, al.id DESC"
        );

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['id'], $row['created_at'], $row['full_name'] ?? 'System',
                $row['email'] ?? '', $row['action'], $row['description'] ?? '', $row['ip_address'] ?? ''
            ]);
        }
    }

    fclose($out);
    exit;
}

$pageTitle = 'Data exports';

$reportCount = (int) $pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn();
$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$logCount = (int) $pdo->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn();
$contactCount = (int) $pdo->query('SELECT COUNT(*) FROM contact_submissions')->fetchColumn();

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="page-intro">
    <div>
        <span class="eyebrow">DATA PORTABILITY</span>
        <h2>Export data</h2>
        <p>Download CSV snapshots for reporting, backups or analysis.</p>
    </div>
</div>

<div class="export-grid">
    <article class="export-card">
        <h3>Reports</h3>
        <p><?= number_format($reportCount) ?> reports with student, category, status and reviewer details.</p>
        <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/admin/exports.php?type=reports">
            Download reports CSV
        </a>
    </article>

    <article class="export-card">
        <h3>Users</h3>
        <p><?= number_format($userCount) ?> accounts with role, status, report counts and login history.</p>
        <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/admin/exports.php?type=users">
            Download users CSV
        </a>
    </article>

    <article class="export-card">
        <h3>Activity logs</h3>
        <p><?= number_format($logCount) ?> audit entries with user, action and IP address.</p>
        <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/admin/exports.php?type=activity">
            Download activity CSV
        </a>
    </article>

    <article class="export-card">
        <h3>Contact submissions</h3>
        <p><?= number_format($contactCount) ?> messages from the public Contact Us form.</p>
        <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/admin/exports.php?type=contact">
            Download contact CSV
        </a>
    </article>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>