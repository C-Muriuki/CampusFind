<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';

$pdo = db();

$pageTitle = 'Review report';

$reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$reportId || $reportId < 1) {
    flash('error', 'Invalid report selected.');
    redirect('/admin/reports.php');
}

$stmt = $pdo->prepare("
    SELECT r.*,
           c.name AS category_name,
           u.full_name AS student_name,
           u.email AS student_email,
           u.student_number,
           u.phone AS student_phone,
           reviewer.full_name AS reviewer_name
    FROM reports r
    INNER JOIN users u ON u.id = r.user_id
    INNER JOIN categories c ON c.id = r.category_id
    LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by
    WHERE r.id = :id
    LIMIT 1
");

$stmt->execute(['id' => $reportId]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    flash('error', 'The selected report could not be found.');
    redirect('/admin/reports.php');
}

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="page-intro">
    <div>
        <span class="eyebrow">REPORT #<?= (int) $report['id'] ?></span>
        <h2><?= e($report['item_name']) ?></h2>
        <p>Review the submission and decide whether it should be published.</p>
    </div>

    <a class="btn btn-light" href="<?= BASE_URL ?>/admin/reports.php">
        Back to reports
    </a>
</div>

<div class="review-layout">
    <section class="panel">
        <?php if (!empty($report['image_path'])): ?>
            <div class="report-image">
                <img
                    src="<?= BASE_URL ?>/<?= e($report['image_path']) ?>"
                    alt="<?= e($report['item_name']) ?>"
                >
            </div>
        <?php endif; ?>

        <div class="detail-heading">
            <div>
                <span class="eyebrow">SUBMISSION DETAILS</span>
                <h2>Item information</h2>
            </div>

            <span class="status-badge <?= adminStatusClass($report['status']) ?>">
                <?= e(ucfirst($report['status'])) ?>
            </span>
        </div>

        <dl class="detail-list">
            <div>
                <dt>Report type</dt>
                <dd><?= e(ucfirst($report['item_type'])) ?></dd>
            </div>

            <div>
                <dt>Category</dt>
                <dd><?= e($report['category_name']) ?></dd>
            </div>

            <div>
                <dt>Location</dt>
                <dd><?= e($report['location']) ?></dd>
            </div>

            <div>
                <dt>Date of incident</dt>
                <dd><?= e(formatDate($report['incident_date'])) ?></dd>
            </div>

            <div>
                <dt>Submitted</dt>
                <dd><?= e(formatDate($report['created_at'])) ?></dd>
            </div>

            <div>
                <dt>Reviewed by</dt>
                <dd><?= e($report['reviewer_name'] ?? '—') ?></dd>
            </div>
        </dl>

        <div class="description-block">
            <h3>Description</h3>
            <p><?= nl2br(e($report['description'])) ?></p>
        </div>

        <?php if (!empty($report['admin_notes'])): ?>
            <div class="moderation-note">
                <h3>Previous moderation note</h3>
                <p><?= nl2br(e($report['admin_notes'])) ?></p>
            </div>
        <?php endif; ?>
    </section>

    <aside class="review-sidebar">
        <section class="panel">
            <span class="eyebrow">SUBMITTED BY</span>
            <h2><?= e($report['student_name']) ?></h2>

            <div class="student-details">
                <p><strong>Email</strong><br><?= e($report['student_email']) ?></p>
                <p><strong>Student number</strong><br><?= e($report['student_number'] ?? 'Not provided') ?></p>
                <p><strong>Phone</strong><br><?= e($report['student_phone'] ?? 'Not provided') ?></p>
            </div>
        </section>

        <section class="panel">
            <span class="eyebrow">MODERATION</span>
            <h2>Review decision</h2>

            <?php if (in_array($report['status'], ['pending', 'approved', 'rejected'], true)): ?>
                <form method="POST" action="<?= BASE_URL ?>/admin/report-review-save.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">

                    <label for="decision">Decision</label>
                    <select name="decision" id="decision" required>
                        <option value="">Choose a decision</option>
                        <option value="approved">Approve report</option>
                        <option value="rejected">Reject report</option>
                    </select>

                    <label for="admin_notes">Moderation note</label>
                    <textarea
                        name="admin_notes"
                        id="admin_notes"
                        rows="5"
                        maxlength="2000"
                        placeholder="Explain the decision or provide guidance to the student..."
                    ><?= e($report['admin_notes'] ?? '') ?></textarea>

                    <button class="btn btn-primary btn-full" type="submit">
                        Save decision
                    </button>
                </form>
            <?php else: ?>
                <p class="empty-state">
                    This report is <?= e($report['status']) ?> and cannot be moderated again here.
                </p>
            <?php endif; ?>
        </section>
    </aside>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>