<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';

$pdo = db();

$pageTitle = 'Dashboard';

$stats = [
    'students' => 0,
    'reports' => 0,
    'pending' => 0,
    'approved' => 0,
    'resolved' => 0,
    'categories' => 0,
    'claims' => 0,
    'pendingClaims' => 0,
    'contact' => 0,
    'newContact' => 0
];

$queries = [
    'students' => "SELECT COUNT(*) FROM users WHERE role = 'student'",
    'reports' => "SELECT COUNT(*) FROM reports",
    'pending' => "SELECT COUNT(*) FROM reports WHERE status = 'pending'",
    'approved' => "SELECT COUNT(*) FROM reports WHERE status = 'approved'",
    'resolved' => "SELECT COUNT(*) FROM reports WHERE status = 'resolved'",
    'categories' => "SELECT COUNT(*) FROM categories WHERE is_active = 1",
    'claims' => "SELECT COUNT(*) FROM claims",
    'pendingClaims' => "SELECT COUNT(*) FROM claims WHERE status = 'pending'",
    'contact' => "SELECT COUNT(*) FROM contact_submissions",
    'newContact' => "SELECT COUNT(*) FROM contact_submissions WHERE status = 'new'"
];

foreach ($queries as $key => $sql) {
    $stats[$key] = (int) $pdo->query($sql)->fetchColumn();
}

/* Analytics: reports grouped by status and by type. */
$statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'resolved' => 0, 'archived' => 0];
foreach ($pdo->query("SELECT status, COUNT(*) AS c FROM reports GROUP BY status") as $row) {
    if (isset($statusCounts[$row['status']])) {
        $statusCounts[$row['status']] = (int) $row['c'];
    }
}

$typeCounts = ['lost' => 0, 'found' => 0];
foreach ($pdo->query("SELECT item_type, COUNT(*) AS c FROM reports GROUP BY item_type") as $row) {
    if (isset($typeCounts[$row['item_type']])) {
        $typeCounts[$row['item_type']] = (int) $row['c'];
    }
}

/* Analytics: reports over the last six months. */
$monthlyCounts = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthlyCounts[$month] = 0;
}
foreach ($pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c
    FROM reports
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY ym
") as $row) {
    if (isset($monthlyCounts[$row['ym']])) {
        $monthlyCounts[$row['ym']] = (int) $row['c'];
    }
}
$maxMonthly = max(1, max($monthlyCounts));

