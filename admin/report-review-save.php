<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/reports.php');
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('error', 'Your session could not be verified. Please try again.');
    redirect('/admin/reports.php');
}

$reportId = filter_var($_POST['report_id'] ?? null, FILTER_VALIDATE_INT);
$decision = $_POST['decision'] ?? '';
$adminNotes = trim($_POST['admin_notes'] ?? '');

if (!$reportId || $reportId < 1) {
    flash('error', 'Invalid report selected.');
    redirect('/admin/reports.php');
}

if (!in_array($decision, ['approved', 'rejected'], true)) {
    flash('error', 'Select a valid moderation decision.');
    redirect('/admin/report-review.php?id=' . $reportId);
}

if (adminTextLength($adminNotes) > 2000) {
    flash('error', 'The moderation note is too long.');
    redirect('/admin/report-review.php?id=' . $reportId);
}

$pdo = db();

if (!$pdo) {
    flash('error', 'Service temporarily unavailable.');
    redirect('/admin/reports.php');
}

try {
    $pdo->beginTransaction();

    $findStmt = $pdo->prepare("
        SELECT id, user_id, item_name, status
        FROM reports
        WHERE id = :id
        FOR UPDATE
    ");
    $findStmt->execute(['id' => $reportId]);
    $report = $findStmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        throw new RuntimeException('Report not found.');
    }

    if (!in_array($report['status'], ['pending', 'approved', 'rejected'], true)) {
        throw new RuntimeException('This report can no longer be moderated.');
    }

    $updateStmt = $pdo->prepare("
        UPDATE reports
        SET status = :status,
            admin_notes = :admin_notes,
            reviewed_by = :reviewed_by,
            reviewed_at = NOW(),
            updated_at = NOW()
        WHERE id = :id
    ");

    $updateStmt->execute([
        'status' => $decision,
        'admin_notes' => $adminNotes !== '' ? $adminNotes : null,
        'reviewed_by' => currentUserId(),
        'id' => $reportId
    ]);

    $notificationTitle = $decision === 'approved'
        ? 'Your report has been approved'
        : 'Your report was not approved';

    $notificationMessage = $decision === 'approved'
        ? 'Your report "' . $report['item_name'] . '" has been approved and can now appear in CampusFind listings.'
        : 'Your report "' . $report['item_name'] . '" was rejected. Please review the administrator note for more information.';

    $notificationStmt = $pdo->prepare("
        INSERT INTO notifications
            (user_id, title, message, notification_type, related_report_id)
        VALUES
            (:user_id, :title, :message, :notification_type, :report_id)
    ");

    $notificationStmt->execute([
        'user_id' => $report['user_id'],
        'title' => $notificationTitle,
        'message' => $notificationMessage,
        'notification_type' => 'report_' . $decision,
        'report_id' => $reportId
    ]);

    $logStmt = $pdo->prepare("
        INSERT INTO activity_logs
            (user_id, action, description, ip_address, user_agent)
        VALUES
            (:user_id, :action, :description, :ip_address, :user_agent)
    ");

    $logStmt->execute([
        'user_id' => currentUserId(),
        'action' => 'report_' . $decision,
        'description' => 'Administrator ' . $decision . ' report #' . $reportId
            . ' (' . $report['item_name'] . ').',
        'ip_address' => clientIp(),
        'user_agent' => userAgentString()
    ]);

    $pdo->commit();

    flash(
        'success',
        $decision === 'approved'
            ? 'The report has been approved and the student has been notified.'
            : 'The report has been rejected and the student has been notified.'
    );

    redirect('/admin/report-review.php?id=' . $reportId);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('CampusFind moderation error: ' . $exception->getMessage());

    flash('error', 'The moderation decision could not be saved. Check the application log.');
    redirect('/admin/report-review.php?id=' . $reportId);
}