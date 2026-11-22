<?php
declare(strict_types=1);

/**
 * Stage 4.6: report ownership checks and image helpers.
 *
 * Centralises the "does this report belong to this student?" query and the
 * safe deletion of uploaded images so a student cannot reach another
 * student's report by changing the id in the URL.
 */

function getStudentReport(PDO $pdo, int $reportId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT r.*, c.name AS category_name
         FROM reports r
         LEFT JOIN categories c ON c.id = r.category_id
         WHERE r.id = :report_id
           AND r.user_id = :user_id
         LIMIT 1"
    );

    $stmt->execute([
        'report_id' => $reportId,
        'user_id' => $userId,
    ]);

    $report = $stmt->fetch();

    return $report ?: null;
}

function deleteReportImage(?string $imagePath): void
{
    if (!$imagePath) {
        return;
    }

    // Only delete files stored in the reports upload folder.
    $prefix = 'uploads/reports/';

    if (!str_starts_with($imagePath, $prefix)) {
        return;
    }

    $filename = basename($imagePath);

    if ($filename === '' || $filename === '.' || $filename === '..') {
        return;
    }

    $fullPath = ROOT_PATH . '/' . $prefix . $filename;

    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}

function validReportDate(string $date): bool
{
    $parsed = DateTime::createFromFormat('Y-m-d', $date);

    return $parsed !== false
        && $parsed->format('Y-m-d') === $date
        && $parsed <= new DateTime('today');
}