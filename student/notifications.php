<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/student_guard.php';

$userId = currentUserId();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid security token. Please try again.');
        redirect('/student/notifications.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'mark_one') {
            $notificationId = filter_var(
                $_POST['notification_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$notificationId || $notificationId < 1) {
                flash('error', 'Invalid notification.');
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE notifications
                     SET is_read = 1,
                         read_at = NOW()
                     WHERE id = :id
                       AND user_id = :user_id"
                );

                $stmt->execute([
                    'id' => $notificationId,
                    'user_id' => $userId,
                ]);

                flash('success', 'Notification marked as read.');
            }

        } elseif ($action === 'mark_all') {
            $stmt = $pdo->prepare(
                "UPDATE notifications
                 SET is_read = 1,
                     read_at = NOW()
                 WHERE user_id = :user_id
                   AND is_read = 0"
            );

            $stmt->execute(['user_id' => $userId]);

            flash('success', 'All notifications marked as read.');

        } else {
            flash('error', 'Unknown notification action.');
        }

    } catch (PDOException $exception) {
        error_log('CampusFind notification error: ' . $exception->getMessage());
        flash('error', 'Your notification could not be updated.');
    }

    redirect('/student/notifications.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT
        n.id,
        n.title,
        n.message,
        n.notification_type,
        n.related_report_id,
        n.is_read,
        n.read_at,
        n.created_at,
        r.item_name
     FROM notifications n
     LEFT JOIN reports r ON r.id = n.related_report_id
     WHERE n.user_id = :user_id
     ORDER BY n.created_at DESC
     LIMIT 100"
);

$stmt->execute(['user_id' => $userId]);
$notifications = $stmt->fetchAll();

$countStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM notifications
     WHERE user_id = :user_id AND is_read = 0"
);
$countStmt->execute(['user_id' => $userId]);
$unreadCount = (int) $countStmt->fetchColumn();

$successMessage = flash('success');
$errorMessage = flash('error');

$pageTitle = 'Notifications';
require_once __DIR__ . '/../includes/student_header.php';
?>

<main class="student-main">
    <div class="page-heading page-heading-row">
        <div>
            <p class="eyebrow">STUDENT PORTAL</p>
            <h1>Notifications</h1>
            <p class="muted">
                Updates about your reports and CampusFind account.
            </p>
        </div>

        <?php if ($unreadCount > 0): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token"
                       value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="mark_all">

                <button type="submit" class="btn btn-secondary">
                    Mark all as read
                </button>
            </form>
        <?php endif; ?>
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
        <div class="notification-summary">
            <strong><?= $unreadCount ?></strong>
            <span>unread notification<?= $unreadCount === 1 ? '' : 's' ?></span>
        </div>

        <?php if (!$notifications): ?>
            <div class="empty-state">
                <h2>No notifications yet</h2>
                <p>
                    Updates about your submissions will appear here.
                </p>
            </div>
        <?php else: ?>
            <div class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                    <article class="notification-item
                        <?= (int) $notification['is_read'] === 0 ? 'unread' : '' ?>">

                        <div class="notification-content">
                            <div class="notification-title-row">
                                <h3><?= e($notification['title']) ?></h3>

                                <?php if ((int) $notification['is_read'] === 0): ?>
                                    <span class="unread-indicator">New</span>
                                <?php endif; ?>
                            </div>

                            <p><?= nl2br(e($notification['message'])) ?></p>

                            <small>
                                <?= e(date('d M Y, h:i A', strtotime($notification['created_at']))) ?>
                            </small>

                            <?php if ($notification['notification_type'] === 'contact_reply'): ?>
                                <a class="text-link notification-report-link"
                                   href="<?= e(BASE_URL) ?>/student/my-messages.php">
                                    View your messages
                                </a>
                            <?php elseif (!empty($notification['related_report_id'])): ?>
                                <a class="text-link notification-report-link"
                                   href="<?= e(BASE_URL) ?>/student/report-view.php?id=<?= (int) $notification['related_report_id'] ?>">
                                    View related report
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if ((int) $notification['is_read'] === 0): ?>
                            <form method="POST" class="notification-action">
                                <input type="hidden" name="csrf_token"
                                       value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="action"
                                       value="mark_one">
                                <input type="hidden" name="notification_id"
                                       value="<?= (int) $notification['id'] ?>">

                                <button type="submit" class="text-link">
                                    Mark as read
                                </button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>