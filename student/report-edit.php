<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/student_guard.php';
require_once __DIR__ . '/../includes/student_report_helpers.php';
require_once __DIR__ . '/../includes/upload_security.php';

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

if (!in_array($report['status'], ['pending', 'approved'], true)) {
    http_response_code(403);
    exit('This report can no longer be edited.');
}

$categoryStmt = $pdo->query(
    "SELECT id, name FROM categories ORDER BY name ASC"
);
$categories = $categoryStmt->fetchAll();

$errors = [];

$fields = [
    'item_name' => $report['item_name'],
    'item_type' => $report['item_type'],
    'category_id' => (string) $report['category_id'],
    'description' => $report['description'],
    'location' => $report['location'],
    'incident_date' => $report['incident_date'],
    'contact_phone' => $report['contact_phone'] ?? '',
    'contact_email' => $report['contact_email'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session token is invalid. Refresh and try again.';
    }

    foreach ($fields as $key => $value) {
        if (isset($_POST[$key]) && is_string($_POST[$key])) {
            $fields[$key] = trim($_POST[$key]);
        }
    }

    if ($fields['item_name'] === '' ||
        student_text_length($fields['item_name']) > 150) {
        $errors[] = 'Enter an item name of no more than 150 characters.';
    }

    if (!in_array($fields['item_type'], ['lost', 'found'], true)) {
        $errors[] = 'Select a valid report type.';
    }

    $categoryId = filter_var(
        $fields['category_id'],
        FILTER_VALIDATE_INT
    );

    if (!$categoryId) {
        $errors[] = 'Select a valid category.';
    } else {
        $check = $pdo->prepare(
            "SELECT id FROM categories WHERE id = :id LIMIT 1"
        );
        $check->execute(['id' => $categoryId]);

        if (!$check->fetch()) {
            $errors[] = 'The selected category does not exist.';
        }
    }

    if ($fields['description'] === '' ||
        student_text_length($fields['description']) > 5000) {
        $errors[] = 'Enter a description of no more than 5,000 characters.';
    }

    if ($fields['location'] === '' ||
        student_text_length($fields['location']) > 180) {
        $errors[] = 'Enter a valid campus location.';
    }

    if (!validReportDate($fields['incident_date'])) {
        $errors[] = 'Enter a valid date that is not in the future.';
    }

    if ($fields['contact_email'] !== '' &&
        !filter_var($fields['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid contact email.';
    }

    if (student_text_length($fields['contact_phone']) > 30) {
        $errors[] = 'The phone number is too long.';
    }

    $newImagePath = null;
    $uploadedFile = $_FILES['item_image'] ?? null;

    if ($uploadedFile && $uploadedFile['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $generated = storeSecureReportImage($uploadedFile);

            if ($generated !== null) {
                $newImagePath = 'uploads/reports/' . $generated;
            }
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    if (!$errors) {
        try {
            $updateStmt = $pdo->prepare(
                "UPDATE reports
                 SET
                    category_id = :category_id,
                    item_name = :item_name,
                    item_type = :item_type,
                    description = :description,
                    location = :location,
                    incident_date = :incident_date,
                    contact_phone = :contact_phone,
                    contact_email = :contact_email,
                    image_path = :image_path,
                    updated_at = NOW()
                 WHERE id = :report_id
                   AND user_id = :user_id
                   AND status IN ('pending', 'approved')"
            );

            $updateStmt->execute([
                'category_id' => $categoryId,
                'item_name' => $fields['item_name'],
                'item_type' => $fields['item_type'],
                'description' => $fields['description'],
                'location' => $fields['location'],
                'incident_date' => $fields['incident_date'],
                'contact_phone' => $fields['contact_phone'] ?: null,
                'contact_email' => $fields['contact_email'] ?: null,
                'image_path' => $newImagePath ?? $report['image_path'],
                'report_id' => $reportId,
                'user_id' => currentUserId(),
            ]);

            if ($newImagePath !== null) {
                deleteReportImage($report['image_path']);
            }

            logActivityDb(
                'report_updated',
                'Student updated report #' . $reportId
            );

            flash('success', 'Your report has been updated.');
            redirect('/student/report-view.php?id=' . $reportId);
            exit;

        } catch (PDOException $exception) {
            if ($newImagePath !== null) {
                deleteReportImage($newImagePath);
            }

            error_log('CampusFind report update error: ' . $exception->getMessage());
            $errors[] = 'The report could not be updated. Please try again.';
        }
    }
}

$pageTitle = 'Edit Report';
require_once __DIR__ . '/../includes/student_header.php';
?>

<main class="student-main form-page">
    <div class="page-heading">
        <a class="back-link"
           href="<?= e(BASE_URL) ?>/student/report-view.php?id=<?= (int) $reportId ?>">
            Back to report
        </a>

        <p class="eyebrow">UPDATE SUBMISSION</p>
        <h1>Edit report</h1>
        <p class="muted">Update the details of your submitted item.</p>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <strong>Please correct the following:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="report-form"
          method="POST"
          enctype="multipart/form-data">

        <input type="hidden" name="csrf_token"
               value="<?= e(csrfToken()) ?>">

        <div class="form-section">
            <h2>Item information</h2>

            <div class="form-group">
                <label for="item_type">Report type</label>
                <select name="item_type" id="item_type" required>
                    <option value="lost"
                        <?= $fields['item_type'] === 'lost' ? 'selected' : '' ?>>
                        I lost an item
                    </option>
                    <option value="found"
                        <?= $fields['item_type'] === 'found' ? 'selected' : '' ?>>
                        I found an item
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label for="item_name">Item name</label>
                <input type="text" id="item_name" name="item_name"
                       maxlength="150" required
                       value="<?= e($fields['item_name']) ?>">
            </div>

            <div class="form-group">
                <label for="category_id">Category</label>
                <select name="category_id" id="category_id" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"
                            <?= (string) $fields['category_id'] ===
                                (string) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description"
                          rows="5" maxlength="5000"
                          required><?= e($fields['description']) ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h2>Location and date</h2>

            <div class="form-group">
                <label for="location">Campus location</label>
                <input type="text" id="location" name="location"
                       maxlength="180" required
                       value="<?= e($fields['location']) ?>">
            </div>

            <div class="form-group">
                <label for="incident_date">Date lost or found</label>
                <input type="date" id="incident_date"
                       name="incident_date"
                       max="<?= date('Y-m-d') ?>"
                       required
                       value="<?= e($fields['incident_date']) ?>">
            </div>
        </div>

        <div class="form-section">
            <h2>Item photo</h2>

            <?php if (!empty($report['image_path'])): ?>
                <p class="muted">Current photo:</p>
                <img class="report-detail-image"
                     src="<?= e(BASE_URL . '/' . $report['image_path']) ?>"
                     alt="Current item photo">
            <?php endif; ?>

            <div class="form-group">
                <label for="item_image">Replace photo (optional)</label>
                <input type="file" id="item_image" name="item_image"
                       accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                <small>JPG, PNG or WebP. Maximum 5 MB.</small>
            </div>
        </div>

        <div class="form-section">
            <h2>Contact information</h2>

            <div class="form-group">
                <label for="contact_phone">Phone number</label>
                <input type="tel" id="contact_phone"
                       name="contact_phone" maxlength="30"
                       value="<?= e($fields['contact_phone']) ?>">
            </div>

            <div class="form-group">
                <label for="contact_email">Email address</label>
                <input type="email" id="contact_email"
                       name="contact_email" maxlength="190"
                       value="<?= e($fields['contact_email']) ?>">
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary"
               href="<?= e(BASE_URL) ?>/student/report-view.php?id=<?= (int) $reportId ?>">
                Cancel
            </a>

            <button type="submit" class="btn btn-primary">
                Save changes
            </button>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>