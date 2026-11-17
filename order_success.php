<?php
require __DIR__ . '/app.php';

$order = $_SESSION['last_order'] ?? null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order received | Corner Shop</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require __DIR__ . '/site_header.php'; ?>
<main class="page-main">
    <?php if (is_array($order)): ?>
        <section class="success-card">
            <span class="success-mark">✓</span>
            <p class="eyebrow">ORDER RECEIVED</p>
            <h1>Thank you for your order.</h1>
            <p>Your order has been saved. No payment was collected.</p>
            <div class="receipt-number"><span>Order number</span><strong><?= e((string) $order['number']) ?></strong></div>
            <div class="receipt-number"><span>Payment method</span><strong><?= e(payment_method_label((string) ($order['payment_method'] ?? 'cod'))) ?></strong></div>
            <div class="receipt-number"><span>Order total</span><strong><?= money((string) $order['total']) ?></strong></div>
            <a class="button" href="index.php">Back to the shop</a>
        </section>
    <?php else: ?>
        <div class="empty-state"><h1>No recent order</h1><p>When you place an order, its confirmation will appear here.</p><a class="button" href="index.php">Browse the shop</a></div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/site_footer.php'; ?>
</body>
</html>
