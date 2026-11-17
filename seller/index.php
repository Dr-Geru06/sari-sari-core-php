
<?php
require __DIR__ . '/_auth.php';

$productStatsStatement = db()->prepare(
    'SELECT COUNT(*) AS product_count,
            SUM(CASE WHEN approval_status = \'approved\' AND is_active = 1 THEN 1 ELSE 0 END) AS published_count,
            SUM(CASE WHEN approval_status = \'pending\' THEN 1 ELSE 0 END) AS pending_count,
            COALESCE(SUM(stock), 0) AS stock_count
     FROM products
     WHERE shop_id = ?'
);
$productStatsStatement->execute([$sellerShop['id']]);
$productStats = $productStatsStatement->fetch();

$salesStatement = db()->prepare(
    'SELECT COALESCE(SUM(oi.quantity), 0) AS units_sold,
            COALESCE(SUM(oi.quantity * oi.unit_price), 0) AS order_value
     FROM order_items oi
     INNER JOIN products p ON p.id = oi.product_id
     INNER JOIN orders o ON o.id = oi.order_id
     WHERE p.shop_id = ? AND o.status != \'cancelled\''
);
$salesStatement->execute([$sellerShop['id']]);
$sales = $salesStatement->fetch();

$productsStatement = db()->prepare(
    'SELECT id, name, price, stock, approval_status, review_note, is_active, created_at
     FROM products
     WHERE shop_id = ?
     ORDER BY created_at DESC
     LIMIT 12'
);
$productsStatement->execute([$sellerShop['id']]);
$products = $productsStatement->fetchAll();

$notice = flash('notice');
$error = flash('error');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seller Dashboard | <?= e($sellerShop['store_name']) ?></title>
    <link rel="stylesheet" href="../style.css?v=999">
</head>
<body>

<?php require __DIR__ . '/_header.php'; ?>

<main class="page-main seller-dashboard seller-dashboard-premium">

    <section class="seller-welcome">
        <div class="seller-welcome-content">
            <div class="seller-welcome-label">
                <span class="seller-welcome-dot"></span>
                SELLER WORKSPACE
            </div>

            <h1>Welcome to your shop, <span><?= e($sellerShop['store_name']) ?>.</span></h1>

            <p>
                Manage your products, monitor your shop performance,
                and grow your business with Sari-Sari.
            </p>

            <div class="seller-welcome-actions">
                <a class="seller-primary-action" href="products.php?add=1">
                    <span>+</span> Add a product
                </a>
                <a class="seller-secondary-action" href="products.php">
                    Manage catalog <span>&rarr;</span>
                </a>
            </div>
        </div>

        <div class="seller-welcome-decoration">
            <div class="seller-decoration-circle seller-circle-one"></div>
            <div class="seller-decoration-circle seller-circle-two"></div>
            <div class="seller-decoration-mark">S.S</div>
            <span>YOUR BUSINESS. YOUR GROWTH.</span>
        </div>
    </section>

    <?php if ($notice): ?>
        <div class="seller-alert seller-alert-success" role="status">
            <span>✓</span><?= e($notice) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="seller-alert seller-alert-error" role="alert">
            <span>!</span><?= e($error) ?>
        </div>
    <?php endif; ?>

    <section class="seller-overview">
        <div class="seller-section-heading">
            <div>
                <p class="seller-section-eyebrow">SHOP OVERVIEW</p>
                <h2>Your performance at a glance</h2>
            </div>
            <span class="seller-live-label">
                <span></span> SHOP STATISTICS
            </span>
        </div>

        <div class="seller-stat-grid seller-stat-grid-premium">

            <article class="seller-stat-card">
                <div class="seller-stat-top">
                    <span class="seller-stat-icon">▦</span>
                    <span class="seller-stat-caption">CATALOG</span>
                </div>
                <strong><?= number_format((int) $productStats['published_count']) ?></strong>
                <span class="seller-stat-title">Published products</span>
                <small>Approved and visible to shoppers</small>
            </article>

            <article class="seller-stat-card">
                <div class="seller-stat-top">
                    <span class="seller-stat-icon pending-icon">◷</span>
                    <span class="seller-stat-caption">REVIEW</span>
                </div>
                <strong><?= number_format((int) $productStats['pending_count']) ?></strong>
                <span class="seller-stat-title">Pending approval</span>
                <small>Products awaiting administrator review</small>
            </article>

            <article class="seller-stat-card">
                <div class="seller-stat-top">
                    <span class="seller-stat-icon stock-icon">▤</span>
                    <span class="seller-stat-caption">INVENTORY</span>
                </div>
                <strong><?= number_format((int) $productStats['stock_count']) ?></strong>
                <span class="seller-stat-title">Units in stock</span>
                <small>Total available inventory</small>
            </article>

            <article class="seller-stat-card">
                <div class="seller-stat-top">
                    <span class="seller-stat-icon sales-icon">↗</span>
                    <span class="seller-stat-caption">SALES</span>
                </div>
                <strong><?= number_format((int) $sales['units_sold']) ?></strong>
                <span class="seller-stat-title">Units sold</span>
                <small>Units across non-cancelled orders</small>
            </article>

            <article class="seller-stat-card seller-stat-highlight">
                <div class="seller-stat-top">
                    <span class="seller-stat-icon value-icon">₱</span>
                    <span class="seller-stat-caption">ORDER VALUE</span>
                </div>
                <strong><?= money((string) $sales['order_value']) ?></strong>
                <span class="seller-stat-title">Recorded order value</span>
                <small>Based on non-cancelled order items</small>
            </article>

        </div>
    </section>

    <section class="seller-products-panel seller-panel-premium">

        <div class="seller-products-heading">
            <div>
                <p class="seller-section-eyebrow">YOUR CATALOG</p>
                <h2>Recent products</h2>
                <p>Keep track of your latest product listings and their approval status.</p>
            </div>

            <a class="seller-add-product" href="products.php?add=1">
                <span>+</span> Add product
            </a>
        </div>

        <?php if (!$products): ?>

            <div class="seller-empty-state">
                <div class="seller-empty-icon">▦</div>
                <h3>Your shop is ready for its first product!</h3>
                <p>Add a product to your catalog and submit it for administrator review.</p>
                <a class="seller-primary-action" href="products.php?add=1">
                    Add your first product <span>&rarr;</span>
                </a>
            </div>

        <?php else: ?>

            <div class="seller-table-wrap">
                <table class="seller-product-table">
                    <thead>
                        <tr>
                            <th>PRODUCT</th>
                            <th>PRICE</th>
                            <th>STOCK</th>
                            <th>REVIEW STATUS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <div class="seller-product-name">
                                    <span class="seller-product-symbol">▦</span>
                                    <div>
                                        <strong><?= e($product['name']) ?></strong>
                                        <?php if ($product['review_note']): ?>
                                            <small>Review note: <?= e($product['review_note']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <td class="seller-price">
                                <?= money($product['price']) ?>
                            </td>

                            <td>
                                <span class="seller-stock-value <?= (int) $product['stock'] <= 5 ? 'seller-low-stock' : '' ?>">
                                    <?= number_format((int) $product['stock']) ?>
                                </span>
                            </td>

                            <td>
                                <span class="seller-review-status status-<?= e($product['approval_status']) ?>">
                                    <span></span>
                                    <?= e(ucfirst($product['approval_status'])) ?>
                                </span>
                            </td>

                            <td>
                                <a class="seller-edit-action" href="products.php?edit=<?= (int) $product['id'] ?>">
                                    Edit <span>&rarr;</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

        <div class="seller-panel-footer">
            <p>
                New products and edits remain hidden from shoppers until approved by an administrator.
            </p>
            <a href="products.php">View all products &rarr;</a>
        </div>

    </section>

    <div class="seller-dashboard-footnote">
        <span>ⓘ</span>
        Order value is a demonstration statistic, not a representation of collected payments.
    </div>

</main>

<?php require __DIR__ . '/_footer.php'; ?>

</body>
</html>
