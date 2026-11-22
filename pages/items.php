<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();

$search = trim($_GET['search'] ?? '');
$type = $_GET['type'] ?? '';
$category = (int) ($_GET['category'] ?? 0);

if (!in_array($type, ['lost', 'found'], true)) {
    $type = '';
}

$categoryList = [];
if ($pdo) {
    try {
        $catStmt = $pdo->query(
            'SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name'
        );
        $categoryList = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        error_log('[CampusFind items] categories: ' . $exception->getMessage());
    }
}

if ($category !== 0) {
    $validCat = false;
    foreach ($categoryList as $cat) {
        if ((int) $cat['id'] === $category) {
            $validCat = true;
            break;
        }
    }
    if (!$validCat) {
        $category = 0;
    }
}

$where = ["r.status = 'approved'"];
$params = [];

if ($type !== '') {
    $where[] = 'r.item_type = :type';
    $params['type'] = $type;
}

if ($category > 0) {
    $where[] = 'r.category_id = :cat';
    $params['cat'] = $category;
}

if ($search !== '') {
    $where[] = '(r.item_name LIKE :sn OR r.location LIKE :sl)';
    $params['sn'] = '%' . $search . '%';
    $params['sl'] = '%' . $search . '%';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

$totalReports = 0;
$reports = [];

if ($pdo) {
    try {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM reports r $whereSql");
        $countStmt->execute($params);
        $totalReports = (int) $countStmt->fetchColumn();
    } catch (Throwable $exception) {
        error_log('[CampusFind items] count: ' . $exception->getMessage());
    }

    $totalPages = max(1, (int) ceil($totalReports / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $sql = "
        SELECT r.id, r.item_name, r.item_type, r.location, r.incident_date,
               r.image_path, r.created_at, c.name AS category_name
        FROM reports r
        INNER JOIN categories c ON c.id = r.category_id
        $whereSql
        ORDER BY r.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    try {
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        error_log('[CampusFind items] list: ' . $exception->getMessage());
    }
} else {
    $totalPages = 1;
}

$pageTitle = 'Browse Items | CampusFind';
$activeNav = 'items';
$seoDescription = 'Browse approved lost and found items reported by the campus community. Search and filter by type, category or location.';
require_once __DIR__ . '/../includes/public_header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow">CAMPUS COMMUNITY</p>
        <h1>Browse reported items</h1>
        <p class="page-hero-lead">
            Look through approved lost and found reports.
        </p>
    </div>
</section>

<section class="page-section">
    <div class="container">

        <form method="GET" action="<?= e(rtrim(BASE_URL, '/')) ?>/pages/items.php"
              class="items-filters" role="search">

            <input type="search" name="search"
                   placeholder="Search item or location..."
                   value="<?= e($search) ?>"
                   aria-label="Search items">

            <select name="type" aria-label="Filter by type">
                <option value="">Lost and found</option>
                <option value="lost" <?= $type === 'lost' ? 'selected' : '' ?>>Lost</option>
                <option value="found" <?= $type === 'found' ? 'selected' : '' ?>>Found</option>
            </select>

            <select name="category" aria-label="Filter by category">
                <option value="0">All categories</option>
                <?php foreach ($categoryList as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>"
                        <?= $category === (int) $cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="button button-primary" type="submit">
                <?= icon('search', 15) ?> Search
            </button>
            <a class="button button-outline"
               href="<?= e(rtrim(BASE_URL, '/')) ?>/pages/items.php">
                <?= icon('refresh', 15) ?> Clear
            </a>
        </form>

        <?php if (!$reports): ?>
            <div class="items-empty">
                <h2>No items found.</h2>
                <p>Try adjusting your filters, or check back later.</p>
            </div>
        <?php else: ?>
            <p class="items-count">
                <?= number_format($totalReports) ?> item<?= $totalReports === 1 ? '' : 's' ?>
            </p>

            <div class="items-grid">
                <?php foreach ($reports as $report): ?>
                    <a class="item-card"
                       href="<?= e(rtrim(BASE_URL, '/')) ?>/pages/item.php?id=<?= (int) $report['id'] ?>">

                        <div class="item-card-media">
                            <?php if (!empty($report['image_path'])): ?>
                                <img src="<?= e(rtrim(BASE_URL, '/') . '/' . $report['image_path']) ?>"
                                     alt="<?= e($report['item_name']) ?>">
                            <?php else: ?>
                                <div class="item-card-placeholder" aria-hidden="true"></div>
                            <?php endif; ?>
                            <span class="item-type-badge item-type-<?= e($report['item_type']) ?>">
                                <?= e(ucfirst($report['item_type'])) ?>
                            </span>
                        </div>

                        <div class="item-card-body">
                            <h2><?= e($report['item_name']) ?></h2>
                            <p class="item-meta">
                                <span><?= e($report['category_name']) ?></span>
                                <span aria-hidden="true">&middot;</span>
                                <span><?= e($report['location']) ?></span>
                            </p>
                            <p class="item-date">
                                Reported <?= e(date('d M Y', strtotime($report['created_at']))) ?>
                            </p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="items-pagination" aria-label="Pagination">
                    <span>Page <?= $page ?> of <?= $totalPages ?></span>
                    <div>
                        <?php if ($page > 1): ?>
                            <a class="button button-outline"
                               href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">
                                Previous
                            </a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a class="button button-outline"
                               href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/public_footer.php'; ?>