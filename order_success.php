
<?php
require __DIR__ . '/app.php';

$order = $_SESSION['last_order'] ?? null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Confirmation | Sari-Sari</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="order-confirmation-page">

<?php require __DIR__ . '/site_header.php'; ?>

<main class="page-main confirmation-main">

    <?php if (is_array($order)): ?>

        <section class="confirmation-wrapper">

            <div class="confirmation-heading">
                <div class="confirmation-icon">
                    <span>✓</span>
                </div>

                <p class="confirmation-eyebrow">ORDER SUCCESSFULLY RECEIVED</p>

                <h1>Thank you for shopping with us.</h1>

                <p class="confirmation-description">
                    Your order has been placed successfully. We're glad you chose
                    Sari-Sari, where every purchase supports a growing marketplace
                    of local businesses.
                </p>
            </div>

            <div class="confirmation-ticket">

                <div class="ticket-top">
                    <div>
                        <p class="ticket-label">ORDER CONFIRMATION</p>
                        <h2>Your order details</h2>
                    </div>

                    <span class="ticket-status">
                        <span></span> Received
                    </span>
                </div>

                <div class="ticket-order-number">
                    <span>YOUR ORDER NUMBER</span>
                    <strong><?= e((string) $order['number']) ?></strong>
                </div>

                <div class="ticket-divider">
                    <span></span>
                    <span></span>
                </div>

                <div class="ticket-details">
                    <div class="ticket-detail-row">
                        <div>
                            <span>Payment method</span>
                            <strong>
                                <?= e(payment_method_label((string) ($order['payment_method'] ?? 'cod'))) ?>
                            </strong>
                        </div>
                        <div class="ticket-detail-icon">₱</div>
                    </div>

                    <div class="ticket-detail-row">
                        <div>
                            <span>Order total</span>
                            <strong class="ticket-total">
                                <?= money((string) $order['total']) ?>
                            </strong>
                        </div>
                        <div class="ticket-detail-icon">✓</div>
                    </div>
                </div>

                <div class="payment-notice">
                    <span class="notice-icon">i</span>
                    <p>
                        Your order has been saved. No payment was collected
                        through this confirmation.
                    </p>
                </div>

                <div class="ticket-bottom">
                    <p>THANK YOU FOR SUPPORTING LOCAL BUSINESSES</p>
                    <span>SARI-SARI · EVERYTHING IN ONE PLACE</span>
                </div>

            </div>

            <div class="confirmation-actions">
                <a class="confirmation-primary-button" href="index.php">
                    Continue Shopping <span>→</span>
                </a>

                <p>
                    We appreciate your order. Come back and discover
                    something wonderful!
                </p>
            </div>

        </section>

    <?php else: ?>

        <section class="confirmation-empty">
            <div class="empty-icon">♡</div>
            <p class="confirmation-eyebrow">NOTHING TO DISPLAY</p>
            <h1>No recent order found.</h1>
            <p>
                Your order confirmation will appear here once you complete
                a purchase.
            </p>
            <a class="confirmation-primary-button" href="index.php">
                Browse the shop <span>→</span>
            </a>
        </section>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/site_footer.php'; ?>

</body>
</html>
