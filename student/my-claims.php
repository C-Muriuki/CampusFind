<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/student_guard.php';

$pdo = db();
$userId = currentUserId();

/* Claims I have submitted. */
$mine = $pdo->prepare(
    "SELECT cl.id, cl.status, cl.proof, cl.admin_notes, cl.created_at, cl.reviewed_at,
            r.id AS report_id, r.item_name, r.item_type, r.image_path
     FROM claims cl
     INNER JOIN reports r ON r.id = cl.report_id
     WHERE cl.claimant_id = ?
     ORDER BY cl.created_at DESC"
);
$mine->execute([$userId]);
$myClaims = $mine->fetchAll(PDO::FETCH_ASSOC);

/* Claims submitted against my own reports. */
$incoming = $pdo->prepare(
    "SELECT cl.id, cl.status, cl.proof, cl.admin_notes, cl.created_at,
            r.id AS report_id, r.item_name,
            u.full_name AS claimant_name, u.email AS claimant_email
     FROM claims cl
     INNER JOIN reports r ON r.id = cl.report_id
     INNER JOIN users u ON u.id = cl.claimant_id
     WHERE r.user_id = ?
     ORDER BY cl.created_at DESC"
);
$incoming->execute([$userId]);
$incomingClaims = $incoming->fetchAll(PDO::FETCH_ASSOC);

$successMessage = flash('success');
$errorMessage = flash('error');

$pageTitle = 'My Claims';
require_once __DIR__ . '/../includes/student_header.php';
?>

<main class="student-main">
    <div class="page-heading page-heading-row">
        <div>
            <p class="eyebrow">RECOVERY</p>
            <h1>My claims</h1>
            <p class="muted">Track the claims you have made and the claims others made on your reports.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/student/browse.php">Browse items</a>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?= e($successMessage) ?></div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="alert alert-error"><?= e($errorMessage) ?></div>
    <?php endif; ?>

    <section class="dashboard-section">
        <h2 class="section-heading">Claims I've made</h2>

        <?php if (!$myClaims): ?>
            <div class="empty-state">
                <p>You have not claimed any items yet.</p>
                <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/student/browse.php">Browse items</a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Outcome</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($myClaims as $claim): ?>
                        <tr>
                            <td>
                                <div class="report-item-cell">
                                    <?php if (!empty($claim['image_path'])): ?>
                                        <img src="<?= e(BASE_URL . '/' . $claim['image_path']) ?>"
                                             alt="" class="report-thumbnail">
                                    <?php else: ?>
                                        <div class="report-thumbnail-placeholder"></div>
                                    <?php endif; ?>
                                    <strong><?= e($claim['item_name']) ?></strong>
                                </div>
                            </td>
                            <td><?= e(ucfirst($claim['item_type'])) ?></td>
                            <td>
                                <span class="status-badge <?= e(student_status_class($claim['status'])) ?>">
                                    <?= e(ucfirst($claim['status'])) ?>
                                </span>
                            </td>
                            <td><?= e(date('d M Y', strtotime($claim['created_at']))) ?></td>
                            <td class="claim-outcome">
                                <?= e($claim['admin_notes'] !== null && $claim['admin_notes'] !== '' ? $claim['admin_notes'] : '—') ?>
                            </td>
                            <td>
                                <a class="table-action"
                                   href="<?= e(BASE_URL) ?>/student/report-view.php?id=<?= (int) $claim['report_id'] ?>">
                                    View item
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="dashboard-section">
        <h2 class="section-heading">Claims on my reports</h2>

        <?php if (!$incomingClaims): ?>
            <div class="empty-state">
                <p>No one has claimed your reports yet.</p>
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
                    <?php foreach ($incomingClaims as $claim): ?>
                        <tr>
                            <td>
                                <a class="text-link"
                                   href="<?= e(BASE_URL) ?>/student/report-view.php?id=<?= (int) $claim['report_id'] ?>">
                                    <?= e($claim['item_name']) ?>
                                </a>
                            </td>
                            <td>
                                <strong><?= e($claim['claimant_name']) ?></strong>
                                <br><span class="muted"><?= e($claim['claimant_email']) ?></span>
                            </td>
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
            <p class="muted" style="margin-top:14px">
                An administrator verifies each claim. If a claim is approved, the item is marked
                resolved and the rightful owner is contacted to collect it.
            </p>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>