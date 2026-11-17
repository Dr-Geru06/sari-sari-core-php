<?php
require __DIR__ . '/_auth.php';

function save_product_image(?array $upload): ?string
{
    if ($upload === null) {
        return null;
    }

    $error = filter_var($upload['error'] ?? null, FILTER_VALIDATE_INT);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('The image upload failed. Choose an image under 5 MB and try again.');
    }

    $temporaryPath = $upload['tmp_name'] ?? null;
    if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('Choose a valid product image and try again.');
    }

    $fileSize = filesize($temporaryPath);
    if ($fileSize === false || $fileSize < 1 || $fileSize > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Product images must be smaller than 5 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mime) || !isset($extensions[$mime])) {
        throw new InvalidArgumentException('Use a JPG, PNG, or WebP image.');
    }

    $imageInfo = @getimagesize($temporaryPath);
    if (!is_array($imageInfo) || ($imageInfo['mime'] ?? null) !== $mime) {
        throw new InvalidArgumentException('The uploaded file is not a valid image.');
    }

    $width = (int) $imageInfo[0];
    $height = (int) $imageInfo[1];
    if ($width < 1 || $height < 1 || $width > 6000 || $height > 6000 || $width * $height > 20000000) {
        throw new InvalidArgumentException('Images must be at most 6000 pixels per side and 20 megapixels.');
    }

    $uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Unable to create the product image folder.');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($temporaryPath, $uploadDirectory . DIRECTORY_SEPARATOR . $fileName)) {
        throw new RuntimeException('Unable to save the uploaded product image.');
    }

    return 'uploads/products/' . $fileName;
}

