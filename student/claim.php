<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/student_guard.php';

$pdo = db();
$userId = currentUserId();

$reportId = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? filter_var($_POST['report_id'] ?? null, FILTER_VALIDATE_INT)
    : filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$errors = [];
$proofValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        http_response_code(419);
        exit('Invalid CSRF token. Please refresh the page and try again.');
    }

    $proof = trim($_POST['proof'] ?? '');
    $proofValue = $proof;

    if ($proof === '' || student_text_length($proof) > 2000) {
        $errors[] = 'Enter proof of ownership (up to 2,000 characters).';
    }

    if (!$errors && $reportId) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT id, user_id, item_name, status FROM reports WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$reportId]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);

            $problem = null;

            if (!$report) {
                $problem = 'That report could not be found.';
            } elseif ($report['status'] !== 'approved') {
                $problem = 'This item is no longer available to claim.';
            } elseif ((int) $report['user_id'] === $userId) {
                $problem = 'You cannot claim your own report.';
            } else {
                $existing = $pdo->prepare(
                    "SELECT id FROM claims
                     WHERE report_id = ? AND claimant_id = ?
                       AND status IN ('pending','approved') LIMIT 1"
                );
                $existing->execute([$reportId, $userId]);

                if ($existing->fetch()) {
                    $problem = 'You already have a claim on this item.';
                }
            }

            if ($problem !== null) {
                $pdo->rollBack();
                $errors[] = $problem;
            } else {
                $insert = $pdo->prepare(
                    "INSERT INTO claims (report_id, claimant_id, proof, status)
                     VALUES (?, ?, ?, 'pending')"
                );
                $insert->execute([$reportId, $userId, $proof]);
                $claimId = (int) $pdo->lastInsertId();

                $pdo->commit();

                createNotification(
                    (int) $report['user_id'],
                    'New claim on your report',
                    'A student has submitted a claim for "' . $report['item_name']
                        . '". Review claims in the admin panel.',
                    'claim_new',
                    $reportId
                );

                logActivityDb(
                    'submit_claim',
                    'Submitted claim #' . $claimId . ' for report #' . $reportId
                );

                flash('success', 'Your claim has been submitted and is awaiting review.');
                redirect('/student/my-claims.php');
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('CampusFind claim submit error: ' . $exception->getMessage());
            $errors[] = 'Your claim could not be submitted. Please try again.';
        }
    }
}

if (!$reportId) {
    flash('error', 'Invalid item selected.');
    redirect('/student/browse.php');
}

$stmt = $pdo->prepare(
    "SELECT r.*, c.name AS category_name, u.full_name AS reporter_name
     FROM reports r
     INNER JOIN categories c ON c.id = r.category_id
     INNER JOIN users u ON u.id = r.user_id
     WHERE r.id = ? LIMIT 1"
);
$stmt->execute([$reportId]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report || $report['status'] !== 'approved') {
    flash('error', 'This item is not available to claim.');
    redirect('/student/browse.php');
}

$isOwner = (int) $report['user_id'] === $userId;

$existing = $pdo->prepare(
    "SELECT id FROM claims
     WHERE report_id = ? AND claimant_id = ?
       AND status IN ('pending','approved') LIMIT 1"
);
$existing->execute([$reportId, $userId]);
$hasClaim = (bool) $existing->fetch();

$pageTitle = 'Claim Item';
require_once __DIR__ . '/../includes/student_header.php';
?>

<main class="student-main">
    <div class="page-heading page-heading-row">
        <div>
            <p class="eyebrow">CLAIM AN ITEM</p>
            <h1><?= e($report['item_name']) ?></h1>
            <p class="muted">Review the item details and submit proof of ownership.</p>
        </div>
        <a class="btn btn-secondary" href="<?= e(BASE_URL) ?>/student/browse.php">Back to browse</a>
    </div>

    <section class="dashboard-section">
        <div class="report-detail">
            <?php if (!empty($report['image_path'])): ?>
                <div class="report-detail-image">
                    <img src="<?= e(BASE_URL . '/' . $report['image_path']) ?>"
                         alt="<?= e($report['item_name']) ?>">
                </div>
            <?php endif; ?>

            <dl class="detail-grid">
                <div><span class="detail-label">Type</span> <?= e(ucfirst($report['item_type'])) ?></div>
                <div><span class="detail-label">Category</span> <?= e($report['category_name']) ?></div>
                <div><span class="detail-label">Location</span> <?= e($report['location']) ?></div>
                <div><span class="detail-label">Incident date</span> <?= e(date('d M Y', strtotime($report['incident_date']))) ?></div>
                <div><span class="detail-label">Reported by</span> <?= e($report['reporter_name']) ?></div>
                <div><span class="detail-label">Contact</span> <?= e($report['contact_email'] ?: $report['contact_phone'] ?: '—') ?></div>
            </dl>

            <div class="report-description">
                <h3>Description</h3>
                <p><?= nl2br(e($report['description'])) ?></p>
            </div>
        </div>
    </section>

    <section class="dashboard-section">
        <?php if ($isOwner): ?>
            <div class="alert alert-error">
                This is your own report, so you cannot submit a claim on it.
            </div>
        <?php elseif ($hasClaim): ?>
            <div class="alert alert-error">
                You already have a claim on this item.
                <a class="text-link" href="<?= e(BASE_URL) ?>/student/my-claims.php">View my claims</a>.
            </div>
        <?php else: ?>
            <div class="form-section">
                <h2>Submit a claim</h2>
                <p class="muted">
                    Explain how you can prove this item belongs to you (distinguishing features,
                    where and when you lost it, or other identifying details). An administrator
                    will verify your claim before the item is handed over.
                </p>

                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-error"><?= e($error) ?></div>
                <?php endforeach; ?>

                <form method="POST" class="report-form"
                      action="<?= e(BASE_URL) ?>/student/claim.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">

                    <div class="form-group">
                        <label for="proof">Proof of ownership</label>
                        <textarea id="proof" name="proof" rows="6" maxlength="2000" required><?= e($proofValue) ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Submit claim</button>
                        <a class="btn btn-secondary" href="<?= e(BASE_URL) ?>/student/browse.php">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>