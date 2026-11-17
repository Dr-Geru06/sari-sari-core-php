<?php
require __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('categories.php');
    }

    $name = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
    if ($name === '' || strlen($name) > 80) {
        flash('error', 'Enter a classification name of up to 80 characters.');
        redirect('categories.php');
    }

    $existingCategory = db()->prepare('SELECT id FROM product_categories WHERE name = ?');
    $existingCategory->execute([$name]);
    if ($existingCategory->fetch()) {
        flash('error', 'A classification with that name already exists.');
        redirect('categories.php');
    }

    try {
        $insertCategory = db()->prepare('INSERT INTO product_categories (name) VALUES (?)');
        $insertCategory->execute([$name]);
    } catch (PDOException $exception) {
        if ($exception->getCode() !== '23000') {
            throw $exception;
        }
        flash('error', 'A classification with that name already exists.');
        redirect('categories.php');
    }

    flash('notice', 'Classification added.');
    redirect('categories.php');
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
                    <thead><tr><th>Classification</th><th>Products</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><strong><?= e($category['name']) ?></strong></td>
                            <td><?= number_format((int) $category['product_count']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <a class="button secondary category-products-link" href="products.php">Manage product classifications</a>
    </section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
