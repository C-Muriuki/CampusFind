<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$report = null;

if ($pdo && $id > 0) {
    try {
        $stmt = $pdo->prepare(
            "SELECT r.id, r.item_name, r.item_type, r.description,
                    r.location, r.incident_date, r.image_path,
                    r.created_at, r.resolved_at,
                    c.name AS category_name
             FROM reports r
             INNER JOIN categories c ON c.id = r.category_id
             WHERE r.id = :id AND r.status = 'approved'
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $report = $row === false ? null : $row;
    } catch (Throwable $exception) {
        error_log('[CampusFind item] ' . $exception->getMessage());
    }
}

$pageTitle = $report ? $report['item_name'] . ' | CampusFind' : 'Item not found | CampusFind';
$activeNav = 'items';
$seoDescription = $report
    ? ($report['item_type'] === 'lost' ? 'Lost item: ' : 'Found item: ') . $report['item_name'] . ' reported at ' . $report['location'] . '.'
    : 'This item is not available.';
require_once __DIR__ . '/../includes/public_header.php';
?>

<section class="page-hero">
    <div class="container">
        <a class="back-link"
           href="<?= e(rtrim(BASE_URL, '/')) ?>/pages/items.php">&larr; Back to items</a>
        <p class="eyebrow">ITEM DETAILS</p>
        <?php if ($report): ?>
            <h1><?= e($report['item_name']) ?></h1>
        <?php else: ?>
            <h1>Item not found</h1>
        <?php endif; ?>
    </div>
</section>

<section class="page-section">
    <div class="container">

        <?php if (!$report): ?>
            <div class="items-empty">
                <h2>This item is not available.</h2>
                <p>It may have been resolved, removed, or never approved.</p>
                <a class="button button-primary"
                   href="<?= e(rtrim(BASE_URL, '/')) ?>/pages/items.php">Browse items</a>
            </div>
        <?php else: ?>
            <article class="item-detail">

                <div class="item-detail-media">
                    <?php if (!empty($report['image_path'])): ?>
                        <img src="<?= e(rtrim(BASE_URL, '/') . '/' . $report['image_path']) ?>"
                             alt="<?= e($report['item_name']) ?>">
                    <?php else: ?>
                        <div class="item-detail-placeholder" aria-hidden="true"></div>
                    <?php endif; ?>
                </div>

                <div class="item-detail-body">
                    <p class="item-detail-tags">
                        <span class="item-type-badge item-type-<?= e($report['item_type']) ?>">
                            <?= e(ucfirst($report['item_type'])) ?>
                        </span>
                        <span class="item-detail-category"><?= e($report['category_name']) ?></span>
                    </p>

                    <p class="item-detail-meta">
                        <span><strong>Location:</strong> <?= e($report['location']) ?></span>
                        <span><strong>Date:</strong> <?= e(formatDate($report['incident_date'])) ?></span>
                        <span><strong>Reported:</strong> <?= e(date('d M Y', strtotime($report['created_at']))) ?></span>
                    </p>

                    <div class="item-detail-description">
                        <h2>Description</h2>
                        <p><?= nl2br(e($report['description'])) ?></p>
                    </div>

                    <?php if (!empty($report['resolved_at'])): ?>
                        <p class="item-resolved">
                            Resolved on <?= e(date('d M Y', strtotime($report['resolved_at']))) ?>.
                        </p>
                    <?php endif; ?>

                    <?php if (!isLoggedIn()): ?>
                        <div class="item-detail-cta">
                            <p>Is this your item? Sign in to submit a claim.</p>
                            <a class="button button-primary"
                               href="<?= e(rtrim(BASE_URL, '/')) ?>/auth/login.php">
                                Sign in to claim
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="item-detail-cta">
                            <p>Is this your item? Submit a claim with proof.</p>
                            <a class="button button-primary"
                               href="<?= e(rtrim(BASE_URL, '/')) ?>/student/claim.php?id=<?= (int) $report['id'] ?>">
                                Claim this item
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/public_footer.php'; ?>