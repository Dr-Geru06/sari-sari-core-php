<?php
require __DIR__ . '/_auth.php';

$pageTitle = 'Overview';
$activePage = 'overview';

$totals = db()->query(
    "SELECT COUNT(*) AS order_count,
            COALESCE(SUM(total), 0) AS order_value,
            COALESCE(SUM(status = 'pending'), 0) AS pending_orders
     FROM orders"
)->fetch();
$monthTotals = db()->query(
    "SELECT COUNT(*) AS order_count, COALESCE(SUM(total), 0) AS order_value
     FROM orders
     WHERE created_at >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')"
)->fetch();
$customerCount = (int) db()->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$shopStats = db()->query(
    'SELECT COALESCE(SUM(status = \'approved\'), 0) AS approved_shop_count,
            COALESCE(SUM(status = \'pending\'), 0) AS pending_shop_count,
            COUNT(DISTINCT CASE WHEN status = \'approved\' THEN customer_id END) AS approved_seller_count
     FROM shops'
)->fetch();
$productStats = db()->query(
    'SELECT COUNT(*) AS total_product_count,
            COALESCE(SUM(is_active = 1 AND approval_status = \'approved\'), 0) AS published_product_count,
            COALESCE(SUM(approval_status = \'pending\'), 0) AS pending_review_count
     FROM products'
)->fetch();
$lowStockCount = (int) db()->query(
    'SELECT COUNT(*)
     FROM products p
     WHERE p.is_active = 1 AND p.approval_status = \'approved\' AND p.stock <= 5
       AND (p.shop_id IS NULL OR EXISTS (
           SELECT 1 FROM shops s WHERE s.id = p.shop_id AND s.status = \'approved\'
       ))'
)->fetchColumn();

$monthlyRows = db()->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
            COUNT(*) AS order_count,
            COALESCE(SUM(total), 0) AS order_value
     FROM orders
     WHERE created_at >= DATE_FORMAT(DATE_SUB(CURRENT_DATE, INTERVAL 5 MONTH), '%Y-%m-01')
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY month_key"
)->fetchAll();
$monthlyLookup = [];
foreach ($monthlyRows as $row) {
    $monthlyLookup[$row['month_key']] = $row;
}

$chartMonths = [];
$monthStart = new DateTimeImmutable('first day of this month');
for ($offset = 5; $offset >= 0; $offset--) {
    $month = $monthStart->modify("-{$offset} months");
    $key = $month->format('Y-m');
    $chartMonths[] = [
        'label' => $month->format('M'),
        'value' => (string) ($monthlyLookup[$key]['order_value'] ?? '0.00'),
        'orders' => (int) ($monthlyLookup[$key]['order_count'] ?? 0),
    ];
}
$maxChartCents = 0;
foreach ($chartMonths as $month) {
    $maxChartCents = max($maxChartCents, price_to_cents($month['value']));
}

$recentOrders = db()->query(
    'SELECT o.id, o.order_number, o.customer_email, o.total, o.status, o.created_at,
            COALESCE(c.full_name, o.customer_name) AS customer_name
     FROM orders o
     LEFT JOIN customers c ON c.id = o.customer_id
     ORDER BY o.created_at DESC
     LIMIT 8'
)->fetchAll();
$lowStockProducts = db()->query(
    'SELECT p.name, p.stock FROM products p
     WHERE p.is_active = 1 AND p.approval_status = \'approved\' AND p.stock <= 5
       AND (p.shop_id IS NULL OR EXISTS (
           SELECT 1 FROM shops s WHERE s.id = p.shop_id AND s.status = \'approved\'
       ))
     ORDER BY p.stock, p.name LIMIT 5'
)->fetchAll();
$approvedShops = db()->query(
    'SELECT s.id, s.store_name, s.description, s.approved_at, c.full_name, c.email
     FROM shops s
     INNER JOIN customers c ON c.id = s.customer_id
     WHERE s.status = \'approved\'
     ORDER BY s.approved_at DESC, s.store_name'
)->fetchAll();

require __DIR__ . '/_header.php';
?>
<section class="admin-metrics">
    <article class="admin-metric">
        <div class="metric-topline"><span>Total orders</span><span class="metric-dot green"></span></div>
        <strong><?= number_format((int) $totals['order_count']) ?></strong>
        <small><?= number_format((int) $totals['pending_orders']) ?> pending and awaiting fulfillment</small>
    </article>
    <article class="admin-metric">
        <div class="metric-topline"><span>Order value</span><span class="metric-dot orange"></span></div>
        <strong><?= money((string) $totals['order_value']) ?></strong>
        <small>All placed orders, not collected payments</small>
    </article>
    <article class="admin-metric">
        <div class="metric-topline"><span>This month</span><span class="metric-dot blue"></span></div>
        <strong><?= number_format((int) $monthTotals['order_count']) ?></strong>
        <small><?= money((string) $monthTotals['order_value']) ?> order value</small>
    </article>
    <article class="admin-metric">
        <div class="metric-topline"><span>Customers</span><span class="metric-dot purple"></span></div>
        <strong><?= number_format($customerCount) ?></strong>
        <small><?= number_format($lowStockCount) ?> products low in stock</small>
    </article>
    <article class="admin-metric">
        <div class="metric-topline"><span>Approved shops</span><span class="metric-dot green"></span></div>
        <strong><?= number_format((int) $shopStats['approved_shop_count']) ?></strong>
        <small><?= number_format((int) $shopStats['approved_seller_count']) ?> approved sellers</small>
    </article>
    <article class="admin-metric">
        <div class="metric-topline"><span>Seller applications</span><span class="metric-dot orange"></span></div>
        <strong><?= number_format((int) $shopStats['pending_shop_count']) ?></strong>
        <small>Shops awaiting approval</small>
    </article>
    <article class="admin-metric">
        <div class="metric-topline"><span>Products</span><span class="metric-dot blue"></span></div>
        <strong><?= number_format((int) $productStats['total_product_count']) ?></strong>
        <small><?= number_format((int) $productStats['published_product_count']) ?> published in the store</small>
    </article>
    <article class="admin-metric">
        <div class="metric-topline"><span>Product reviews</span><span class="metric-dot purple"></span></div>
        <strong><?= number_format((int) $productStats['pending_review_count']) ?></strong>
        <small>Seller products awaiting review</small>
    </article>
</section>

<section class="admin-panel approved-shops-panel">
    <div class="panel-heading">
        <div><p class="eyebrow">MARKETPLACE</p><h2>Approved shops &amp; sellers</h2></div>
        <a class="panel-link" href="approved_shops.php">View all approved shops →</a>
    </div>
    <?php if (!$approvedShops): ?>
        <div class="admin-empty">Approved seller shops will appear here.</div>
    <?php else: ?>
        <div class="approved-shop-list">
            <?php foreach ($approvedShops as $shop): ?>
                <article class="approved-shop-card">
                    <div>
                        <h3><?= e($shop['store_name']) ?></h3>
                        <p><?= e($shop['description']) ?></p>
                        <small><?= e($shop['full_name']) ?> · <?= e($shop['email']) ?></small>
                    </div>
                    <a class="button small secondary" href="../shop.php?id=<?= (int) $shop['id'] ?>">View shop</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="admin-panel chart-panel">
    <div class="panel-heading">
        <div><p class="eyebrow">PERFORMANCE</p><h2>Order value by month</h2></div>
        <span class="panel-caption">Last six months</span>
    </div>
    <p class="admin-note">Based on placed orders. Payments are not processed by this store.</p>
    <div class="sales-chart" role="img" aria-label="Order value by month for the last six months">
        <?php foreach ($chartMonths as $month): ?>
            <?php
            $monthCents = price_to_cents($month['value']);
            $barHeight = $maxChartCents > 0 ? max(4, (int) round($monthCents / $maxChartCents * 100)) : 4;
            ?>
            <div class="sales-column" title="<?= e($month['label'] . ': ' . money($month['value']) . ' across ' . $month['orders'] . ' orders') ?>">
                <span class="sales-value"><?= money($month['value']) ?></span>
                <div class="sales-track"><div class="sales-bar" style="height: <?= $barHeight ?>%"></div></div>
                <span class="sales-month"><?= e($month['label']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<div class="admin-dashboard-grid">
    <section class="admin-panel">
        <div class="panel-heading">
            <div><p class="eyebrow">LATEST ACTIVITY</p><h2>Recent orders</h2></div>
            <a class="panel-link" href="orders.php">All orders →</a>
        </div>
        <?php if (!$recentOrders): ?>
            <div class="admin-empty">Orders will appear here after customers check out.</div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><a class="order-number" href="orders.php"><?= e($order['order_number']) ?></a><small><?= e(date('M j', strtotime($order['created_at']))) ?></small></td>
                            <td><?= e($order['customer_name']) ?><small><?= e($order['customer_email']) ?></small></td>
                            <td><span class="status-pill"><?= e(order_status_label($order['status'])) ?></span></td>
                            <td class="table-money"><?= money($order['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <section class="admin-panel stock-panel">
        <div class="panel-heading">
            <div><p class="eyebrow">INVENTORY</p><h2>Low stock</h2></div>
            <a class="panel-link" href="products.php">Products →</a>
        </div>
        <?php if (!$lowStockProducts): ?>
            <div class="admin-empty">Everything is stocked up.</div>
        <?php else: ?>
            <ul class="low-stock-list">
                <?php foreach ($lowStockProducts as $product): ?>
                    <li><span><?= e($product['name']) ?></span><strong><?= (int) $product['stock'] ?> left</strong></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