function remove_product_image(?string $imagePath): void
{
    if ($imagePath === null || !preg_match('#\Auploads/products/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $imagePath)) {
        return;
    }

    $filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . basename($imagePath);
    if (is_file($filePath) && !unlink($filePath)) {
        error_log('Could not remove product image: ' . $filePath);
    }
}

function product_form_values(): array
{
    $name = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) && is_string($_POST['description']) ? trim($_POST['description']) : '';
    $sellerName = isset($_POST['seller_name']) && is_string($_POST['seller_name']) ? trim($_POST['seller_name']) : '';
    $sellerDetails = isset($_POST['seller_details']) && is_string($_POST['seller_details']) ? trim($_POST['seller_details']) : '';
    $rawPrice = isset($_POST['price']) && is_string($_POST['price']) ? trim($_POST['price']) : '';
    $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
    $rawCategoryId = $_POST['category_id'] ?? null;
    $categoryId = is_string($rawCategoryId) ? filter_var($rawCategoryId, FILTER_VALIDATE_INT) : false;

    if ($name === '' || strlen($name) > 160 || $description === '' || strlen($description) > 500) {
        throw new InvalidArgumentException('Enter a product name and description within the listed limits.');
    }
    if (!is_int($categoryId) || $categoryId < 1) {
        throw new InvalidArgumentException('Choose a valid product classification.');
    }
    $categoryStatement = db()->prepare('SELECT id FROM product_categories WHERE id = ?');
    $categoryStatement->execute([$categoryId]);
    if (!$categoryStatement->fetch()) {
        throw new InvalidArgumentException('Choose a valid product classification.');
    }
    if ($sellerName === '' || strlen($sellerName) > 120 || strlen($sellerDetails) > 500) {
        throw new InvalidArgumentException('Enter a seller name and keep seller details within 500 characters.');
    }
    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $rawPrice)) {
        throw new InvalidArgumentException('Enter a price with up to 8 whole digits and 2 decimal places.');
    }
    $priceCents = price_to_cents($rawPrice);
    if ($priceCents < 1 || $stock === false || $stock === null || $stock < 0 || $stock > 4294967295) {
        throw new InvalidArgumentException('Price must be greater than zero and stock must be a non-negative whole number.');
    }

    return [$name, $description, $sellerName, $sellerDetails, cents_to_price($priceCents), $stock, $categoryId];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('products.php');
    }

    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : 'add';
    $upload = $_FILES['image'] ?? null;
    if ($upload !== null && !is_array($upload)) {
        flash('error', 'Choose a valid product image.');
        redirect('products.php');
    }

    if ($action === 'update') {
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        if ($productId === false || $productId === null || $productId < 1) {
            flash('error', 'Choose a valid product.');
            redirect('products.php');
        }

        $productStatement = db()->prepare('SELECT id, image_path FROM products WHERE id = ?');
        $productStatement->execute([$productId]);
        $product = $productStatement->fetch();
        if (!$product) {
            flash('error', 'That product could not be found.');
            redirect('products.php');
        }

        try {
            [$name, $description, $sellerName, $sellerDetails, $price, $stock, $categoryId] = product_form_values();
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
            redirect('products.php?edit=' . $productId);
        }

        try {
            $newImagePath = save_product_image($upload);
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
            redirect('products.php?edit=' . $productId);
        } catch (RuntimeException $exception) {
            error_log('Product image upload failed: ' . $exception->getMessage());
            flash('error', 'The product image could not be saved. Check that the uploads folder is writable.');
            redirect('products.php?edit=' . $productId);
        }

        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $imagePath = $newImagePath ?? $product['image_path'];

        try {
            $updateStatement = db()->prepare(
                'UPDATE products SET name = ?, description = ?, seller_name = ?, seller_details = ?, price = ?, stock = ?, category_id = ?, image_path = ?, is_active = ? WHERE id = ?'
            );
            $updateStatement->execute([$name, $description, $sellerName, $sellerDetails, $price, $stock, $categoryId, $imagePath, $isActive, $productId]);
        } catch (PDOException $exception) {
            remove_product_image($newImagePath);
            throw $exception;
        }

        if ($newImagePath !== null) {
            remove_product_image($product['image_path']);
        }
        flash('notice', 'Product updated.');
        redirect('products.php');
    }

    if ($action === 'delete') {
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        if ($productId === false || $productId === null || $productId < 1) {
            flash('error', 'Choose a valid product.');
            redirect('products.php');
        }

        $productStatement = db()->prepare('SELECT image_path FROM products WHERE id = ?');
        $productStatement->execute([$productId]);
        $product = $productStatement->fetch();
        if (!$product) {
            flash('error', 'That product could not be found.');
            redirect('products.php');
        }

        try {
            $deleteStatement = db()->prepare('DELETE FROM products WHERE id = ?');
            $deleteStatement->execute([$productId]);
        } catch (PDOException $exception) {
            error_log('Product deletion failed: ' . $exception->getMessage());
            flash('error', 'The product could not be deleted. Its order history was left unchanged.');
            redirect('products.php');
        }

        remove_product_image($product['image_path']);
        flash('notice', 'Product permanently deleted. Past order items remain in order history.');
        redirect('products.php');
    }

    if ($action !== 'add') {
        flash('error', 'Choose a valid product action.');
        redirect('products.php');
    }

    try {
        [$name, $description, $sellerName, $sellerDetails, $price, $stock, $categoryId] = product_form_values();
    } catch (InvalidArgumentException $exception) {
        flash('error', $exception->getMessage());
        redirect('products.php?add=1');
    }

    try {
        $imagePath = save_product_image($upload);
    } catch (InvalidArgumentException $exception) {
        flash('error', $exception->getMessage());
        redirect('products.php?add=1');
    } catch (RuntimeException $exception) {
        error_log('Product image upload failed: ' . $exception->getMessage());
        flash('error', 'The product image could not be saved. Check that the uploads folder is writable.');
        redirect('products.php?add=1');
    }

    try {
        $statement = db()->prepare('INSERT INTO products (name, description, seller_name, seller_details, price, stock, category_id, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([$name, $description, $sellerName, $sellerDetails, $price, $stock, $categoryId, $imagePath]);
    } catch (PDOException $exception) {
        remove_product_image($imagePath);
        throw $exception;
    }

    flash('notice', 'Product added to the catalog.');
    redirect('products.php');
}

