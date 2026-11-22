<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/student_guard.php';

$pdo = db();
$userId = currentUserId();

$search = trim($_GET['search'] ?? '');
$type = $_GET['type'] ?? '';

if (!in_array($type, ['lost', 'found'], true)) {
    $type = '';
}

$where = ["r.status = 'approved'"];
$params = [];

if ($type !== '') {
    $where[] = 'r.item_type = :type';
    $params['type'] = $type;
}

if ($search !== '') {
    $where[] = '(r.item_name LIKE :sn OR r.location LIKE :sl OR r.description LIKE :sd)';
    $params['sn'] = '%' . $search . '%';
    $params['sl'] = $params['sn'];
    $params['sd'] = $params['sn'];
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM reports r $whereSql"
);
$countStmt->execute($params);
$totalReports = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalReports / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT r.id, r.item_name, r.item_type, r.location, r.incident_date,
           r.image_path, r.created_at, c.name AS category_name,
           u.full_name AS reporter_name,
           (SELECT COUNT(*) FROM claims cl
             WHERE cl.report_id = r.id AND cl.claimant_id = :me
               AND cl.status IN ('pending','approved')) AS my_claim
    FROM reports r
    INNER JOIN categories c ON c.id = r.category_id
    INNER JOIN users u ON u.id = r.user_id
    $whereSql
    ORDER BY r.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':me', $userId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

$successMessage = flash('success');
$errorMessage = flash('error');

$pageTitle = 'Browse Items';
require_once __DIR__ . '/../includes/student_header.php';
?>

<main class="student-main">
    <div class="page-heading page-heading-row">
        <div>
            <p class="eyebrow">CAMPUS COMMUNITY</p>
            <h1>Browse reported items</h1>
            <p class="muted">
                Look through approved lost and found reports. If something is yours,
                open it and submit a claim with proof.
            </p>
        </div>
        <span class="browse-count"><?= number_format($totalReports) ?> items</span>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?= e($successMessage) ?></div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="alert alert-error"><?= e($errorMessage) ?></div>
    <?php endif; ?>

    <section class="dashboard-section">
        <form method="GET" class="browse-filters">
            <input type="search" name="search" placeholder="Search item or location..."
                   value="<?= e($search) ?>">
            <select name="type">
                <option value="">Lost and found</option>
                <option value="lost" <?= $type === 'lost' ? 'selected' : '' ?>>Lost</option>
                <option value="found" <?= $type === 'found' ? 'selected' : '' ?>>Found</option>
            </select>
            <button class="btn btn-primary" type="submit">Search</button>
            <a class="btn btn-secondary" href="<?= e(BASE_URL) ?>/student/browse.php">Clear</a>
        </form>

        <?php if (!$reports): ?>
            <div class="empty-state">
                <h2>No approved items to show.</h2>
                <p>Try adjusting your filters, or check back later.</p>
            </div>
        <?php else: ?>
            <div class="browse-grid">
                <?php foreach ($reports as $report): ?>
                    <article class="browse-card">
                        <div class="browse-card-media">
                            <?php if (!empty($report['image_path'])): ?>
                                <img src="<?= e(BASE_URL . '/' . $report['image_path']) ?>"
                                     alt="<?= e($report['item_name']) ?>">
                            <?php else: ?>
                                <div class="report-thumbnail-placeholder"></div>
                            <?php endif; ?>
                            <span class="status-badge status-approved">
                                <?= e(ucfirst($report['item_type'])) ?>
                            </span>
                        </div>

                        <div class="browse-card-body">
                            <h3><?= e($report['item_name']) ?></h3>
                            <p class="browse-meta">
                                <span><?= e($report['category_name'] ?? 'Uncategorised') ?></span>
                                <span>·</span>
                                <span><?= e($report['location']) ?></span>
                            </p>
                            <p class="browse-meta muted">
                                Reported by <?= e($report['reporter_name']) ?>
                                on <?= e(date('d M Y', strtotime($report['created_at']))) ?>
                            </p>

                            <?php if ((int) $report['my_claim'] > 0): ?>
                                <span class="browse-claimed">You have a claim on this item</span>
                            <?php else: ?>
                                <a class="btn btn-primary btn-block"
                                   href="<?= e(BASE_URL) ?>/student/claim.php?id=<?= (int) $report['id'] ?>">
                                    View &amp; claim
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
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
</main>

<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>