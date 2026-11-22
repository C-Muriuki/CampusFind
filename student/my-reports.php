<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/student_guard.php';

$userId = currentUserId();

$stmt = $pdo->prepare(
    "SELECT
        r.id,
        r.item_name,
        r.item_type,
        r.location,
        r.incident_date,
        r.image_path,
        r.status,
        r.created_at,
        c.name AS category_name
     FROM reports r
     LEFT JOIN categories c ON c.id = r.category_id
     WHERE r.user_id = :user_id
     ORDER BY r.created_at DESC"
);

$stmt->execute(['user_id' => $userId]);
$reports = $stmt->fetchAll();

$successMessage = flash('success');
$errorMessage = flash('error');

$pageTitle = 'My Submissions';
require_once __DIR__ . '/../includes/student_header.php';
?>

<main class="student-main">
    <div class="page-heading page-heading-row">
        <div>
            <p class="eyebrow">STUDENT PORTAL</p>
            <h1>My submissions</h1>
            <p class="muted">
                View and manage the lost and found reports you have submitted.
            </p>
        </div>

        <a class="btn btn-primary"
           href="<?= e(BASE_URL) ?>/student/report-create.php">
            Report an Item
        </a>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success">
            <?= e($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-error">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <section class="dashboard-section">
        <?php if (!$reports): ?>
            <div class="empty-state">
                <h2>You have not submitted any reports.</h2>
                <p>
                    Submit a report when you lose something or find an item on campus.
                </p>
                <a class="btn btn-primary"
                   href="<?= e(BASE_URL) ?>/student/report-create.php">
                    Create a report
                </a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Report type</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Date submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td>
                                    <div class="report-item-cell">
                                        <?php if (!empty($report['image_path'])): ?>
                                            <img
                                                src="<?= e(BASE_URL . '/' . $report['image_path']) ?>"
                                                alt=""
                                                class="report-thumbnail"
                                            >
                                        <?php else: ?>
                                            <div class="report-thumbnail-placeholder"></div>
                                        <?php endif; ?>

                                        <strong><?= e($report['item_name']) ?></strong>
                                    </div>
                                </td>

                                <td><?= e(ucfirst($report['item_type'])) ?></td>

                                <td><?= e($report['category_name'] ?? 'Uncategorised') ?></td>

                                <td><?= e($report['location']) ?></td>

                                <td>
                                    <span class="status-badge <?= e(student_status_class($report['status'])) ?>">
                                        <?= e(ucfirst($report['status'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e(date('d M Y', strtotime($report['created_at']))) ?>
                                </td>

                                <td>
                                    <a class="table-action"
                                       href="<?= e(BASE_URL) ?>/student/report-view.php?id=<?= (int) $report['id'] ?>">
                                        View
                                    </a>
                                    <?php if (in_array($report['status'], ['pending', 'approved'], true)): ?>
                                        <a class="table-action"
                                           href="<?= e(BASE_URL) ?>/student/report-edit.php?id=<?= (int) $report['id'] ?>">
                                            Edit
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>