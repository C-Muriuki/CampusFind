<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/student_guard.php';
require_once __DIR__ . '/../includes/upload_security.php';

$errors = [];
$old = [
    'item_name' => '',
    'item_type' => 'lost',
    'category_id' => '',
    'description' => '',
    'location' => '',
    'incident_date' => '',
    'contact_phone' => '',
    'contact_email' => '',
];

$categoryStmt = $pdo->query(
    "SELECT id, name FROM categories ORDER BY name ASC"
);
$categories = $categoryStmt->fetchAll();

$userStmt = $pdo->prepare(
    "SELECT email, phone FROM users WHERE id = :id LIMIT 1"
);
$userStmt->execute(['id' => currentUserId()]);
$student = $userStmt->fetch() ?: [];

$old['contact_email'] = $student['email'] ?? '';
$old['contact_phone'] = $student['phone'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $field => $default) {
        if (isset($_POST[$field]) && is_string($_POST[$field])) {
            $old[$field] = trim($_POST[$field]);
        }
    }

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session token is invalid. Refresh the page and try again.';
    }

    $validation = validateReportInput($old);
    $errors = array_merge($errors, $validation['errors']);
    $old = array_merge($old, $validation['clean']);

$imagePath = null;
$uploadedFile = $_FILES['item_image'] ?? null;

if (!$uploadedFile || $uploadedFile['error'] === UPLOAD_ERR_NO_FILE) {
    $errors[] = 'An item photo is required. Please upload a clear image of the item.';
} elseif ($uploadedFile['error'] !== UPLOAD_ERR_NO_FILE) {
    try {
        $generated = storeSecureReportImage($uploadedFile);

        if ($generated !== null) {
            $imagePath = 'uploads/reports/' . $generated;
        }
    } catch (RuntimeException $exception) {
        $errors[] = $exception->getMessage();
    }
}

    if (!$errors) {
        try {
            $insertStmt = $pdo->prepare(
                "INSERT INTO reports (
                    user_id,
                    category_id,
                    item_name,
                    item_type,
                    description,
                    location,
                    incident_date,
                    image_path,
                    contact_phone,
                    contact_email,
                    status,
                    created_at,
                    updated_at
                ) VALUES (
                    :user_id,
                    :category_id,
                    :item_name,
                    :item_type,
                    :description,
                    :location,
                    :incident_date,
                    :image_path,
                    :contact_phone,
                    :contact_email,
                    'pending',
                    NOW(),
                    NOW()
                )"
            );

            $insertStmt->execute([
                'user_id' => currentUserId(),
                'category_id' => $old['category_id'],
                'item_name' => $old['item_name'],
                'item_type' => $old['item_type'],
                'description' => $old['description'],
                'location' => $old['location'],
                'incident_date' => $old['incident_date'],
                'image_path' => $imagePath,
                'contact_phone' => $old['contact_phone'] ?: null,
                'contact_email' => $old['contact_email'] ?: null,
            ]);

            $reportId = (int) $pdo->lastInsertId();

            logActivityDb(
                'report_created',
                'Student submitted report #' . $reportId
            );

            createNotification(
                currentUserId(),
                'Report submitted',
                'Your report "' . $old['item_name'] . '" has been submitted and is awaiting review.',
                'report_update',
                $reportId
            );

            flash(
                'success',
                'Your report has been submitted and is awaiting review.'
            );

            redirect('/student/my-reports.php');
            exit;

        } catch (PDOException $exception) {
            if ($imagePath !== null) {
                $savedImage = ROOT_PATH . '/' . $imagePath;

                if (is_file($savedImage)) {
                    unlink($savedImage);
                }
            }

            error_log('CampusFind report creation error: ' . $exception->getMessage());
            $errors[] = 'Your report could not be saved. Please try again.';
        }
    }
}

$pageTitle = 'Report an Item';
require_once __DIR__ . '/../includes/student_header.php';
?>

<main class="student-main form-page">
    <div class="page-heading">
        <a class="back-link" href="<?= e(BASE_URL) ?>/student/dashboard.php">
            Back to dashboard
        </a>

        <p class="eyebrow">STUDENT PORTAL</p>
        <h1>Report an item</h1>
        <p class="muted">
            Provide accurate details to help the campus community identify the item.
        </p>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert">
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
          enctype="multipart/form-data"
          action="">

        <input type="hidden" name="csrf_token"
               value="<?= e(csrfToken()) ?>">

        <div class="form-section">
            <h2>Item information</h2>
            <p class="muted">Tell us what happened to the item.</p>

            <div class="form-group">
                <label for="item_type">Report type</label>
                <select id="item_type" name="item_type" required>
                    <option value="lost"
                        <?= $old['item_type'] === 'lost' ? 'selected' : '' ?>>
                        I lost an item
                    </option>
                    <option value="found"
                        <?= $old['item_type'] === 'found' ? 'selected' : '' ?>>
                        I found an item
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label for="item_name">Item name</label>
                <input
                    type="text"
                    id="item_name"
                    name="item_name"
                    maxlength="150"
                    placeholder="Example: Black HP laptop"
                    value="<?= e($old['item_name']) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option
                            value="<?= (int) $category['id'] ?>"
                            <?= (string) $old['category_id'] ===
                                (string) $category['id'] ? 'selected' : '' ?>
                        >
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea
                    id="description"
                    name="description"
                    rows="5"
                    maxlength="5000"
                    placeholder="Describe the item, including colour, brand, identifying marks or other useful details."
                    required
                ><?= e($old['description']) ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h2>Where and when?</h2>

            <div class="form-group">
                <label for="location">Campus location</label>
                <input
                    type="text"
                    id="location"
                    name="location"
                    maxlength="180"
                    placeholder="Example: Main library, second floor"
                    value="<?= e($old['location']) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="incident_date">Date lost or found</label>
                <input
                    type="date"
                    id="incident_date"
                    name="incident_date"
                    max="<?= date('Y-m-d') ?>"
                    value="<?= e($old['incident_date']) ?>"
                    required
                >
            </div>
        </div>

        <div class="form-section">
            <h2>Item photo</h2>
<p class="muted">
    A clear photo is required to help someone identify the item.
</p>

            <div class="form-group">
                <label for="item_image">Upload image</label>
                <input
                    type="file"
                    id="item_image"
                    name="item_image"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    required
                >
                <small>JPG, PNG or WebP. Maximum size: 5 MB.</small>
            </div>

            <div id="image-preview" class="image-preview" hidden>
                <img id="preview-image" src="" alt="Selected item preview">
                <button type="button" id="remove-image" class="text-link">
                    Remove image
                </button>
            </div>
        </div>

        <div class="form-section">
            <h2>Contact information</h2>
            <p class="muted">
                Provide contact details so someone can reach you about the item.
            </p>

            <div class="form-group">
                <label for="contact_phone">Phone number</label>
                <input
                    type="tel"
                    id="contact_phone"
                    name="contact_phone"
                    maxlength="30"
                    value="<?= e($old['contact_phone']) ?>"
                    placeholder="Your contact number"
                >
            </div>

            <div class="form-group">
                <label for="contact_email">Email address</label>
                <input
                    type="email"
                    id="contact_email"
                    name="contact_email"
                    maxlength="190"
                    value="<?= e($old['contact_email']) ?>"
                    placeholder="Your email address"
                >
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary"
               href="<?= e(BASE_URL) ?>/student/dashboard.php">
                Cancel
            </a>

            <button class="btn btn-primary" type="submit">
                Submit report
            </button>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../includes/student_footer.php'; ?>