$editProduct = null;
if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($editId === false || $editId === null || $editId < 1) {
        flash('error', 'Choose a valid product to edit.');
        redirect('products.php');
    }

    $editStatement = db()->prepare('SELECT id, name, description, seller_name, seller_details, price, stock, category_id, image_path, is_active FROM products WHERE id = ?');
    $editStatement->execute([$editId]);
    $editProduct = $editStatement->fetch();
    if (!$editProduct) {
        flash('error', 'That product could not be found.');
        redirect('products.php');
    }
}

$pageTitle = 'Products';
$activePage = 'products';
$notice = flash('notice');
$error = flash('error');
$categories = db()->query('SELECT id, name FROM product_categories ORDER BY name')->fetchAll();
$defaultCategoryId = null;
foreach ($categories as $category) {
    if ($category['name'] === 'Everyday Essentials') {
        $defaultCategoryId = (int) $category['id'];
        break;
    }
}
$products = db()->query(
    'SELECT p.id, p.name, p.description, p.seller_name, p.seller_details, p.price, p.stock, p.image_path, p.is_active, p.created_at, c.name AS category_name
     FROM products p
     INNER JOIN product_categories c ON c.id = p.category_id
     ORDER BY p.created_at DESC'
)->fetchAll();
$openProductModal = $editProduct !== null || isset($_GET['add']);

