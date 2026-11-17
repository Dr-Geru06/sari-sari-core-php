<?php
require __DIR__ . '/app.php';

$shopId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($shopId === false || $shopId === null || $shopId < 1) {
    http_response_code(404);
    exit('Shop not found.');
}

$shopStatement = db()->prepare(
    'SELECT id, store_name, description
     FROM shops
     WHERE id = ? AND status = ?'
);
$shopStatement->execute([$shopId, 'approved']);
$shop = $shopStatement->fetch();
if (!$shop) {
    http_response_code(404);
    exit('Shop not found.');
}

$productStatement = db()->prepare(
    'SELECT p.id, p.name, p.description, p.price, p.stock, p.is_available, p.image_path, c.name AS category_name
     FROM products p
     INNER JOIN product_categories c ON c.id = p.category_id
     WHERE p.shop_id = ? AND p.is_active = 1 AND p.approval_status = ?
     ORDER BY p.name'
);
$productStatement->execute([$shopId, 'approved']);
$products = $productStatement->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($shop['store_name']) ?> | Sari-Sari</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require __DIR__ . '/site_header.php'; ?>
<main class="page-main public-shop-page">
    <section class="public-shop-heading">
        <p class="eyebrow">SARI-SARI SELLER SHOP</p>
        <h1 class="page-title"><?= e($shop['store_name']) ?></h1>
        <p><?= e($shop['description']) ?></p>
        <a class="text-button" href="index.php">&larr; Back to all products</a>
    </section>
    <section class="catalog public-shop-catalog">
        <div class="section-heading">
            <div><p class="eyebrow">APPROVED LISTINGS</p><h2>Shop products</h2></div>
            <p><?= count($products) ?> <?= count($products) === 1 ? 'item' : 'items' ?></p>
        </div>
        <?php if (!$products): ?>
            <div class="empty-state"><h2>No approved products yet</h2><p>This shop will show its products here after admin review.</p></div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <?php $canOrder = (int) $product['is_available'] === 1 && (int) $product['stock'] > 0; ?>
                    <article class="product-card">
                        <div class="product-art <?= $product['image_path'] ? 'has-image' : '' ?>">
                            <?php if ($product['image_path']): ?>
                                <img src="<?= e($product['image_path']) ?>" alt="<?= e($product['name']) ?>" loading="lazy" decoding="async">
                            <?php else: ?>
                                <span>SS</span><small><?= e(strtoupper($product['category_name'])) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h2 class="public-shop-product-name"><?= e($product['name']) ?></h2>
                            <span class="product-category-label"><?= e($product['category_name']) ?></span>
                            <p><?= e($product['description']) ?></p>
                            <div class="product-stock-count<?= $canOrder ? '' : ' is-sold-out' ?>">
                                <?php if ($canOrder): ?>
                                    <span>Remaining stock</span><strong><?= (int) $product['stock'] ?></strong>
                                <?php else: ?>
                                    <span>Availability</span><strong>Out of stock</strong>
                                <?php endif; ?>
                            </div>
                            <div class="product-bottom">
                                <strong><?= money($product['price']) ?></strong>
                                <?php if ($canOrder): ?>
                                    <form class="add-to-cart-form" method="post" action="cart.php">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                        <input type="hidden" name="return_to_cart" value="1">
                                        <button class="button small" type="submit">Add to cart</button>
                                    </form>
                                <?php else: ?>
                                    <span class="stock-note">Sold out</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/site_footer.php'; ?>
</body>
</html>
