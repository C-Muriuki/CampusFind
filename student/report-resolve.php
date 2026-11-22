<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/student_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('error', 'Invalid security token. Please try again.');
    redirect('/student/my-reports.php');
    exit;
}

$reportId = filter_var(
    $_POST['report_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$reportId || $reportId < 1) {
    flash('error', 'Invalid report.');
    redirect('/student/my-reports.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        "UPDATE reports
         SET status = 'resolved',
             updated_at = NOW()
         WHERE id = :report_id
           AND user_id = :user_id
           AND status = 'approved'"
    );

    $stmt->execute([
        'report_id' => $reportId,
        'user_id' => currentUserId(),
    ]);

    if ($stmt->rowCount() !== 1) {
        flash(
            'error',
            'The report could not be marked as resolved. It may not be approved or may already be resolved.'
        );
    } else {
        logActivityDb(
            'report_resolved',
            'Student marked report #' . $reportId . ' as resolved'
        );

        flash('success', 'Your report has been marked as resolved.');
    }

} catch (PDOException $exception) {
    error_log('CampusFind resolve report error: ' . $exception->getMessage());
    flash('error', 'The report could not be updated.');
}

redirect('/student/report-view.php?id=' . $reportId);
exit;