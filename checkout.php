<?php
require __DIR__ . '/app.php';

$customer = current_customer();
if (!$customer) {
    $_SESSION['checkout_after_login'] = true;
    flash('notice', 'Sign in or create an account to continue to checkout.');
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('checkout.php');
    }

    $address = isset($_POST['address']) && is_string($_POST['address']) ? trim($_POST['address']) : '';

    if ($address === '' || strlen($address) > 500) {
        flash('error', 'Please provide a delivery address.');
        redirect('checkout.php');
    }

    $cart = cart();
    if (!$cart) {
        flash('error', 'Your cart is empty.');
        redirect('cart.php');
    }

    try {
        $connection = db();
        $connection->beginTransaction();
        ksort($cart);
        $selectProduct = $connection->prepare('SELECT id, name, price, stock FROM products WHERE id = ? AND is_active = 1 FOR UPDATE');
        $products = [];
        $totalCents = 0;

        foreach ($cart as $id => $quantity) {
            $selectProduct->execute([(int) $id]);
            $product = $selectProduct->fetch();
            if (!$product || $quantity < 1 || $quantity > (int) $product['stock']) {
                $connection->rollBack();
                flash('error', 'A cart item is unavailable or has insufficient stock. Please review your cart.');
                redirect('cart.php');
            }
            $products[] = ['product' => $product, 'quantity' => (int) $quantity];
            $totalCents += price_to_cents($product['price']) * (int) $quantity;
        }

        $orderNumber = 'CS-' . strtoupper(bin2hex(random_bytes(6)));
        $insertOrder = $connection->prepare('INSERT INTO orders (customer_id, order_number, customer_name, customer_email, shipping_address, total, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insertOrder->execute([$customer['id'], $orderNumber, $customer['full_name'], $customer['email'], $address, cents_to_price($totalCents), 'new']);
        $orderId = (int) $connection->lastInsertId();

        $insertItem = $connection->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity) VALUES (?, ?, ?, ?, ?)');
        $decrementStock = $connection->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
        foreach ($products as $line) {
            $product = $line['product'];
            $quantity = $line['quantity'];
            $insertItem->execute([$orderId, $product['id'], $product['name'], $product['price'], $quantity]);
            $decrementStock->execute([$quantity, $product['id']]);
        }

        $connection->commit();
    } catch (Throwable $exception) {
        if (isset($connection) && $connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }

    $_SESSION['cart'] = [];
    $_SESSION['last_order'] = ['number' => $orderNumber, 'total' => cents_to_price($totalCents)];
    redirect('order_success.php');
}

$items = cart_items();
if (!$items) {
    flash('error', 'Add some products to your cart before checking out.');
    redirect('cart.php');
}

$totalCents = 0;
foreach ($items as $item) {
    $totalCents += price_to_cents($item['price']) * $item['quantity'];
}
$error = flash('error');
$notice = flash('notice');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout | Corner Shop</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require __DIR__ . '/site_header.php'; ?>
<main class="page-main">
    <p class="eyebrow">ALMOST YOURS</p><h1 class="page-title">Checkout</h1>
    <?php if ($error): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>
    <?php if ($notice): ?><p class="alert success"><?= e($notice) ?></p><?php endif; ?>
    <div class="checkout-layout">
        <form class="checkout-form" method="post" action="checkout.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <h2>Delivery details</h2>
            <div class="customer-detail"><span>Delivering for</span><strong><?= e($customer['full_name']) ?></strong></div>
            <div class="customer-detail"><span>Confirmation email</span><strong><?= e($customer['email']) ?></strong></div>
            <label for="address">Delivery address</label>
            <textarea id="address" name="address" autocomplete="street-address" maxlength="500" rows="4" required></textarea>
            <p class="payment-note">No payment is collected in this demo. Your order will be recorded for follow-up.</p>
            <button class="button checkout-button" type="submit">Place order · <?= money(cents_to_price($totalCents)) ?></button>
        </form>
        <aside class="checkout-summary">
            <h2>Order summary</h2>
            <?php foreach ($items as $item): ?>
                <div class="summary-line"><span><?= e($item['name']) ?> × <?= (int) $item['quantity'] ?></span><strong><?= money(cents_to_price(price_to_cents($item['price']) * $item['quantity'])) ?></strong></div>
            <?php endforeach; ?>
            <div class="summary-total"><span>Total</span><strong><?= money(cents_to_price($totalCents)) ?></strong></div>
        </aside>
    </div>
</main>
<?php require __DIR__ . '/site_footer.php'; ?>
</body>
</html>
