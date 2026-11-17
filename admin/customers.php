<?php
require __DIR__ . '/_auth.php';

$pageTitle = 'Customers';
$activePage = 'customers';
$customers = db()->query(
    'SELECT c.id, c.full_name, c.email, c.is_admin, c.created_at,
            COALESCE(order_totals.order_count, 0) AS order_count,
            COALESCE(order_totals.order_value, 0) AS order_value
     FROM customers c
     LEFT JOIN (
         SELECT customer_id, COUNT(*) AS order_count, SUM(total) AS order_value
         FROM orders
         WHERE customer_id IS NOT NULL
         GROUP BY customer_id
     ) order_totals ON order_totals.customer_id = c.id
     ORDER BY c.created_at DESC
     LIMIT 100'
)->fetchAll();

require __DIR__ . '/_header.php';
?>
<section class="admin-panel">
    <div class="panel-heading">
        <div><p class="eyebrow">CUSTOMER DIRECTORY</p><h2>Accounts</h2></div>
        <span class="panel-caption"><?= count($customers) ?> recent accounts</span>
    </div>
    <?php if (!$customers): ?>
        <div class="admin-empty">Customer accounts will appear here after registration.</div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Customer</th><th>Joined</th><th>Orders</th><th>Order value</th><th>Access</th></tr></thead>
                <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><strong><?= e($customer['full_name']) ?></strong><small><?= e($customer['email']) ?></small></td>
                        <td><?= e(date('M j, Y', strtotime($customer['created_at']))) ?></td>
                        <td><?= number_format((int) $customer['order_count']) ?></td>
                        <td class="table-money"><?= money((string) $customer['order_value']) ?></td>
                        <td><span class="access-pill <?= (int) $customer['is_admin'] === 1 ? 'admin-access' : '' ?>"><?= (int) $customer['is_admin'] === 1 ? 'Administrator' : 'Customer' ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="admin-note">Customer access is read-only here. Grant administrator access through phpMyAdmin.</p>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