/* Analytics: reports grouped by category. */
$categoryCounts = [];
foreach ($pdo->query("
    SELECT c.name, COUNT(r.id) AS c
    FROM categories c
    LEFT JOIN reports r ON r.category_id = c.id
    WHERE c.is_active = 1
    GROUP BY c.id, c.name
    HAVING c > 0
    ORDER BY c DESC
    LIMIT 6
") as $row) {
    $categoryCounts[] = ['name' => $row['name'], 'count' => (int) $row['c']];
}
$maxCategoryCount = 1;
foreach ($categoryCounts as $entry) {
    $maxCategoryCount = max($maxCategoryCount, $entry['count']);
}

$recentReports = $pdo->query("
    SELECT r.id, r.item_name, r.item_type, r.status, r.created_at,
           u.full_name, c.name AS category_name
    FROM reports r
    INNER JOIN users u ON u.id = r.user_id
    INNER JOIN categories c ON c.id = r.category_id
    ORDER BY r.created_at DESC
    LIMIT 7
")->fetchAll(PDO::FETCH_ASSOC);

$recentActivity = $pdo->query("
    SELECT al.action, al.description, al.created_at, u.full_name
    FROM activity_logs al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$recentClaims = $pdo->query("
    SELECT cl.id, cl.status, cl.created_at, r.item_name,
           u.full_name AS claimant_name
    FROM claims cl
    INNER JOIN reports r ON r.id = cl.report_id
    INNER JOIN users u ON u.id = cl.claimant_id
    ORDER BY cl.created_at DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$recentContacts = $pdo->query("
    SELECT cs.id, cs.name, cs.email, cs.subject, cs.status, cs.created_at
    FROM contact_submissions cs
    ORDER BY cs.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="welcome-panel">
    <div>
        <span class="eyebrow">ADMINISTRATION OVERVIEW</span>
        <h2>Welcome back, <?= e($_SESSION['full_name'] ?? 'Administrator') ?></h2>
        <p>Monitor reports, manage student accounts and keep CampusFind running smoothly.</p>
    </div>

    <a class="btn btn-primary" href="<?= BASE_URL ?>/admin/reports.php">
        Review reports
    </a>
</div>

<div class="stats-grid">
    <article class="stat-card">
        <span><?= icon('users', 15) ?> Total students</span>
        <strong><?= number_format($stats['students']) ?></strong>
        <small>Registered student accounts</small>
    </article>

    <article class="stat-card">
        <span><?= icon('file', 15) ?> Total reports</span>
        <strong><?= number_format($stats['reports']) ?></strong>
        <small>All submitted reports</small>
    </article>

    <article class="stat-card stat-highlight">
        <span><?= icon('clock', 15) ?> Awaiting review</span>
        <strong><?= number_format($stats['pending']) ?></strong>
        <small>Reports requiring attention</small>
    </article>

    <article class="stat-card">
        <span><?= icon('check', 15) ?> Approved reports</span>
        <strong><?= number_format($stats['approved']) ?></strong>
        <small>Visible approved reports</small>
    </article>

    <article class="stat-card">
        <span><?= icon('check', 15) ?> Resolved reports</span>
        <strong><?= number_format($stats['resolved']) ?></strong>
        <small>Successfully resolved</small>
    </article>

    <article class="stat-card">
        <span><?= icon('tag', 15) ?> Active categories</span>
        <strong><?= number_format($stats['categories']) ?></strong>
        <small>Available report categories</small>
    </article>

    <article class="stat-card">
        <span><?= icon('box', 15) ?> Total claims</span>
        <strong><?= number_format($stats['claims']) ?></strong>
        <small>Ownership claims submitted</small>
    </article>

    <article class="stat-card stat-highlight">
        <span><?= icon('alert', 15) ?> Pending claims</span>
        <strong><?= number_format($stats['pendingClaims']) ?></strong>
        <small>Awaiting verification</small>
    </article>

    <article class="stat-card stat-highlight">
        <span><?= icon('mail', 15) ?> New contact messages</span>
        <strong><?= number_format($stats['newContact']) ?></strong>
        <small><?= number_format($stats['contact']) ?> total messages</small>
    </article>
</div>

<div class="charts-row">
    <section class="panel chart-panel">
        <div class="panel-heading">
            <div>
                <span class="eyebrow">ANALYTICS</span>
                <h2>Reports by status</h2>
            </div>
            <a href="<?= BASE_URL ?>/admin/reports.php">View all</a>
        </div>

        <?php
        $statusMeta = [
            'pending'  => ['Pending',  '#596273'],
            'approved' => ['Approved', '#E91E63'],
            'rejected' => ['Rejected', '#C2185B'],
            'resolved' => ['Resolved', '#080F2B'],
            'archived' => ['Archived', '#94A3B8'],
        ];
        $statusSegments = [];
        $statusTotal = 0;
        foreach ($statusMeta as $key => [$label, $color]) {
            $count = (int) $statusCounts[$key];
            $statusTotal += $count;
            if ($count > 0) {
                $statusSegments[] = ['label' => $label, 'value' => $count, 'color' => $color];
            }
        }
        ?>

        <?php if ($statusTotal === 0): ?>
            <p class="empty-state">No reports yet.</p>
        <?php else: ?>
            <div class="donut-wrap">
                <?= donutChart($statusSegments, 200, 34, (string) $statusTotal, 'reports') ?>
                <?= donutLegend($statusSegments) ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel chart-panel">
        <div class="panel-heading">
            <div>
                <span class="eyebrow">ANALYTICS</span>
                <h2>Lost vs found</h2>
            </div>
        </div>

        <?php
        $typeSegments = [];
        if ($typeCounts['lost'] > 0) {
            $typeSegments[] = ['label' => 'Lost', 'value' => (int) $typeCounts['lost'], 'color' => '#E91E63'];
        }
        if ($typeCounts['found'] > 0) {
            $typeSegments[] = ['label' => 'Found', 'value' => (int) $typeCounts['found'], 'color' => '#219653'];
        }
        $typeTotalCount = (int) $typeCounts['lost'] + (int) $typeCounts['found'];
        ?>

        <?php if ($typeTotalCount === 0): ?>
            <p class="empty-state">No reports yet.</p>
        <?php else: ?>
            <div class="donut-wrap">
                <?= donutChart($typeSegments, 200, 34, (string) $typeTotalCount, 'reports') ?>
                <?= donutLegend($typeSegments) ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="charts-row">
    <section class="panel chart-panel">
        <div class="panel-heading">
            <div>
                <span class="eyebrow">ANALYTICS</span>
                <h2>Reports over time</h2>
            </div>
            <span class="chart-sub">Last 6 months</span>
        </div>

        <?php if (array_sum($monthlyCounts) === 0): ?>
            <p class="empty-state">No reports in this period.</p>
        <?php else: ?>
            <?php
            $linePoints = [];
            foreach ($monthlyCounts as $month => $count) {
                $linePoints[] = [
                    'label' => date('M', strtotime($month . '-01')),
                    'value' => (int) $count,
                ];
            }
            ?>
            <div class="line-wrap">
                <?= lineChart($linePoints, 520, 240, '#E91E63') ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel chart-panel">
        <div class="panel-heading">
            <div>
                <span class="eyebrow">ANALYTICS</span>
                <h2>Reports by category</h2>
            </div>
        </div>

        <?php if (!$categoryCounts): ?>
            <p class="empty-state">No reports yet.</p>
        <?php else: ?>
            <div class="bar-chart" role="img"
                 aria-label="Report count per category">
                <?php foreach ($categoryCounts as $entry):
                    $pct = (int) round($entry['count'] / $maxCategoryCount * 100);
                    ?>
                    <div class="bar-row">
                        <span class="bar-label"><?= e($entry['name']) ?></span>
                        <span class="bar-track">
                            <span class="bar-fill fill-approved"
                                  data-width="<?= $pct ?>"
                                  style="width:<?= $pct ?>%"></span>
                        </span>
                        <span class="bar-value"><?= number_format($entry['count']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
    <section class="panel">
        <div class="panel-heading">
            <div>
                <span class="eyebrow">LATEST SUBMISSIONS</span>
                <h2>Recent reports</h2>
            </div>
            <a href="<?= BASE_URL ?>/admin/reports.php">View all</a>
        </div>

        <?php if (!$recentReports): ?>
            <p class="empty-state">No reports have been submitted yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Submitted by</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentReports as $report): ?>
                        <tr>
                            <td>
                                <strong><?= e($report['item_name']) ?></strong>
                                <small><?= e($report['category_name']) ?></small>
                            </td>
                            <td><?= e($report['full_name']) ?></td>
                            <td>
                                <span class="status-badge <?= adminStatusClass($report['status']) ?>">
                                    <?= e(ucfirst($report['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <a class="table-link"
                                   href="<?= BASE_URL ?>/admin/report-review.php?id=<?= (int) $report['id'] ?>">
                                    Review
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <span class="eyebrow">SYSTEM RECORD</span>
                <h2>Recent activity</h2>
            </div>
            <a href="<?= BASE_URL ?>/admin/activity-logs.php">View logs</a>
        </div>

        <?php if (!$recentActivity): ?>
            <p class="empty-state">No activity has been recorded yet.</p>
        <?php else: ?>
            <div class="activity-list">
                <?php foreach ($recentActivity as $activity): ?>
                    <article class="activity-item">
                        <strong><?= e($activity['action']) ?></strong>
                        <p><?= e($activity['description'] ?? '') ?></p>
                        <small>
                            <?= e($activity['full_name'] ?? 'System') ?>
                            · <?= e(formatDate($activity['created_at'])) ?>
                        </small>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="panel" style="margin-top:18px">
    <div class="panel-heading">
        <div>
            <span class="eyebrow">INBOX</span>
            <h2>Recent contact messages</h2>
        </div>
        <a href="<?= BASE_URL ?>/admin/contact-submissions.php">View all</a>
    </div>

    <?php if (!$recentContacts): ?>
        <p class="empty-state">No contact messages have been received yet.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>From</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Received</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentContacts as $contact): ?>
                    <tr>
                        <td>
                            <strong><?= e($contact['name']) ?></strong>
                            <small><?= e($contact['email']) ?></small>
                        </td>
                        <td><?= e($contact['subject']) ?></td>
                        <td>
                            <span class="status-badge <?= adminStatusClass($contact['status']) ?>">
                                <?= e(ucwords(str_replace('_', ' ', $contact['status']))) ?>
                            </span>
                        </td>
                        <td><?= e(formatDate($contact['created_at'])) ?></td>
                        <td>
                            <a class="table-link"
                               href="<?= BASE_URL ?>/admin/contact-submissions.php">Manage</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel" style="margin-top:18px">
    <div class="panel-heading">
        <div>
            <span class="eyebrow">OWNERSHIP VERIFICATION</span>
            <h2>Recent claims</h2>
        </div>
        <a href="<?= BASE_URL ?>/admin/claims.php">Review claims</a>
    </div>

    <?php if (!$recentClaims): ?>
        <p class="empty-state">No claims have been submitted yet.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Claimant</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentClaims as $claim): ?>
                    <tr>
                        <td><strong><?= e($claim['item_name']) ?></strong></td>
                        <td><?= e($claim['claimant_name']) ?></td>
                        <td>
                            <span class="status-badge <?= adminStatusClass($claim['status']) ?>">
                                <?= e(ucfirst($claim['status'])) ?>
                            </span>
                        </td>
                        <td><?= e(formatDate($claim['created_at'])) ?></td>
                        <td>
                            <a class="table-link" href="<?= BASE_URL ?>/admin/claims.php">Review</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>