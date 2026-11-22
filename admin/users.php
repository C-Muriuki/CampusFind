<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';

$pdo = db();

$pageTitle = 'Student accounts';

/* Handle suspend / reactivate actions. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Your session could not be verified. Please try again.');
        redirect('/admin/users.php');
    }

    $action = $_POST['action'] ?? '';
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$userId || !in_array($action, ['suspend', 'reactivate'], true)) {
        flash('error', 'Invalid account action.');
        redirect('/admin/users.php');
    }

    if ($userId === currentUserId()) {
        flash('error', 'You cannot change the status of your own account.');
        redirect('/admin/users.php');
    }

    $target = fetchUserById($userId);

    if (!$target || $target['role'] !== 'student') {
        flash('error', 'Only student accounts can be managed here.');
        redirect('/admin/users.php');
    }

    if ($action === 'suspend') {
        $pdo->prepare(
            "UPDATE users SET account_status = 'suspended', updated_at = NOW()
             WHERE id = ? AND role = 'student'"
        )->execute([$userId]);
        logActivityDb('suspend_user', 'Suspended student account #' . $userId . ' (' . $target['email'] . ')');
        flash('success', 'Student account suspended.');
    } else {
        $pdo->prepare(
            "UPDATE users SET account_status = 'active', updated_at = NOW()
             WHERE id = ? AND role = 'student'"
        )->execute([$userId]);
        logActivityDb('reactivate_user', 'Reactivated student account #' . $userId . ' (' . $target['email'] . ')');
        flash('success', 'Student account reactivated.');
    }

    redirect('/admin/users.php');
}

/* Filters + pagination. */
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

if (!in_array($status, ['active', 'suspended'], true)) {
    $status = '';
}

$where = ["u.role = 'student'"];
$params = [];

if ($search !== '') {
    $where[] = '(u.full_name LIKE :sn OR u.email LIKE :se OR u.student_number LIKE :stn)';
    $params['sn'] = '%' . $search . '%';
    $params['se'] = $params['sn'];
    $params['stn'] = $params['sn'];
}

if ($status !== '') {
    $where[] = 'u.account_status = :status';
    $params['status'] = $status;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u $whereSql");
$countStmt->execute($params);
$totalUsers = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalUsers / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT u.id, u.full_name, u.email, u.student_number, u.account_status,
           u.last_login_at, u.created_at,
           (SELECT COUNT(*) FROM reports r WHERE r.user_id = u.id) AS reports_count
    FROM users u
    $whereSql
    ORDER BY u.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="page-intro">
    <div>
        <span class="eyebrow">ACCOUNT MANAGEMENT</span>
        <h2>Student accounts</h2>
        <p>Suspend or reactivate student access. Suspended accounts are blocked immediately.</p>
    </div>
    <span class="result-count"><?= number_format($totalUsers) ?> students</span>
</div>

<section class="panel">
    <form method="GET" class="filter-form">
        <input
            type="search"
            name="search"
            placeholder="Search name, email or student number..."
            value="<?= e($search) ?>"
        >

        <select name="status">
            <option value="">All statuses</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        </select>

        <button class="btn btn-primary" type="submit">Apply filters</button>
        <a class="btn btn-light" href="<?= BASE_URL ?>/admin/users.php">Clear</a>
    </form>

    <?php if (!$users): ?>
        <p class="empty-state">No student accounts match your filters.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Student number</th>
                        <th>Reports</th>
                        <th>Last login</th>
                        <th>Joined</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <strong><?= e($user['full_name']) ?></strong>
                            <small><?= e($user['email']) ?></small>
                        </td>
                        <td><?= e($user['student_number'] ?? '—') ?></td>
                        <td><?= (int) $user['reports_count'] ?></td>
                        <td><?= e(adminDateTime($user['last_login_at'])) ?></td>
                        <td><?= e(adminDateTime($user['created_at'])) ?></td>
                        <td>
                            <?php if ($user['account_status'] === 'active'): ?>
                                <span class="status-badge badge-active">Active</span>
                            <?php else: ?>
                                <span class="status-badge badge-suspended">Suspended</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions">
                                <?php if ($user['account_status'] === 'active'): ?>
                                    <form method="POST" data-confirm="Suspend this student account?">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                        <input type="hidden" name="action" value="suspend">
                                        <button type="submit" class="btn btn-sm btn-danger">Suspend</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" data-confirm="Reactivate this student account?">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                        <input type="hidden" name="action" value="reactivate">
                                        <button type="submit" class="btn btn-sm btn-primary">Reactivate</button>
                                    </form>
                                <?php endif; ?>
                            </div>
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