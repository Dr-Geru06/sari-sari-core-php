<?php
require __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('orders.php');
    }

    $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $status = isset($_POST['status']) && is_string($_POST['status']) ? $_POST['status'] : '';
    $allowedStatuses = ['new', 'processing', 'fulfilled'];

    if ($orderId === false || $orderId === null || !in_array($status, $allowedStatuses, true)) {
        flash('error', 'Choose a valid order and status.');
        redirect('orders.php');
    }

    $statement = db()->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $statement->execute([$status, $orderId]);
    if ($statement->rowCount() === 0) {
        $check = db()->prepare('SELECT id FROM orders WHERE id = ?');
        $check->execute([$orderId]);
        if (!$check->fetch()) {
            flash('error', 'That order could not be found.');
            redirect('orders.php');
        }
    }
    flash('notice', 'Order status updated.');
    redirect('orders.php');
}

$pageTitle = 'Orders';
$activePage = 'orders';
$notice = flash('notice');
$error = flash('error');
$orders = db()->query(
    'SELECT o.id, o.order_number, o.customer_name, o.customer_email, o.shipping_address,
            o.total, o.status, o.created_at, COALESCE(c.full_name, o.customer_name) AS account_name
     FROM orders o
     LEFT JOIN customers c ON c.id = o.customer_id
     ORDER BY o.created_at DESC
     LIMIT 100'
)->fetchAll();
$orderItems = [];
if ($orders) {
    $ids = array_map('intval', array_column($orders, 'id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $itemStatement = db()->prepare("SELECT order_id, product_name, unit_price, quantity FROM order_items WHERE order_id IN ({$placeholders}) ORDER BY id");
    $itemStatement->execute($ids);
    foreach ($itemStatement->fetchAll() as $item) {
        $orderItems[$item['order_id']][] = $item;
    }
}
$allowedStatuses = ['new', 'processing', 'fulfilled'];

require __DIR__ . '/_header.php';
?>
<?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
<section class="admin-panel">
    <div class="panel-heading">
        <div><p class="eyebrow">FULFILLMENT</p><h2>Order management</h2></div>
        <span class="panel-caption">Showing latest <?= count($orders) ?> orders</span>
    </div>
    <?php if (!$orders): ?>
        <div class="admin-empty">No orders have been placed yet.</div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table order-admin-table">
                <thead><tr><th>Order</th><th>Customer</th><th>Delivery address</th><th>Items</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong class="order-number"><?= e($order['order_number']) ?></strong><small><?= e(date('M j, Y · g:i a', strtotime($order['created_at']))) ?></small></td>
                        <td><?= e($order['account_name']) ?><small><?= e($order['customer_email']) ?></small></td>
                        <td class="address-cell"><?= nl2br(e($order['shipping_address'])) ?></td>
                        <td>
                            <?php foreach ($orderItems[$order['id']] ?? [] as $item): ?>
                                <span class="admin-item-line"><?= e($item['product_name']) ?> × <?= (int) $item['quantity'] ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td class="table-money"><?= money($order['total']) ?></td>
                        <td>
                            <form class="status-form" method="post" action="orders.php">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                <label class="sr-only" for="status-<?= (int) $order['id'] ?>">Status for <?= e($order['order_number']) ?></label>
                                <select id="status-<?= (int) $order['id'] ?>" name="status">
                                    <?php foreach ($allowedStatuses as $status): ?>
                                        <option value="<?= e($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="button small" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
