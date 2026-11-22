<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';

$pdo = db();

$pageTitle = 'Report moderation';

$allowedStatuses = ['pending', 'approved', 'rejected', 'resolved', 'archived'];
$allowedTypes = ['lost', 'found'];

$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$search = trim($_GET['search'] ?? '');

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

if (!in_array($type, $allowedTypes, true)) {
    $type = '';
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$where = [];
$params = [];

if ($status !== '') {
    $where[] = 'r.status = :status';
    $params['status'] = $status;
}

if ($type !== '') {
    $where[] = 'r.item_type = :type';
    $params['type'] = $type;
}

if ($search !== '') {
    $where[] = '(
        r.item_name LIKE :search_name
        OR r.location LIKE :search_location
        OR u.full_name LIKE :search_student
    )';

    $term = '%' . $search . '%';
    $params['search_name'] = $term;
    $params['search_location'] = $term;
    $params['search_student'] = $term;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reports r
    INNER JOIN users u ON u.id = r.user_id
    $whereSql
");
$countStmt->execute($params);
$totalReports = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalReports / $perPage));

$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT r.id, r.item_name, r.item_type, r.location, r.status, r.created_at,
           u.full_name, c.name AS category_name
    FROM reports r
    INNER JOIN users u ON u.id = r.user_id
    INNER JOIN categories c ON c.id = r.category_id
    $whereSql
    ORDER BY
        CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END,
        r.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}

$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="page-intro">
    <div>
        <span class="eyebrow">CONTENT MANAGEMENT</span>
        <h2>Submitted reports</h2>
        <p>Review submissions before they become visible to the campus community.</p>
    </div>
    <span class="result-count"><?= number_format($totalReports) ?> reports</span>
</div>

<section class="panel">
    <form method="GET" class="filter-form">
        <input
            type="search"
            name="search"
            placeholder="Search item, location or student..."
            value="<?= e($search) ?>"
        >

        <select name="status">
            <option value="">All statuses</option>
            <?php foreach ($allowedStatuses as $option): ?>
                <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>>
                    <?= e(ucfirst($option)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="type">
            <option value="">Lost and found</option>
            <option value="lost" <?= $type === 'lost' ? 'selected' : '' ?>>Lost</option>
            <option value="found" <?= $type === 'found' ? 'selected' : '' ?>>Found</option>
        </select>

        <button class="btn btn-primary" type="submit">Apply filters</button>
        <a class="btn btn-light" href="<?= BASE_URL ?>/admin/reports.php">Clear</a>
    </form>

    <?php if (!$reports): ?>
        <p class="empty-state">No reports match your filters.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Student</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($reports as $report): ?>
                    <tr>
                        <td><strong><?= e($report['item_name']) ?></strong></td>
                        <td><?= e(ucfirst($report['item_type'])) ?></td>
                        <td><?= e($report['category_name']) ?></td>
                        <td><?= e($report['full_name']) ?></td>
                        <td><?= e($report['location']) ?></td>
                        <td>
                            <span class="status-badge <?= adminStatusClass($report['status']) ?>">
                                <?= e(ucfirst($report['status'])) ?>
                            </span>
                        </td>
                        <td><?= e(formatDate($report['created_at'])) ?></td>
                        <td>
                            <a class="table-link"
                               href="<?= BASE_URL ?>/admin/report-review.php?id=<?= (int) $report['id'] ?>">
                                Open
                            </a>
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