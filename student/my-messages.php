<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/student_guard.php';

/**
 * Student — My Messages.
 *
 * Shows every Contact Us message the signed-in student has sent, together with
 * any reply the CampusFind team wrote from the admin dashboard. This is the
 * in-app half of the contact loop (the team's reply is also emailed to the
 * address on the account).
 */

$userId = currentUserId();

$stmt = $pdo->prepare(
    "SELECT id, subject, message, status, admin_response, responded_at, created_at
       FROM contact_submissions
      WHERE user_id = :user_id
      ORDER BY created_at DESC
      LIMIT 100"
);
$stmt->execute(['user_id' => $userId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$awaiting = 0;
$replied = 0;
foreach ($messages as $message) {
    if (!empty($message['admin_response'])) {
        $replied++;
    } else {
        $awaiting++;
    }
}

$pageTitle = 'My Messages';
require_once __DIR__ . '/../includes/student_header.php';
?>

<main class="student-main">
    <div class="page-heading page-heading-row">
        <div>
            <p class="eyebrow">STUDENT PORTAL</p>
            <h1>My Messages</h1>
            <p class="muted">
                Replies from the CampusFind team to messages you sent through Contact Us.
            </p>
        </div>

        <div class="message-summary">
            <span><strong><?= (int) $replied ?></strong> replied</span>
            <span><strong><?= (int) $awaiting ?></strong> awaiting reply</span>
        </div>
    </div>

    <section class="dashboard-section">
        <?php if (!$messages): ?>
            <div class="empty-state">
                <h2>No messages yet</h2>
                <p>
                    Messages you send through the
                    <a href="<?= e(BASE_URL) ?>/pages/contact.php">Contact Us</a>
                    page appear here, along with any reply from the team.
                </p>
            </div>
        <?php else: ?>
            <div class="message-list">
                <?php foreach ($messages as $message): ?>
                    <article class="message-card">
                        <div class="message-card-head">
                            <h2><?= e($message['subject']) ?></h2>
                            <span class="status-badge status-<?= e($message['status']) ?>">
                                <?= e(ucwords(str_replace('_', ' ', $message['status']))) ?>
                            </span>
                        </div>

                        <p class="message-meta">
                            Sent <?= e(formatDate($message['created_at'])) ?>
                            &middot; Ref #<?= (int) $message['id'] ?>
                        </p>

                        <div class="message-block">
                            <span class="message-block-label">You wrote</span>
                            <p><?= nl2br(e($message['message'])) ?></p>
                        </div>

                        <?php if (!empty($message['admin_response'])): ?>
                            <div class="message-block message-reply">
                                <span class="message-block-label">
                                    <?= icon('mail', 13) ?>
                                    Team reply &middot;
                                    <?= e(formatDate($message['responded_at'] ?: $message['created_at'])) ?>
                                </span>
                                <p><?= nl2br(e($message['admin_response'])) ?></p>
                            </div>
                        <?php else: ?>
                            <p class="message-awaiting">Awaiting a reply from the CampusFind team.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>