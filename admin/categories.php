<?php
require __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('categories.php');
    }

    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : 'add';
    if ($action === 'add' || $action === 'update') {
        $categoryId = null;
        if ($action === 'update') {
            $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
            if ($categoryId === false || $categoryId === null || $categoryId < 1) {
                flash('error', 'Choose a valid classification to update.');
                redirect('categories.php');
            }

            $categoryStatement = db()->prepare('SELECT id FROM product_categories WHERE id = ?');
            $categoryStatement->execute([$categoryId]);
            if (!$categoryStatement->fetch()) {
                flash('error', 'That classification could not be found.');
                redirect('categories.php');
            }
        }

        $name = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
        if ($name === '' || strlen($name) > 80) {
            flash('error', 'Enter a classification name of up to 80 characters.');
            redirect($action === 'update' ? 'categories.php?edit=' . $categoryId : 'categories.php');
        }

        $existingCategory = db()->prepare(
            'SELECT id FROM product_categories WHERE name = ? AND id <> ?'
        );
        $existingCategory->execute([$name, $categoryId ?? 0]);
        if ($existingCategory->fetch()) {
            flash('error', 'A classification with that name already exists.');
            redirect($action === 'update' ? 'categories.php?edit=' . $categoryId : 'categories.php');
        }

        try {
            if ($action === 'update') {
                $saveCategory = db()->prepare('UPDATE product_categories SET name = ? WHERE id = ?');
                $saveCategory->execute([$name, $categoryId]);
                flash('notice', 'Classification updated.');
            } else {
                $saveCategory = db()->prepare('INSERT INTO product_categories (name) VALUES (?)');
                $saveCategory->execute([$name]);
                flash('notice', 'Classification added.');
            }
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }
            flash('error', 'A classification with that name already exists.');
            redirect($action === 'update' ? 'categories.php?edit=' . $categoryId : 'categories.php');
        }

        redirect('categories.php');
    }

    if ($action === 'delete') {
        $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
        if ($categoryId === false || $categoryId === null || $categoryId < 1) {
            flash('error', 'Choose a valid classification to delete.');
            redirect('categories.php');
        }

        $categoryStatement = db()->prepare('SELECT id FROM product_categories WHERE id = ?');
        $categoryStatement->execute([$categoryId]);
        if (!$categoryStatement->fetch()) {
            flash('error', 'That classification could not be found.');
            redirect('categories.php');
        }

        $productCountStatement = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $productCountStatement->execute([$categoryId]);
        if ((int) $productCountStatement->fetchColumn() > 0) {
            flash('error', 'Reassign or remove this classification\'s products before deleting it.');
            redirect('categories.php');
        }

        try {
            $deleteCategory = db()->prepare('DELETE FROM product_categories WHERE id = ?');
            $deleteCategory->execute([$categoryId]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1451) {
                throw $exception;
            }
            flash('error', 'This classification now has products assigned. Reassign them before deleting it.');
            redirect('categories.php');
        }

        flash('notice', 'Classification deleted.');
        redirect('categories.php');
    }

    flash('error', 'Choose a valid classification action.');
    redirect('categories.php');
}

$editCategory = null;
if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($editId === false || $editId === null || $editId < 1) {
        flash('error', 'Choose a valid classification to edit.');
        redirect('categories.php');
    }

    $editStatement = db()->prepare('SELECT id, name FROM product_categories WHERE id = ?');
    $editStatement->execute([$editId]);
    $editCategory = $editStatement->fetch();
    if (!$editCategory) {
        flash('error', 'That classification could not be found.');
        redirect('categories.php');
    }
}

$pageTitle = 'Classifications';
$activePage = 'categories';
$notice = flash('notice');
$error = flash('error');
$categories = db()->query(
    'SELECT c.id, c.name, COUNT(p.id) AS product_count
     FROM product_categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id, c.name
     ORDER BY c.name'
)->fetchAll();

require __DIR__ . '/_header.php';
?>
<?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
<div class="category-admin-grid">
    <section class="admin-panel">
        <div class="panel-heading"><div><p class="eyebrow">PRODUCT ORGANIZATION</p><h2>Add a classification</h2></div></div>
        <p class="admin-note">Classifications appear as filters in the storefront. Add a category, then assign it to products in Products.</p>
        <form class="admin-category-form" method="post" action="categories.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add">
            <label for="category-name">Classification name</label>
            <input id="category-name" name="name" maxlength="80" placeholder="For example, Food" required>
            <button class="button" type="submit">Add classification</button>
        </form>
    </section>
    <section class="admin-panel">
        <div class="panel-heading">
            <div><p class="eyebrow">STORE CATEGORIES</p><h2>Classifications</h2></div>
            <span class="panel-caption"><?= count($categories) ?> total</span>
        </div>
        <?php if (!$categories): ?>
            <div class="admin-empty">No classifications have been added.</div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Classification</th><th>Products</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><strong><?= e($category['name']) ?></strong></td>
                            <td><?= number_format((int) $category['product_count']) ?></td>
                            <td>
                                <div class="product-admin-actions">
                                    <a class="button small secondary" href="categories.php?edit=<?= (int) $category['id'] ?>">Edit</a>
                                    <?php if ((int) $category['product_count'] > 0): ?>
                                        <button class="button small danger" type="button" disabled title="Reassign products before deleting this classification">Delete</button>
                                    <?php else: ?>
                                        <button
                                            class="button small danger"
                                            type="button"
                                            data-modal-open="category-delete-<?= (int) $category['id'] ?>">Delete</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <a class="button secondary category-products-link" href="products.php">Manage product classifications</a>
    </section>
</div>
<dialog class="admin-modal" id="category-edit-modal" aria-labelledby="category-edit-title" <?= $editCategory ? 'data-auto-open' : '' ?>>
    <div class="admin-modal-heading">
        <div><p class="eyebrow">PRODUCT ORGANIZATION</p><h2 id="category-edit-title">Edit classification</h2></div>
        <button class="modal-close" type="button" data-modal-close aria-label="Close dialog">&times;</button>
    </div>
    <form class="admin-category-form" method="post" action="categories.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="category_id" value="<?= $editCategory ? (int) $editCategory['id'] : '' ?>">
        <label for="category-edit-name">Classification name</label>
        <input id="category-edit-name" name="name" maxlength="80" value="<?= $editCategory ? e($editCategory['name']) : '' ?>" required>
        <div class="admin-form-actions">
            <button class="button small" type="submit">Save changes</button>
            <button class="button small secondary" type="button" data-modal-close>Cancel</button>
        </div>
    </form>
</dialog>
<?php foreach ($categories as $category): ?>
    <?php if ((int) $category['product_count'] === 0): ?>
        <dialog class="admin-modal" id="category-delete-<?= (int) $category['id'] ?>" aria-labelledby="category-delete-title-<?= (int) $category['id'] ?>">
            <div class="admin-modal-heading">
                <div><p class="eyebrow">PRODUCT ORGANIZATION</p><h2 id="category-delete-title-<?= (int) $category['id'] ?>">Delete classification?</h2></div>
                <button class="modal-close" type="button" data-modal-close aria-label="Close dialog">&times;</button>
            </div>
            <form method="post" action="categories.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                <p class="customer-delete-copy">Delete <strong><?= e($category['name']) ?></strong>? This permanently removes the classification.</p>
                <div class="admin-form-actions">
                    <button class="button small danger" type="submit">Delete classification</button>
                    <button class="button small secondary" type="button" data-modal-close>Cancel</button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>
<?php endforeach; ?>
<?php require __DIR__ . '/_footer.php'; ?>
