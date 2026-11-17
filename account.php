<?php
require __DIR__ . '/app.php';

$customer = current_customer();
if (!$customer) {
    redirect('login.php');
}

$statement = db()->prepare('SELECT id, order_number, total, status, created_at FROM orders WHERE customer_id = ? ORDER BY created_at DESC');
$statement->execute([$customer['id']]);
$orders = $statement->fetchAll();
$orderItems = [];

if ($orders) {
    $orderIds = array_map('intval', array_column($orders, 'id'));
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemsStatement = db()->prepare("SELECT order_id, product_name, unit_price, quantity FROM order_items WHERE order_id IN ({$placeholders}) ORDER BY id");
    $itemsStatement->execute($orderIds);
    foreach ($itemsStatement->fetchAll() as $item) {
        $orderItems[$item['order_id']][] = $item;
    }
}
$notice = flash('notice');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My account | Corner Shop</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require __DIR__ . '/site_header.php'; ?>
<main class="page-main">
    <p class="eyebrow">YOUR CORNER</p>
    <h1 class="page-title">Hello, <?= e($customer['full_name']) ?>.</h1>
    <p class="account-email"><?= e($customer['email']) ?></p>
    <?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
    <section class="account-orders">
        <div class="section-heading"><div><p class="eyebrow">YOUR PURCHASES</p><h2>Order history</h2></div></div>
        <?php if (!$orders): ?>
            <div class="empty-state"><h2>No orders just yet</h2><p>Your placed orders will show up here.</p><a class="button" href="index.php">Explore the shop</a></div>
        <?php else: ?>
            <div class="order-history">
                <?php foreach ($orders as $order): ?>
                    <article class="order-card">
                        <div class="order-card-heading">
                            <div><span class="order-label">ORDER</span><strong><?= e($order['order_number']) ?></strong></div>
                            <span class="order-status"><?= e(ucfirst($order['status'])) ?></span>
                        </div>
                        <div class="order-card-meta"><span><?= e(date('M j, Y', strtotime($order['created_at']))) ?></span><strong><?= money($order['total']) ?></strong></div>
                        <ul>
                            <?php foreach ($orderItems[$order['id']] ?? [] as $item): ?>
                                <li><span><?= e($item['product_name']) ?> × <?= (int) $item['quantity'] ?></span><span><?= money(cents_to_price(price_to_cents($item['unit_price']) * (int) $item['quantity'])) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/site_footer.php'; ?>
</body>
</html>
