<?php
declare(strict_types=1);

/**
 * Admin — Contact Submissions Directory.
 *
 * Project spec (Section C — Submissions Directory):
 *  - Structured HTML table of Contact Us messages from the database
 *  - Search / filter using GET + LIKE query
 *  - Pagination using LIMIT and OFFSET
 *  - Full CRUD: view, update (status + admin response), delete
 *  - Export to CSV (see admin/exports.php?type=contact)
 *  - All output escaped with htmlspecialchars() via e()
 */

require_once __DIR__ . '/../includes/admin_guard.php';

$pdo = db();

/* --------------------------------------------------------------------------
 * CSV export for contact submissions (file handling requirement).
 * ------------------------------------------------------------------------ */
if (($_GET['export'] ?? '') === 'csv') {
    $filename = 'campusfind-contact-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

    // Formula-injection mitigation.
    $cell = static function (mixed $value): string {
        $s = (string) ($value ?? '');
        if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $s = "'" . $s;
        }
        return $s;
    };

    fputcsv($out, ['ID', 'Name', 'Email', 'Subject', 'Message', 'Status', 'Admin response', 'Submitted']);

    $rows = $pdo->query(
        'SELECT cs.id, cs.name, cs.email, cs.subject, cs.message, cs.status,
                cs.admin_response, cs.created_at
         FROM contact_submissions cs
         ORDER BY cs.created_at DESC, cs.id DESC'
    );

    foreach ($rows as $row) {
        fputcsv($out, [
            $cell($row['id']),
            $cell($row['name']),
            $cell($row['email']),
            $cell($row['subject']),
            $cell($row['message']),
            $cell($row['status']),
            $cell($row['admin_response']),
            $cell($row['created_at']),
        ]);
    }

    fclose($out);
    exit;
}

/* --------------------------------------------------------------------------
 * CRUD: delete (POST + CSRF).
 * ------------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Your session could not be verified. Please try again.');
        redirect('/admin/contact-submissions.php');
    }

    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare('DELETE FROM contact_submissions WHERE id = ?');
        $stmt->execute([$id]);

        logActivityDb('contact_deleted', 'Deleted contact submission #' . $id);
        flash('success', 'Submission deleted.');
    } elseif ($action === 'update' && $id > 0) {
        $allowedStatuses = ['new', 'in_progress', 'resolved'];
        $status = $_POST['status'] ?? '';
        $response = trim(sanitizeInput($_POST['admin_response'] ?? ''));

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'new';
        }

        // Load the submission so we can email the sender and notify them in-app.
        $lookup = $pdo->prepare(
            'SELECT user_id, name, email, subject FROM contact_submissions WHERE id = ?'
        );
        $lookup->execute([$id]);
        $submission = $lookup->fetch(PDO::FETCH_ASSOC);

        if (!$submission) {
            flash('error', 'That submission no longer exists.');
            redirect('/admin/contact-submissions.php');
        }

        // Persist the status + response. responded_* is stamped only when there
        // is an actual reply, so an empty box clears the previous response.
        if ($response !== '') {
            $stmt = $pdo->prepare(
                'UPDATE contact_submissions
                 SET status = ?, admin_response = ?, responded_by = ?, responded_at = NOW()
                 WHERE id = ?'
            );
            $stmt->execute([$status, $response, currentUserId(), $id]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE contact_submissions
                 SET status = ?, admin_response = NULL, responded_by = NULL, responded_at = NULL
                 WHERE id = ?'
            );
            $stmt->execute([$status, $id]);
        }

        logActivityDb('contact_updated', 'Updated contact submission #' . $id);

        if ($response === '') {
            flash('success', 'Submission updated.');
            redirect('/admin/contact-submissions.php');
        }

        // Reply back to the user: email (primary channel) + in-app notification
        // when the sender is a registered student.
        $supportEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '';
        $subject = (string) $submission['subject'];
        $recipient = (string) $submission['email'];
        $senderName = (string) $submission['name'];

        $body = buildContactReplyEmail($senderName, $subject, $response, $id);
        $subjectLine = 'Re: ' . $subject . ' — CampusFind';
        $sent = $supportEmail !== ''
            ? sendMail($recipient, $subjectLine, $body, $supportEmail)
            : false;

        if (!empty($submission['user_id'])) {
            createNotification(
                (int) $submission['user_id'],
                'New reply to your message',
                'The CampusFind team replied to your message "' . $subject . '". Open My Messages to read it.',
                'contact_reply'
            );
        }

        if ($sent) {
            flash('success', 'Reply sent to ' . $recipient . '.');
        } else {
            flash('success', 'Reply saved for ' . $recipient . ' (email delivery is not configured on this server).');
        }

        redirect('/admin/contact-submissions.php');
    }

    redirect('/admin/contact-submissions.php');
}

/* --------------------------------------------------------------------------
 * List: GET search/filter + LIMIT/OFFSET pagination.
 * ------------------------------------------------------------------------ */
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

$allowedStatuses = ['new', 'in_progress', 'resolved'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 8;

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = '(cs.name LIKE :s_name OR cs.email LIKE :s_email
                  OR cs.subject LIKE :s_subject OR cs.message LIKE :s_message)';
    $term = '%' . $search . '%';
    $params['s_name']    = $term;
    $params['s_email']   = $term;
    $params['s_subject'] = $term;
    $params['s_message'] = $term;
}

