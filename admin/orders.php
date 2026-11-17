<?php
require __DIR__ . '/_auth.php';

$allowedStatuses = [
    'pending' => 'Pending',
    'ongoing' => 'Ongoing',
    'on_shipment' => 'On shipment',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('orders.php');
    }

    $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $status = isset($_POST['status']) && is_string($_POST['status']) ? $_POST['status'] : '';
    if ($orderId === false || $orderId === null || !array_key_exists($status, $allowedStatuses)) {
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
    'SELECT o.id, o.order_number, o.customer_name, o.customer_email, o.customer_contact_number, o.shipping_address,
            o.payment_method, o.total, o.status, o.created_at, COALESCE(c.full_name, o.customer_name) AS account_name
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
                <thead><tr><th>Order</th><th>Customer</th><th>Contact number</th><th>Delivery address</th><th>Items</th><th>Payment</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong class="order-number"><?= e($order['order_number']) ?></strong><small><?= e(date('M j, Y · g:i a', strtotime($order['created_at']))) ?></small></td>
                        <td>
                            <?= e($order['account_name']) ?>
                            <small><?= e($order['customer_email']) ?></small>
                        </td>
                        <td class="contact-number-cell"><?= e($order['customer_contact_number'] !== '' ? $order['customer_contact_number'] : 'Not provided') ?></td>
                        <td class="address-cell"><?= nl2br(e($order['shipping_address'])) ?></td>
                        <td>
                            <?php foreach ($orderItems[$order['id']] ?? [] as $item): ?>
                                <span class="admin-item-line"><?= e($item['product_name']) ?> × <?= (int) $item['quantity'] ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td><?= e(payment_method_label($order['payment_method'])) ?></td>
                        <td class="table-money"><?= money($order['total']) ?></td>
                        <td>
                            <div class="order-status-actions">
                                <span class="status-pill"><?= e(order_status_label($order['status'])) ?></span>
                                <button class="button small secondary" type="button"
                                        data-modal-open="order-status-modal"
                                        data-order-id="<?= (int) $order['id'] ?>"
                                        data-order-number="<?= e($order['order_number']) ?>"
                                        data-order-status="<?= e($order['status']) ?>">Change</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<dialog class="admin-modal order-status-modal" id="order-status-modal" aria-labelledby="order-status-title">
    <div class="admin-modal-heading">
        <div>
            <p class="eyebrow">FULFILLMENT</p>
            <h2 id="order-status-title">Update order status</h2>
        </div>
        <button class="modal-close" type="button" data-modal-close aria-label="Close dialog">&times;</button>
    </div>
    <form class="order-status-modal-form" method="post" action="orders.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="order_id" value="">
        <p>Order <strong data-order-number></strong></p>
        <label for="order-status-select">Status</label>
        <select id="order-status-select" name="status">
            <?php foreach ($allowedStatuses as $statusValue => $statusLabel): ?>
                <option value="<?= e($statusValue) ?>"><?= e($statusLabel) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="admin-form-actions">
            <button class="button" type="submit">Save status</button>
            <button class="button secondary" type="button" data-modal-close>Cancel</button>
        </div>
    </form>
</dialog>
<?php require __DIR__ . '/_footer.php'; ?>