require __DIR__ . '/_header.php';
?>
<?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
<section class="admin-panel product-list-panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">PRODUCT CATALOG</p>
            <h2>Products</h2>
            <span class="panel-caption"><?= count($products) ?> products</span>
        </div>
        <div class="product-list-actions">
            <a class="button secondary category-manage-link" href="categories.php">Classifications</a>
            <button class="button" type="button" data-modal-open="product-modal">Add product</button>
        </div>
    </div>
    <?php if (!$products): ?>
        <div class="admin-empty">Add your first product to start building the catalog.</div>
    <?php else: ?>
        <div class="admin-table-wrap product-table-wrap">
            <table class="admin-table product-admin-table">
                <colgroup>
                    <col class="product-column">
                    <col class="price-column">
                    <col class="inventory-column">
                    <col class="actions-column">
                </colgroup>
                <thead><tr><th scope="col">Product</th><th scope="col">Price</th><th scope="col">Stock / status</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <div class="product-table-item">
                                <?php if ($product['image_path']): ?>
                                    <img class="admin-product-thumb" src="../<?= e($product['image_path']) ?>" alt="">
                                <?php else: ?>
                                    <span class="admin-no-image">No image</span>
                                <?php endif; ?>
                                <div class="product-table-copy">
                                    <strong><?= e($product['name']) ?></strong>
                                    <small class="product-table-description"><?= e($product['description']) ?></small>
                                    <small class="product-table-category">Classification: <?= e($product['category_name']) ?></small>
                                    <small class="product-table-seller">Seller: <?= e($product['seller_name']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td class="table-money"><?= money($product['price']) ?></td>
                        <td>
                            <div class="product-inventory-cell">
                                <span class="stock-count <?= (int) $product['stock'] <= 5 ? 'stock-low' : '' ?>"><?= (int) $product['stock'] ?></span>
                                <span class="product-state-pill <?= (int) $product['is_active'] === 1 ? 'is-visible' : '' ?>"><?= (int) $product['is_active'] === 1 ? 'Visible' : 'Hidden' ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="product-admin-actions">
                                <a class="button small secondary" href="products.php?edit=<?= (int) $product['id'] ?>">Edit</a>
                                <form method="post" action="products.php" onsubmit="return confirm('Permanently delete this product? Past order records will be retained.');">
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
    <p class="admin-note">Deleting a product permanently removes it from the catalog. Past order items retain their saved name and price.</p>
</section>
<dialog class="admin-modal" id="product-modal" aria-labelledby="product-modal-title" <?= $openProductModal ? 'data-auto-open' : '' ?>>
    <div class="admin-modal-heading">
        <div>
            <p class="eyebrow">CATALOG</p>
            <h2 id="product-modal-title"><?= $editProduct ? 'Edit product' : 'Add a product' ?></h2>
        </div>
        <button class="modal-close" type="button" data-modal-close aria-label="Close dialog">&times;</button>
    </div>
    <?php if ($editProduct): ?>
        <form class="admin-product-form" method="post" action="products.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="product_id" value="<?= (int) $editProduct['id'] ?>">
            <label for="product-name">Product name</label>
            <input id="product-name" name="name" maxlength="160" value="<?= e($editProduct['name']) ?>" required>
            <label for="product-description">Description</label>
            <textarea id="product-description" name="description" rows="4" maxlength="500" required><?= e($editProduct['description']) ?></textarea>
            <label for="product-category">Classification</label>
            <select id="product-category" name="category_id" required>
                <option value="">Choose a classification</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= (int) $editProduct['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="product-seller-name">Seller name</label>
            <input id="product-seller-name" name="seller_name" maxlength="120" value="<?= e($editProduct['seller_name']) ?>" required>
            <label for="product-seller-details">Seller details</label>
            <textarea id="product-seller-details" name="seller_details" rows="3" maxlength="500"><?= e((string) $editProduct['seller_details']) ?></textarea>
            <?php if ($editProduct['image_path']): ?>
                <img class="admin-edit-product-image" src="../<?= e($editProduct['image_path']) ?>" alt="<?= e($editProduct['name']) ?>">
            <?php endif; ?>
            <label for="new-product-image">Replace product image (optional)</label>
            <input id="new-product-image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
            <small class="admin-note">JPG, PNG, or WebP. Maximum 5 MB. Leave empty to keep the current image.</small>
            <div class="admin-form-row">
                <div><label for="product-price">Price (₱)</label><input id="product-price" type="number" name="price" min="0.01" max="99999999.99" step="0.01" value="<?= e((string) $editProduct['price']) ?>" required></div>
                <div><label for="product-stock">Stock</label><input id="product-stock" type="number" name="stock" min="0" max="4294967295" step="1" value="<?= (int) $editProduct['stock'] ?>" required></div>
            </div>
            <label class="admin-checkbox-row"><input type="checkbox" name="is_active" value="1" <?= (int) $editProduct['is_active'] === 1 ? 'checked' : '' ?>> Visible in the storefront</label>
            <div class="admin-form-actions">
                <button class="button checkout-button" type="submit">Save changes</button>
                <button class="button secondary" type="button" data-modal-close>Cancel</button>
            </div>
        </form>
    <?php else: ?>
        <form class="admin-product-form" method="post" action="products.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add">
            <label for="product-name">Product name</label>
            <input id="product-name" name="name" maxlength="160" required>
            <label for="product-description">Description</label>
            <textarea id="product-description" name="description" rows="4" maxlength="500" required></textarea>
            <label for="product-category">Classification</label>
            <select id="product-category" name="category_id" required>
                <option value="">Choose a classification</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= $defaultCategoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="product-seller-name">Seller name</label>
            <input id="product-seller-name" name="seller_name" maxlength="120" value="Sari-Sari Store" required>
            <label for="product-seller-details">Seller details</label>
            <textarea id="product-seller-details" name="seller_details" rows="3" maxlength="500"></textarea>
            <label for="new-product-image">Product image (optional)</label>
            <input id="new-product-image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
            <small class="admin-note">JPG, PNG, or WebP. Maximum 5 MB.</small>
            <div class="admin-form-row">
                <div><label for="product-price">Price (₱)</label><input id="product-price" type="number" name="price" min="0.01" max="99999999.99" step="0.01" required></div>
                <div><label for="product-stock">Starting stock</label><input id="product-stock" type="number" name="stock" min="0" max="4294967295" step="1" value="0" required></div>
            </div>
            <div class="admin-form-actions">
                <button class="button checkout-button" type="submit">Add product</button>
                <button class="button secondary" type="button" data-modal-close>Cancel</button>
            </div>
        </form>
    <?php endif; ?>
</dialog>
<?php require __DIR__ . '/_footer.php'; ?>
