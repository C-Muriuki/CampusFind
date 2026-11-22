<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';

$pdo = db();

$pageTitle = 'Categories';

/* Handle create / update / toggle actions. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Your session could not be verified. Please try again.');
        redirect('/admin/categories.php');
    }

    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    if ($action === 'toggle') {
        if (!$id) {
            flash('error', 'Invalid category.');
            redirect('/admin/categories.php');
        }

        $pdo->prepare('UPDATE categories SET is_active = 1 - is_active WHERE id = ?')
            ->execute([$id]);
        logActivityDb('toggle_category', 'Toggled category #' . $id);
        flash('success', 'Category status updated.');
        redirect('/admin/categories.php');
    }

    if ($name === '') {
        flash('error', 'Category name is required.');
        redirect('/admin/categories.php');
    }

    if (adminTextLength($name) > 100) {
        flash('error', 'Category name is too long (max 100 characters).');
        redirect('/admin/categories.php');
    }

    if (adminTextLength($description) > 255) {
        flash('error', 'Category description is too long (max 255 characters).');
        redirect('/admin/categories.php');
    }

    if ($action === 'create') {
        $exists = $pdo->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
        $exists->execute([$name]);

        if ($exists->fetch()) {
            flash('error', 'A category with that name already exists.');
            redirect('/admin/categories.php');
        }

        $pdo->prepare('INSERT INTO categories (name, description, is_active) VALUES (?, ?, 1)')
            ->execute([$name, $description !== '' ? $description : null]);
        logActivityDb('create_category', 'Created category: ' . $name);
        flash('success', 'Category created.');
        redirect('/admin/categories.php');
    }

    if ($action === 'update') {
        if (!$id) {
            flash('error', 'Invalid category.');
            redirect('/admin/categories.php');
        }

        $exists = $pdo->prepare('SELECT id FROM categories WHERE name = ? AND id <> ? LIMIT 1');
        $exists->execute([$name, $id]);

        if ($exists->fetch()) {
            flash('error', 'Another category already uses that name.');
            redirect('/admin/categories.php');
        }

        $pdo->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?')
            ->execute([$name, $description !== '' ? $description : null, $id]);
        logActivityDb('update_category', 'Updated category #' . $id . ' (' . $name . ')');
        flash('success', 'Category updated.');
        redirect('/admin/categories.php');
    }

    flash('error', 'Unknown category action.');
    redirect('/admin/categories.php');
}

$categories = $pdo->query(
    "SELECT c.id, c.name, c.description, c.is_active,
            (SELECT COUNT(*) FROM reports r WHERE r.category_id = c.id) AS reports_count
     FROM categories c
     ORDER BY c.name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="page-intro">
    <div>
        <span class="eyebrow">TAXONOMY</span>
        <h2>Report categories</h2>
        <p>Create and maintain the categories students choose when reporting items.</p>
    </div>
    <span class="result-count"><?= number_format(count($categories)) ?> categories</span>
</div>

<section class="panel">
    <div class="panel-heading">
        <div>
            <span class="eyebrow">NEW CATEGORY</span>
            <h2>Add a category</h2>
        </div>
    </div>

    <form method="POST" class="filter-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="create">
        <input type="text" name="name" placeholder="Category name" required maxlength="100">
        <input type="text" name="description" placeholder="Short description (optional)" maxlength="255">
        <button class="btn btn-primary" type="submit">Create category</button>
    </form>
</section>

<section class="panel" style="margin-top:18px">
    <div class="panel-heading">
        <div>
            <span class="eyebrow">EXISTING</span>
            <h2>All categories</h2>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Reports</th>
                    <th>Status</th>
                    <th>Edit</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $category): ?>
                <tr>
                    <td style="min-width:220px">
                        <form method="POST" class="inline-edit">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                            <input type="text" name="name" value="<?= e($category['name']) ?>" required maxlength="100">
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                        </form>
                    </td>
                    <td style="min-width:220px">
                        <form method="POST" class="inline-edit">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                            <input type="hidden" name="name" value="<?= e($category['name']) ?>">
                            <input type="text" name="description" value="<?= e($category['description'] ?? '') ?>" maxlength="255" placeholder="—">
                            <button type="submit" class="btn btn-sm btn-ghost">Save note</button>
                        </form>
                    </td>
                    <td><?= (int) $category['reports_count'] ?></td>
                    <td>
                        <?php if ((int) $category['is_active'] === 1): ?>
                            <span class="status-badge badge-active">Active</span>
                        <?php else: ?>
                            <span class="status-badge badge-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td></td>
                    <td>
                        <form method="POST" data-confirm="Change this category's active status?">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                            <?php if ((int) $category['is_active'] === 1): ?>
                                <button type="submit" class="btn btn-sm btn-danger">Deactivate</button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-sm btn-primary">Activate</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>