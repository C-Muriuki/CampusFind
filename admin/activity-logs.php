<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';

$pdo = db();

$pageTitle = 'Activity logs';

$search = trim($_GET['search'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(al.action LIKE :a OR al.description LIKE :d OR u.full_name LIKE :n OR u.email LIKE :e)';
    $params['a'] = '%' . $search . '%';
    $params['d'] = $params['a'];
    $params['n'] = $params['a'];
    $params['e'] = $params['a'];
}

if ($actionFilter !== '') {
    $where[] = 'al.action = :action';
    $params['action'] = $actionFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$actions = $pdo->query(
    'SELECT DISTINCT action FROM activity_logs ORDER BY action ASC'
)->fetchAll(PDO::FETCH_COLUMN);

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id $whereSql"
);
$countStmt->execute($params);
$totalLogs = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalLogs / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT al.id, al.action, al.description, al.ip_address, al.created_at,
           u.full_name, u.email
    FROM activity_logs al
    LEFT JOIN users u ON u.id = al.user_id
    $whereSql
    ORDER BY al.created_at DESC, al.id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="page-intro">
    <div>
        <span class="eyebrow">SYSTEM RECORD</span>
        <h2>Activity logs</h2>
        <p>Audit trail of sign-ins, moderation, account and category changes.</p>
    </div>
    <div class="row-actions">
        <a class="btn btn-light" href="<?= BASE_URL ?>/admin/exports.php?type=activity">Export CSV</a>
        <span class="result-count"><?= number_format($totalLogs) ?> entries</span>
    </div>
</div>

<section class="panel">
    <form method="GET" class="filter-form">
        <input
            type="search"
            name="search"
            placeholder="Search action, description or user..."
            value="<?= e($search) ?>"
        >

        <select name="action">
            <option value="">All actions</option>
            <?php foreach ($actions as $action): ?>
                <option value="<?= e($action) ?>" <?= $actionFilter === $action ? 'selected' : '' ?>>
                    <?= e($action) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-primary" type="submit">Apply filters</button>
        <a class="btn btn-light" href="<?= BASE_URL ?>/admin/activity-logs.php">Clear</a>
    </form>

    <?php if (!$logs): ?>
        <p class="empty-state">No activity matches your filters.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP address</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td style="white-space:nowrap"><?= e(adminDateTime($log['created_at'])) ?></td>
                        <td>
                            <?php if ($log['full_name']): ?>
                                <strong><?= e($log['full_name']) ?></strong>
                                <small><?= e($log['email']) ?></small>
                            <?php else: ?>
                                <span class="muted-cell">System</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-archived"><?= e($log['action']) ?></span>
                        </td>
                        <td style="white-space:normal;max-width:420px"><?= e($log['description'] ?? '') ?></td>
                        <td class="muted-cell"><?= e($log['ip_address'] ?? '—') ?></td>
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