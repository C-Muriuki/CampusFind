<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/student_guard.php';

$userId = currentUserId();

$userStmt = $pdo->prepare(
    "SELECT id, full_name, email, student_number, phone
     FROM users
     WHERE id = :id
     LIMIT 1"
);
$userStmt->execute(['id' => $userId]);
$user = $userStmt->fetch();

if (!$user) {
    session_destroy();
    redirect('/auth/login.php');
    exit;
}

$statsStmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_reports,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_reports,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_reports,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_reports
     FROM reports
     WHERE user_id = :user_id"
);
$statsStmt->execute(['user_id' => $userId]);
$stats = $statsStmt->fetch() ?: [];

$recentStmt = $pdo->prepare(
    "SELECT
        r.id,
        r.item_name,
        r.item_type,
        r.location,
        r.status,
        r.created_at,
        r.image_path,
        c.name AS category_name
     FROM reports r
     LEFT JOIN categories c ON c.id = r.category_id
     WHERE r.user_id = :user_id
     ORDER BY r.created_at DESC
     LIMIT 5"
);
$recentStmt->execute(['user_id' => $userId]);
$recentReports = $recentStmt->fetchAll();

$claimsOnMineStmt = $pdo->prepare(
    "SELECT cl.id, cl.status, cl.proof, cl.created_at, r.item_name,
            u.full_name AS claimant_name
     FROM claims cl
     INNER JOIN reports r ON r.id = cl.report_id
     INNER JOIN users u ON u.id = cl.claimant_id
     WHERE r.user_id = :user_id
     ORDER BY cl.created_at DESC
     LIMIT 5"
);
$claimsOnMineStmt->execute(['user_id' => $userId]);
$claimsOnMine = $claimsOnMineStmt->fetchAll();

$incomingCountStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM claims cl
     INNER JOIN reports r ON r.id = cl.report_id
     WHERE r.user_id = :user_id AND cl.status IN ('pending','approved')"
);
$incomingCountStmt->execute(['user_id' => $userId]);
$incomingCount = (int) $incomingCountStmt->fetchColumn();

$myActiveStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM claims
     WHERE claimant_id = :user_id AND status IN ('pending','approved')"
);
$myActiveStmt->execute(['user_id' => $userId]);
$myActiveCount = (int) $myActiveStmt->fetchColumn();

/* Status breakdown for the mini chart. */
$myStatusCounts = ['pending' => 0, 'approved' => 0, 'resolved' => 0, 'rejected' => 0];
foreach ($pdo->query(
    "SELECT status, COUNT(*) AS c FROM reports WHERE user_id = " . (int) $userId . " GROUP BY status"
) as $row) {
    if (isset($myStatusCounts[$row['status']])) {
        $myStatusCounts[$row['status']] = (int) $row['c'];
    }
}
$myTotal = max(1, array_sum($myStatusCounts));

$pageTitle = 'Student Dashboard';
require_once __DIR__ . '/../includes/student_header.php';
?>

