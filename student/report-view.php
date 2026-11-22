<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/student_guard.php';
require_once __DIR__ . '/../includes/student_report_helpers.php';

$reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$reportId || $reportId < 1) {
    http_response_code(404);
    exit('Report not found.');
}

$report = getStudentReport($pdo, $reportId, currentUserId());

if (!$report) {
    http_response_code(404);
    exit('Report not found.');
}

$pageTitle = 'Report Details';
require_once __DIR__ . '/../includes/student_header.php';
?>

<main class="student-main form-page">
    <div class="page-heading">
        <a class="back-link"
           href="<?= e(BASE_URL) ?>/student/my-reports.php">
            Back to my submissions
        </a>

        <p class="eyebrow">MY SUBMISSION</p>
        <h1><?= e($report['item_name']) ?></h1>
        <p class="muted">
            Submitted on <?= e(date('d M Y', strtotime($report['created_at']))) ?>
        </p>
    </div>

    <section class="dashboard-section report-detail">
        <?php if (!empty($report['image_path'])): ?>
            <img
                class="report-detail-image"
                src="<?= e(BASE_URL . '/' . $report['image_path']) ?>"
                alt="<?= e($report['item_name']) ?>"
            >
        <?php endif; ?>

        <div class="detail-grid">
            <div>
                <span class="detail-label">Report type</span>
                <strong><?= e(ucfirst($report['item_type'])) ?></strong>
            </div>

            <div>
                <span class="detail-label">Category</span>
                <strong><?= e($report['category_name'] ?? 'Uncategorised') ?></strong>
            </div>

            <div>
                <span class="detail-label">Status</span>
                <strong>
                    <span class="status-badge status-<?= e($report['status']) ?>">
                        <?= e(ucfirst($report['status'])) ?>
                    </span>
                </strong>
            </div>

            <div>
                <span class="detail-label">Location</span>
                <strong><?= e($report['location']) ?></strong>
            </div>

            <div>
                <span class="detail-label">Date lost or found</span>
                <strong><?= e(date('d M Y', strtotime($report['incident_date']))) ?></strong>
            </div>

            <div>
                <span class="detail-label">Contact phone</span>
                <strong><?= e($report['contact_phone'] ?: 'Not provided') ?></strong>
            </div>

            <div>
                <span class="detail-label">Contact email</span>
                <strong><?= e($report['contact_email'] ?: 'Not provided') ?></strong>
            </div>
        </div>

        <div class="report-description">
            <span class="detail-label">Description</span>
            <p><?= nl2br(e($report['description'])) ?></p>
        </div>

        <div class="form-actions">
            <?php if (in_array($report['status'], ['pending', 'approved'], true)): ?>
                <a class="btn btn-secondary"
                   href="<?= e(BASE_URL) ?>/student/report-edit.php?id=<?= (int) $report['id'] ?>">
                    Edit report
                </a>
            <?php endif; ?>

            <?php if ($report['status'] === 'approved'): ?>
                <form method="POST"
                      action="<?= e(BASE_URL) ?>/student/report-resolve.php"
                      data-confirm="Mark this report as resolved?">

                    <input type="hidden" name="csrf_token"
                           value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="report_id"
                           value="<?= (int) $report['id'] ?>">

                    <button type="submit" class="btn btn-primary">
                        Mark as resolved
                    </button>
                </form>
            <?php endif; ?>

            <form method="POST"
                  action="<?= e(BASE_URL) ?>/student/report-delete.php"
                  data-confirm="Are you sure you want to delete this report? This cannot be undone.">

                <input type="hidden" name="csrf_token"
                       value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="report_id"
                       value="<?= (int) $report['id'] ?>">

                <button type="submit" class="btn btn-danger">
                    Delete report
                </button>
            </form>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>