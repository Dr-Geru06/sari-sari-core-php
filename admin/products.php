<?php
require __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('products.php');
    }

    $name = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) && is_string($_POST['description']) ? trim($_POST['description']) : '';
    $rawPrice = isset($_POST['price']) && is_string($_POST['price']) ? trim($_POST['price']) : '';
    $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);

    if ($name === '' || strlen($name) > 160 || $description === '' || strlen($description) > 500) {
        flash('error', 'Enter a product name and description within the listed limits.');
        redirect('products.php');
    }
    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $rawPrice)) {
        flash('error', 'Enter a price with up to 8 whole digits and 2 decimal places.');
        redirect('products.php');
    }
    $priceCents = price_to_cents($rawPrice);
    if ($priceCents < 1 || $stock === false || $stock === null || $stock < 0 || $stock > 4294967295) {
        flash('error', 'Price must be greater than zero and stock must be a non-negative whole number.');
        redirect('products.php');
    }

    $statement = db()->prepare('INSERT INTO products (name, description, price, stock) VALUES (?, ?, ?, ?)');
    $statement->execute([$name, $description, cents_to_price($priceCents), $stock]);
    flash('notice', 'Product added to the catalog.');
    redirect('products.php');
}

$pageTitle = 'Products';
$activePage = 'products';
$notice = flash('notice');
$error = flash('error');
$products = db()->query('SELECT id, name, description, price, stock, created_at FROM products WHERE is_active = 1 ORDER BY created_at DESC LIMIT 100')->fetchAll();

require __DIR__ . '/_header.php';
?>
<?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
<div class="products-admin-grid">
    <section class="admin-panel add-product-panel">
        <div class="panel-heading"><div><p class="eyebrow">CATALOG</p><h2>Add a product</h2></div></div>
        <form class="admin-product-form" method="post" action="products.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="product-name">Product name</label>
            <input id="product-name" name="name" maxlength="160" required>
            <label for="product-description">Description</label>
            <textarea id="product-description" name="description" rows="4" maxlength="500" required></textarea>
            <div class="admin-form-row">
                <div><label for="product-price">Price (USD)</label><input id="product-price" type="number" name="price" min="0.01" max="99999999.99" step="0.01" required></div>
                <div><label for="product-stock">Starting stock</label><input id="product-stock" type="number" name="stock" min="0" max="4294967295" step="1" value="0" required></div>
            </div>
            <button class="button checkout-button" type="submit">Add product</button>
        </form>
    </section>
    <section class="admin-panel product-list-panel">
        <div class="panel-heading">
            <div><p class="eyebrow">ACTIVE CATALOG</p><h2>Products</h2></div>
            <span class="panel-caption"><?= count($products) ?> products</span>
        </div>
        <?php if (!$products): ?>
            <div class="admin-empty">Add your first product to start building the catalog.</div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Product</th><th>Price</th><th>Stock</th></tr></thead>
                    <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><strong><?= e($product['name']) ?></strong><small><?= e($product['description']) ?></small></td>
                            <td class="table-money"><?= money($product['price']) ?></td>
                            <td><span class="stock-count <?= (int) $product['stock'] <= 5 ? 'stock-low' : '' ?>"><?= (int) $product['stock'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p class="admin-note">Products are currently managed by admins. Seller accounts and seller-specific catalogs are not enabled.</p>
    </section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