<main class="student-main">
    <?php
    $flashSuccess = flash('success');
    $flashError = flash('error');
    ?>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success"><?= e($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-error"><?= e($flashError) ?></div>
    <?php endif; ?>

    <section class="welcome-section">
        <div>
            <p class="eyebrow">STUDENT PORTAL</p>
            <h1>Welcome back, <?= e(explode(' ', trim($user['full_name']))[0]) ?>.</h1>
            <p class="muted">
                Keep track of your lost and found item reports.
            </p>
        </div>

        <div class="welcome-actions">
            <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/student/report-create.php">
                <?= icon('plus', 17) ?> Report an Item
            </a>
            <a class="btn btn-secondary" href="<?= e(BASE_URL) ?>/student/browse.php">
                <?= icon('search', 17) ?> Browse Items
            </a>
        </div>
    </section>

    <section class="stats-grid" aria-label="Your report statistics">
        <article class="stat-card">
            <span class="stat-label">Total reports</span>
            <strong><?= (int) ($stats['total_reports'] ?? 0) ?></strong>
            <span class="stat-description">All your submissions</span>
        </article>

        <article class="stat-card">
            <span class="stat-label">Pending</span>
            <strong><?= (int) ($stats['pending_reports'] ?? 0) ?></strong>
            <span class="stat-description">Awaiting review</span>
        </article>

        <article class="stat-card">
            <span class="stat-label">Approved</span>
            <strong><?= (int) ($stats['approved_reports'] ?? 0) ?></strong>
            <span class="stat-description">Visible to students</span>
        </article>

        <article class="stat-card">
            <span class="stat-label">Resolved</span>
            <strong><?= (int) ($stats['resolved_reports'] ?? 0) ?></strong>
            <span class="stat-description">Items recovered or returned</span>
        </article>

        <article class="stat-card">
            <span class="stat-label">Claims on your items</span>
            <strong><?= (int) $incomingCount ?></strong>
            <span class="stat-description">People claiming your items</span>
        </article>

        <article class="stat-card">
            <span class="stat-label">My active claims</span>
            <strong><?= (int) $myActiveCount ?></strong>
            <span class="stat-description">Items you are claiming</span>
        </article>
    </section>

    <?php if ((int) ($stats['total_reports'] ?? 0) > 0): ?>
        <section class="dashboard-section" aria-label="Your report status breakdown">
            <div class="section-heading">
                <div>
                    <h2>Your report progress</h2>
                    <p class="muted">How your submissions are moving through review.</p>
                </div>
            </div>

            <div class="my-progress" role="img"
                 aria-label="Status breakdown of your reports">
                <?php
                $progressLabels = [
                    'approved' => ['Approved', 'fill-approved'],
                    'pending'  => ['Pending', 'fill-pending'],
                    'resolved' => ['Resolved', 'fill-resolved'],
                    'rejected' => ['Rejected', 'fill-rejected'],
                ];
                $maxMy = max(1, max($myStatusCounts));
                foreach ($progressLabels as $key => [$label, $cls]):
                    $pct = (int) round($myStatusCounts[$key] / $maxMy * 100);
                    ?>
                    <div class="bar-row">
                        <span class="bar-label"><?= e($label) ?></span>
                        <span class="bar-track">
                            <span class="bar-fill <?= e($cls) ?>"
                                  style="width:<?= $pct ?>%"></span>
                        </span>
                        <span class="bar-value"><?= (int) $myStatusCounts[$key] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="dashboard-section" aria-label="Your recent reports">
        <div class="section-heading">
            <div>
                <h2>Recent reports</h2>
                <p class="muted">Your latest lost and found submissions.</p>
            </div>
            <a class="text-link" href="<?= e(BASE_URL) ?>/student/my-reports.php">
                View all reports
            </a>
        </div>

        <?php if (!$recentReports): ?>
            <div class="empty-state">
                <h3>No reports yet</h3>
                <p>
                    When you report a lost or found item, it will appear here.
                </p>
                <a class="btn btn-primary"
                   href="<?= e(BASE_URL) ?>/student/report-create.php">
                    Submit your first report
                </a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Date submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentReports as $report): ?>
                            <tr>
                                <td>
                                    <div class="report-cell">
                                        <?php if (!empty($report['image_path'])): ?>
                                            <img class="report-thumb"
                                                 src="<?= e(rtrim(BASE_URL, '/') . '/' . $report['image_path']) ?>"
                                                 alt="<?= e($report['item_name']) ?>">
                                        <?php else: ?>
                                            <span class="report-thumb report-thumb-empty"
                                                  aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <span class="report-cell-text">
                                            <strong><?= e($report['item_name']) ?></strong>
                                            <small><?= e($report['location']) ?></small>
                                        </span>
                                    </div>
                                </td>
                                <td><?= e(ucfirst($report['item_type'])) ?></td>
                                <td><?= e($report['category_name'] ?? 'Uncategorised') ?></td>
                                <td>
                                    <span class="status-badge <?= e(student_status_class($report['status'])) ?>">
                                        <?= e(ucfirst($report['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= e(date('d M Y', strtotime($report['created_at']))) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="dashboard-section">
        <div class="section-heading">
            <div>
                <h2>Claims on your items</h2>
                <p class="muted">People who say these items belong to them.</p>
            </div>
            <a class="text-link" href="<?= e(BASE_URL) ?>/student/my-claims.php">
                View all claims
            </a>
        </div>

        <?php if (!$claimsOnMine): ?>
            <div class="empty-state">
                <p>No one has claimed your items yet.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>My item</th>
                            <th>Claimant</th>
                            <th>Proof</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($claimsOnMine as $claim): ?>
                        <tr>
                            <td><strong><?= e($claim['item_name']) ?></strong></td>
                            <td><?= e($claim['claimant_name']) ?></td>
                            <td class="claim-outcome"><?= e($claim['proof']) ?></td>
                            <td>
                                <span class="status-badge <?= e(student_status_class($claim['status'])) ?>">
                                    <?= e(ucfirst($claim['status'])) ?>
                                </span>
                            </td>
                            <td><?= e(date('d M Y', strtotime($claim['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="dashboard-section">
        <div class="section-heading">
            <div>
                <h2>Quick actions</h2>
                <p class="muted">Manage your CampusFind activity.</p>
            </div>
        </div>

        <div class="quick-actions">
            <a href="<?= e(BASE_URL) ?>/student/report-create.php">
                <strong>Report an item</strong>
                <span>Submit a lost or found item.</span>
            </a>

            <a href="<?= e(BASE_URL) ?>/student/my-reports.php">
                <strong>My submissions</strong>
                <span>View and manage your reports.</span>
            </a>

            <a href="<?= e(BASE_URL) ?>/student/profile.php">
                <strong>My profile</strong>
                <span>Update your contact information.</span>
            </a>

            <a href="<?= e(BASE_URL) ?>/student/notifications.php">
                <strong>Notifications</strong>
                <span>Check updates about your reports.</span>
            </a>

            <a href="<?= e(BASE_URL) ?>/student/browse.php">
                <strong>Browse items</strong>
                <span>Find and claim reported items.</span>
            </a>

            <a href="<?= e(BASE_URL) ?>/student/my-claims.php">
                <strong>My claims</strong>
                <span>Track your ownership claims.</span>
            </a>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>