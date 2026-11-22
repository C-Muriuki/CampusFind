<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';

$pdo = db();

$pageTitle = 'Claims';

/* Handle approve / reject decisions. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Your session could not be verified. Please try again.');
        redirect('/admin/claims.php');
    }

    $claimId = filter_var($_POST['claim_id'] ?? null, FILTER_VALIDATE_INT);
    $decision = $_POST['decision'] ?? '';
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if (!$claimId || !in_array($decision, ['approved', 'rejected'], true)) {
        flash('error', 'Invalid claim decision.');
        redirect('/admin/claims.php');
    }

    if (adminTextLength($adminNotes) > 2000) {
        flash('error', 'The note is too long.');
        redirect('/admin/claims.php');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT cl.id, cl.report_id, cl.claimant_id, cl.status,
                    r.item_name, r.user_id AS owner_id
             FROM claims cl
             INNER JOIN reports r ON r.id = cl.report_id
             WHERE cl.id = ? FOR UPDATE"
        );
        $stmt->execute([$claimId]);
        $claim = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$claim) {
            throw new RuntimeException('Claim not found.');
        }

        if ($claim['status'] !== 'pending') {
            throw new RuntimeException('This claim has already been reviewed.');
        }

        $pdo->prepare(
            "UPDATE claims
             SET status = :status, admin_notes = :notes,
                 reviewed_by = :reviewer, reviewed_at = NOW(), updated_at = NOW()
             WHERE id = :id"
        )->execute([
            'status' => $decision,
            'notes' => $adminNotes !== '' ? $adminNotes : null,
            'reviewer' => currentUserId(),
            'id' => $claimId
        ]);

        if ($decision === 'approved') {
            $pdo->prepare(
                "UPDATE reports
                 SET status = 'resolved', resolved_at = NOW(), updated_at = NOW()
                 WHERE id = ? AND status = 'approved'"
            )->execute([$claim['report_id']]);

            createNotification(
                (int) $claim['claimant_id'],
                'Your claim was approved',
                'Your claim for "' . $claim['item_name'] . '" was approved. '
                    . 'Please contact the campus office to collect the item.',
                'claim_approved',
                (int) $claim['report_id']
            );

            createNotification(
                (int) $claim['owner_id'],
                'Your report was resolved',
                'A claim for "' . $claim['item_name'] . '" was approved and the item marked resolved.',
                'report_resolved',
                (int) $claim['report_id']
            );

            $message = 'Claim approved. The item has been marked resolved and both parties notified.';
        } else {
            $noteText = $adminNotes !== ''
                ? ' Reason: ' . $adminNotes
                : '';

            createNotification(
                (int) $claim['claimant_id'],
                'Your claim was not approved',
                'Your claim for "' . $claim['item_name'] . '" was not approved.' . $noteText,
                'claim_rejected',
                (int) $claim['report_id']
            );

            $message = 'Claim rejected and the claimant notified.';
        }

        logActivityDb(
            'review_claim',
            'Administrator ' . $decision . ' claim #' . $claimId . ' for report #'
                . $claim['report_id'] . ' (' . $claim['item_name'] . ').'
        );

        $pdo->commit();

        flash('success', $message);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('CampusFind claim review error: ' . $exception->getMessage());

        flash(
            'error',
            $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The decision could not be saved. Check the application log.'
        );
    }

    redirect('/admin/claims.php');
}

/* List + filters. */
$status = $_GET['status'] ?? 'pending';

if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
    $status = 'pending';
}

$where = '';
$params = [];

if ($status !== 'all') {
    $where = 'WHERE cl.status = :status';
    $params['status'] = $status;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM claims cl $where"
);
$countStmt->execute($params);
$totalClaims = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalClaims / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT cl.id, cl.report_id, cl.claimant_id, cl.status, cl.proof, cl.admin_notes,
           cl.created_at, cl.reviewed_at,
           r.item_name, r.item_type,
           u.full_name AS claimant_name, u.email AS claimant_email,
           reviewer.full_name AS reviewer_name
    FROM claims cl
    INNER JOIN reports r ON r.id = cl.report_id
    INNER JOIN users u ON u.id = cl.claimant_id
    LEFT JOIN users reviewer ON reviewer.id = cl.reviewed_by
    $where
    ORDER BY
        CASE WHEN cl.status = 'pending' THEN 0 ELSE 1 END,
        cl.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="page-intro">
    <div>
        <span class="eyebrow">OWNERSHIP VERIFICATION</span>
        <h2>Item claims</h2>
        <p>Verify proof of ownership before handing an item to a claimant.</p>
    </div>
    <span class="result-count"><?= number_format($totalClaims) ?> claims</span>
</div>

<section class="panel">
    <form method="GET" class="filter-form">
        <select name="status">
            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending review</option>
            <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All claims</option>
        </select>
        <button class="btn btn-primary" type="submit">Filter</button>
        <a class="btn btn-light" href="<?= BASE_URL ?>/admin/claims.php">Reset</a>
    </form>

    <?php if (!$claims): ?>
        <p class="empty-state">No claims match this filter.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Claimant</th>
                        <th>Proof</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Reviewed by</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($claims as $claim): ?>
                    <tr>
                        <td>
                            <strong><?= e($claim['item_name']) ?></strong>
                            <small><?= e(ucfirst($claim['item_type'])) ?></small>
                        </td>
                        <td>
                            <strong><?= e($claim['claimant_name']) ?></strong>
                            <small><?= e($claim['claimant_email']) ?></small>
                        </td>
                        <td style="white-space:normal;max-width:280px"><?= e($claim['proof']) ?></td>
                        <td>
                            <span class="status-badge <?= adminStatusClass($claim['status']) ?>">
                                <?= e(ucfirst($claim['status'])) ?>
                            </span>
                        </td>
                        <td style="white-space:nowrap"><?= e(adminDateTime($claim['created_at'])) ?></td>
                        <td><?= e($claim['reviewer_name'] ?? '—') ?></td>
                        <td>
                            <?php if ($claim['status'] === 'pending'): ?>
                                <details class="review-details">
                                    <summary class="table-link">Review</summary>
                                    <form method="POST" class="review-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="claim_id" value="<?= (int) $claim['id'] ?>">
                                        <select name="decision" required>
                                            <option value="approved">Approve (hand over)</option>
                                            <option value="rejected">Reject</option>
                                        </select>
                                        <textarea name="admin_notes" rows="2" maxlength="2000"
                                                  placeholder="Note to claimant (optional)"></textarea>
                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                    </form>
                                </details>
                            <?php elseif ($claim['admin_notes']): ?>
                                <small class="muted-cell"><?= e($claim['admin_notes']) ?></small>
                            <?php else: ?>
                                <span class="muted-cell">—</span>
                            <?php endif; ?>
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