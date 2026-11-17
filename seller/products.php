<?php
require __DIR__ . '/_auth.php';

$formErrorUrl = static function (string $message, ?int $productId = null): never {
    flash('error', $message);
    redirect($productId ? 'products.php?edit=' . $productId : 'products.php?add=1');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('products.php');
    }

    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);

    if ($action === 'delete') {
        if ($productId === false || $productId === null || $productId < 1) {
            $formErrorUrl('Choose a valid product.');
        }
        $productStatement = db()->prepare('SELECT id, image_path FROM products WHERE id = ? AND shop_id = ?');
        $productStatement->execute([$productId, $sellerShop['id']]);
        $product = $productStatement->fetch();
        if (!$product) {
            $formErrorUrl('That product could not be found in your shop.');
        }

        try {
            $deleteStatement = db()->prepare('DELETE FROM products WHERE id = ? AND shop_id = ?');
            $deleteStatement->execute([$productId, $sellerShop['id']]);
        } catch (PDOException $exception) {
            error_log('Seller product deletion failed: ' . $exception->getMessage());
            $formErrorUrl('This product cannot be deleted because it is referenced by order history.', (int) $productId);
        }

        remove_product_image($product['image_path']);
        flash('notice', 'Product deleted from your shop.');
        redirect('products.php');
    }

    if (!in_array($action, ['add', 'update'], true)) {
        $formErrorUrl('Choose a valid product action.');
    }
    if ($action === 'update' && ($productId === false || $productId === null || $productId < 1)) {
        $formErrorUrl('Choose a valid product.');
    }

    $existingProduct = null;
    if ($action === 'update') {
        $productStatement = db()->prepare('SELECT id, image_path FROM products WHERE id = ? AND shop_id = ?');
        $productStatement->execute([$productId, $sellerShop['id']]);
        $existingProduct = $productStatement->fetch();
        if (!$existingProduct) {
            $formErrorUrl('That product could not be found in your shop.');
        }
    }

    $name = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) && is_string($_POST['description']) ? trim($_POST['description']) : '';
    $rawPrice = isset($_POST['price']) && is_string($_POST['price']) ? trim($_POST['price']) : '';
    $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
    $rawCategoryId = $_POST['category_id'] ?? null;
    $categoryId = is_string($rawCategoryId) ? filter_var($rawCategoryId, FILTER_VALIDATE_INT) : false;

    if ($name === '' || strlen($name) > 160 || $description === '' || strlen($description) > 500) {
        $formErrorUrl('Enter a product name and description within the listed limits.', $productId !== false ? (int) $productId : null);
    }
    if (!is_int($categoryId) || $categoryId < 1) {
        $formErrorUrl('Choose a valid product classification.', $productId !== false ? (int) $productId : null);
    }
    $categoryStatement = db()->prepare('SELECT id FROM product_categories WHERE id = ?');
    $categoryStatement->execute([$categoryId]);
    if (!$categoryStatement->fetch()) {
        $formErrorUrl('Choose a valid product classification.', $productId !== false ? (int) $productId : null);
    }
    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $rawPrice)) {
        $formErrorUrl('Enter a price with up to 8 whole digits and 2 decimal places.', $productId !== false ? (int) $productId : null);
    }
    $priceCents = price_to_cents($rawPrice);
    if ($priceCents < 1 || $stock === false || $stock === null || $stock < 0 || $stock > 4294967295) {
        $formErrorUrl('Price must be greater than zero and stock must be a non-negative whole number.', $productId !== false ? (int) $productId : null);
    }

    $upload = $_FILES['image'] ?? null;
    if ($upload !== null && !is_array($upload)) {
        $formErrorUrl('Choose a valid product image.', $productId !== false ? (int) $productId : null);
    }
    try {
        $newImagePath = save_product_image($upload);
    } catch (InvalidArgumentException $exception) {
        $formErrorUrl($exception->getMessage(), $productId !== false ? (int) $productId : null);
    } catch (RuntimeException $exception) {
        error_log('Seller product image upload failed: ' . $exception->getMessage());
        $formErrorUrl('The product image could not be saved. Check that the uploads folder is writable.', $productId !== false ? (int) $productId : null);
    }

    $imagePath = $newImagePath ?? ($existingProduct['image_path'] ?? null);
    try {
        if ($action === 'update') {
            $updateStatement = db()->prepare(
                'UPDATE products
                 SET name = ?, description = ?, price = ?, stock = ?, category_id = ?, image_path = ?,
                     is_active = 0, approval_status = ?, review_note = NULL
                 WHERE id = ? AND shop_id = ?'
            );
            $updateStatement->execute([
                $name,
                $description,
                cents_to_price($priceCents),
                $stock,
                $categoryId,
                $imagePath,
                'pending',
                $productId,
                $sellerShop['id']
            ]);
        } else {
            $insertStatement = db()->prepare(
                'INSERT INTO products
                 (shop_id, name, description, seller_name, seller_details, price, stock, category_id, image_path, is_active, approval_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)'
            );
            $insertStatement->execute([
                $sellerShop['id'],
                $name,
                $description,
                $sellerShop['store_name'],
                $sellerShop['description'],
                cents_to_price($priceCents),
                $stock,
                $categoryId,
                $imagePath,
                'pending'
            ]);
        }
    } catch (PDOException $exception) {
        remove_product_image($newImagePath);
        throw $exception;
    }

    if ($newImagePath !== null && $existingProduct) {
        remove_product_image($existingProduct['image_path']);
    }
    flash('notice', $action === 'update'
        ? 'Product changes sent for admin review. The item is hidden until approval.'
        : 'Product submitted for admin review and will appear in the shop after approval.');
    redirect('products.php');
}