if ($status !== '') {
    $where[] = 'cs.status = :status';
    $params['status'] = $status;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM contact_submissions cs $whereSql");
$countStmt->execute($params);
$totalRows  = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$sql = "SELECT cs.*, u.full_name AS user_name
        FROM contact_submissions cs
        LEFT JOIN users u ON u.id = cs.user_id
        $whereSql
        ORDER BY
            CASE cs.status WHEN 'new' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END,
            cs.created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Status counts for the summary strip (aggregate query). */
$statusCounts = ['new' => 0, 'in_progress' => 0, 'resolved' => 0];
foreach ($pdo->query('SELECT status, COUNT(*) AS c FROM contact_submissions GROUP BY status') as $r) {
    $statusCounts[$r['status']] = (int) $r['c'];
}

$pageTitle = 'Contact submissions';

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="page-intro">
    <div>
        <span class="eyebrow">SUBMISSIONS DIRECTORY</span>
        <h2>Contact messages</h2>
        <p>Every message sent through the public Contact Us form.</p>
    </div>
    <div class="intro-actions">
        <span class="result-count"><?= number_format($totalRows) ?> messages</span>
        <a class="btn btn-light btn-sm"
           href="<?= BASE_URL ?>/admin/contact-submissions.php?export=csv">
            <?= icon('download', 15) ?> Export CSV
        </a>
    </div>
</div>

<div class="stat-strip">
    <div class="stat-chip">
        <span class="stat-chip-label">New</span>
        <span class="stat-chip-value"><?= number_format($statusCounts['new']) ?></span>
    </div>
    <div class="stat-chip">
        <span class="stat-chip-label">In progress</span>
        <span class="stat-chip-value"><?= number_format($statusCounts['in_progress']) ?></span>
    </div>
    <div class="stat-chip">
        <span class="stat-chip-label">Resolved</span>
        <span class="stat-chip-value"><?= number_format($statusCounts['resolved']) ?></span>
    </div>
</div>

<section class="panel">
    <form method="GET" class="filter-form">
        <input
            type="search"
            name="search"
            placeholder="Search name, email, subject or message..."
            value="<?= e($search) ?>"
        >

        <select name="status">
            <option value="">All statuses</option>
            <?php foreach ($allowedStatuses as $option): ?>
                <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>>
                    <?= e(ucwords(str_replace('_', ' ', $option))) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-primary" type="submit">Apply filters</button>
        <a class="btn btn-light" href="<?= BASE_URL ?>/admin/contact-submissions.php">Clear</a>
    </form>

    <?php if (!$submissions): ?>
        <p class="empty-state">No contact messages match your filters.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>From</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($submissions as $row): ?>
                    <tr>
                        <td>
                            <strong><?= e($row['name']) ?></strong><br>
                            <span class="muted-cell"><?= e($row['email']) ?></span>
                        </td>
                        <td><?= e($row['subject']) ?></td>
                        <td class="message-cell">
                            <span title="<?= e($row['message']) ?>">
                                <?= e(cf_strimwidth($row['message'], 70)) ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?= e($row['status']) ?>">
                                <?= e(ucwords(str_replace('_', ' ', $row['status']))) ?>
                            </span>
                            <?php if (!empty($row['admin_response'])): ?>
                                <span class="replied-chip"><?= icon('mail', 12) ?> Replied</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e(formatDate($row['created_at'])) ?></td>
                        <td class="row-actions">
                            <details class="row-details">
                                <summary>Manage</summary>
                                <form method="POST" class="inline-edit-form">
                                    <input type="hidden" name="csrf_token"
                                           value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">

                                    <label>Status
                                        <select name="status">
                                            <?php foreach ($allowedStatuses as $option): ?>
                                                <option value="<?= e($option) ?>"
                                                    <?= $row['status'] === $option ? 'selected' : '' ?>>
                                                    <?= e(ucwords(str_replace('_', ' ', $option))) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>

                                    <label>Response
                                        <textarea name="admin_response" rows="3"
                                            placeholder="Write a reply — it is emailed to the sender"><?= e($row['admin_response'] ?? '') ?></textarea>
                                    </label>

                                    <button class="btn btn-primary btn-sm" type="submit">
                                        <?= icon('mail', 13) ?> Save &amp; email reply
                                    </button>
                                </form>

                                <form method="POST" class="inline-delete-form"
                                      onsubmit="return confirm('Delete this message permanently?');">
                                    <input type="hidden" name="csrf_token"
                                           value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                </form>

                                <?php if (!empty($row['admin_response'])): ?>
                                    <p class="admin-response-preview">
                                        <strong>Response:</strong> <?= e($row['admin_response']) ?>
                                        <?php if (!empty($row['responded_at'])): ?>
                                            <span class="replied-at">
                                                Sent <?= e(formatDate($row['responded_at'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <span>Page <?= $page ?> of <?= $totalPages ?></span>

            <div>
                <?php if ($page > 1): ?>
                    <a href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">Previous</a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>