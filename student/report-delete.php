<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/student_guard.php';
require_once __DIR__ . '/../includes/student_report_helpers.php';

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

$report = getStudentReport($pdo, $reportId, currentUserId());

if (!$report) {
    flash('error', 'Report not found.');
    redirect('/student/my-reports.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $deleteStmt = $pdo->prepare(
        "DELETE FROM reports
         WHERE id = :report_id
           AND user_id = :user_id"
    );

    $deleteStmt->execute([
        'report_id' => $reportId,
        'user_id' => currentUserId(),
    ]);

    if ($deleteStmt->rowCount() !== 1) {
        $pdo->rollBack();

        flash('error', 'The report could not be deleted.');
        redirect('/student/my-reports.php');
        exit;
    }

    $pdo->commit();

    deleteReportImage($report['image_path']);

    logActivityDb(
        'report_deleted',
        'Student deleted report #' . $reportId
    );

    flash('success', 'Your report has been deleted.');

} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('CampusFind report deletion error: ' . $exception->getMessage());
    flash('error', 'Something went wrong while deleting the report.');
}

redirect('/student/my-reports.php');
exit;