$editProduct = null;
if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($editId === false || $editId === null || $editId < 1) {
        flash('error', 'Choose a valid product to edit.');
        redirect('products.php');
    }
    $editStatement = db()->prepare(
        'SELECT id, name, description, price, stock, category_id, image_path
         FROM products
         WHERE id = ? AND shop_id = ?'
    );
    $editStatement->execute([$editId, $sellerShop['id']]);
    $editProduct = $editStatement->fetch();
    if (!$editProduct) {
        flash('error', 'That product could not be found in your shop.');
        redirect('products.php');
    }
}

$categories = db()->query('SELECT id, name FROM product_categories ORDER BY name')->fetchAll();
$defaultCategoryId = (int) ($categories[0]['id'] ?? 0);
foreach ($categories as $category) {
    if ($category['name'] === 'Everyday Essentials') {
        $defaultCategoryId = (int) $category['id'];
        break;
    }
}
$productsStatement = db()->prepare(
    'SELECT id, name, description, price, stock, approval_status, review_note, is_active, created_at
     FROM products
     WHERE shop_id = ?
     ORDER BY created_at DESC'
);
$productsStatement->execute([$sellerShop['id']]);
$products = $productsStatement->fetchAll();
$notice = flash('notice');
$error = flash('error');
$showForm = $editProduct !== null || isset($_GET['add']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage products | <?= e($sellerShop['store_name']) ?></title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<?php require __DIR__ . '/_header.php'; ?>
<main class="page-main seller-dashboard">
    <div class="seller-dashboard-heading">
        <div><p class="eyebrow"><?= e($sellerShop['store_name']) ?></p><h1 class="page-title">Manage products</h1></div>
        <a class="button secondary" href="index.php">Seller dashboard</a>
    </div>
    <?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>

    <?php if ($showForm): ?>
        <section class="seller-product-form-panel">
            <div class="profile-form-heading">
                <h2><?= $editProduct ? 'Edit product' : 'Add a product' ?></h2>
                <p><?= $editProduct ? 'Saving changes sends the product back for admin review and hides it until approval.' : 'All new products are reviewed before appearing in the storefront.' ?></p>
            </div>
            <form class="admin-product-form seller-product-form" method="post" action="products.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $editProduct ? 'update' : 'add' ?>">
                <?php if ($editProduct): ?><input type="hidden" name="product_id" value="<?= (int) $editProduct['id'] ?>"><?php endif; ?>
                <label for="seller-product-name">Product name</label>
                <input id="seller-product-name" name="name" maxlength="160" value="<?= $editProduct ? e($editProduct['name']) : '' ?>" required>
                <label for="seller-product-description">Description</label>
                <textarea id="seller-product-description" name="description" rows="4" maxlength="500" required><?= $editProduct ? e($editProduct['description']) : '' ?></textarea>
                <label for="seller-product-category">Classification</label>
                <select id="seller-product-category" name="category_id" required>
                    <option value="">Choose a classification</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= (int) ($editProduct['category_id'] ?? $defaultCategoryId) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($editProduct && $editProduct['image_path']): ?>
                    <img class="admin-edit-product-image" src="../<?= e($editProduct['image_path']) ?>" alt="<?= e($editProduct['name']) ?>">
                <?php endif; ?>
                <label for="seller-product-image"><?= $editProduct ? 'Replace product image (optional)' : 'Product image (optional)' ?></label>
                <input id="seller-product-image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
                <small class="admin-note">JPG, PNG, or WebP. Maximum 5 MB.</small>
                <div class="admin-form-row">
                    <div><label for="seller-product-price">Price (₱)</label><input id="seller-product-price" type="number" name="price" min="0.01" max="99999999.99" step="0.01" value="<?= $editProduct ? e((string) $editProduct['price']) : '' ?>" required></div>
                    <div><label for="seller-product-stock">Stock</label><input id="seller-product-stock" type="number" name="stock" min="0" max="4294967295" step="1" value="<?= $editProduct ? (int) $editProduct['stock'] : '0' ?>" required></div>
                </div>
                <div class="admin-form-actions">
                    <button class="button" type="submit"><?= $editProduct ? 'Submit changes for review' : 'Submit product for review' ?></button>
                    <a class="button secondary" href="products.php">Cancel</a>
                </div>
            </form>
        </section>
    <?php else: ?>
        <section class="admin-panel seller-products-panel">
            <div class="panel-heading">
                <div><p class="eyebrow">YOUR CATALOG</p><h2>Products</h2></div>
                <a class="button" href="products.php?add=1">Add product</a>
            </div>
            <?php if (!$products): ?>
                <div class="admin-empty">Your shop has no products yet.</div>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Product</th><th>Price</th><th>Stock</th><th>Review status</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><strong><?= e($product['name']) ?></strong><?php if ($product['review_note']): ?><small>Review note: <?= e($product['review_note']) ?></small><?php endif; ?></td>
                                <td class="table-money"><?= money($product['price']) ?></td>
                                <td><?= number_format((int) $product['stock']) ?></td>
                                <td><span class="seller-review-status status-<?= e($product['approval_status']) ?>"><?= e(ucfirst($product['approval_status'])) ?></span></td>
                                <td>
                                    <div class="product-admin-actions">
                                        <a class="button small secondary" href="products.php?edit=<?= (int) $product['id'] ?>">Edit</a>
                                        <form method="post" action="products.php" onsubmit="return confirm('Delete this product from your shop?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                            <button class="button small danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <p class="admin-note">Pending and rejected products are not visible to shoppers. Editing a product sends it back through admin review.</p>
        </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/_footer.php'; ?>
</body>
</html>
