<?php
require __DIR__ . '/_auth.php';

$pageTitle = 'Overview';
$activePage = 'overview';

$totals = db()->query(
    "SELECT COUNT(*) AS order_count,
            COALESCE(SUM(total), 0) AS order_value,
            COALESCE(SUM(status = 'new'), 0) AS new_orders
     FROM orders"
)->fetch();
$monthTotals = db()->query(
    "SELECT COUNT(*) AS order_count, COALESCE(SUM(total), 0) AS order_value
     FROM orders
     WHERE created_at >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')"
)->fetch();
$customerCount = (int) db()->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$lowStockCount = (int) db()->query('SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock <= 5')->fetchColumn();

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
    'SELECT name, stock FROM products WHERE is_active = 1 AND stock <= 5 ORDER BY stock, name LIMIT 5'
)->fetchAll();

require __DIR__ . '/_header.php';
?>
<section class="admin-metrics">
    <article class="admin-metric">
        <div class="metric-topline"><span>Total orders</span><span class="metric-dot green"></span></div>
        <strong><?= number_format((int) $totals['order_count']) ?></strong>
        <small><?= number_format((int) $totals['new_orders']) ?> new and awaiting review</small>
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
                            <td><span class="status-pill"><?= e(ucfirst($order['status'])) ?></span></td>
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
