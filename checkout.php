
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

    $submittedPaymentMethod = isset($_POST['payment_method']) && is_string($_POST['payment_method'])
        ? $_POST['payment_method']
        : '';
    if (in_array($submittedPaymentMethod, ['cod', 'online_test'], true)) {
        $_SESSION['checkout_payment_method'] = $submittedPaymentMethod;
    }

    $submittedAddress = isset($_POST['address']) && is_string($_POST['address'])
        ? $_POST['address']
        : '';
    if (strlen($submittedAddress) <= 500) {
        $_SESSION['checkout_delivery_address'] = $submittedAddress;
    }

    $submittedContactNumber = isset($_POST['contact_number']) && is_string($_POST['contact_number'])
        ? trim($_POST['contact_number'])
        : '';
    if (strlen($submittedContactNumber) <= 30) {
        $_SESSION['checkout_contact_number'] = $submittedContactNumber;
    }

    if (array_key_exists('quantity_change', $_POST)) {
        $quantityChanges = $_POST['quantity_change'];
        if (!is_array($quantityChanges) || count($quantityChanges) !== 1) {
            flash('error', 'Choose one item quantity to update.');
            redirect('checkout.php');
        }

        $id = filter_var(array_key_first($quantityChanges), FILTER_VALIDATE_INT);
        $change = reset($quantityChanges);
        $cart = selected_cart();
        if (
            $id === false
            || $id === null
            || $id < 1
            || !is_string($change)
            || !in_array($change, ['increase', 'decrease'], true)
            || !array_key_exists($id, $cart)
        ) {
            flash('error', 'That item could not be updated. Please review your cart.');
            redirect('cart.php');
        }

        $quantity = filter_var($cart[$id], FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 1) {
            unset($cart[$id]);
            $_SESSION['cart'] = $cart;
            flash('error', 'That item could not be updated. Please review your cart.');
            redirect('cart.php');
        }

        $productStatement = db()->prepare(
            'SELECT p.name, p.stock
             FROM products p
             WHERE p.id = ? AND p.is_active = 1 AND p.is_available = 1 AND p.approval_status = ?
               AND (p.shop_id IS NULL OR EXISTS (
                   SELECT 1 FROM shops s WHERE s.id = p.shop_id AND s.status = ?
               ))'
        );
        $productStatement->execute([$id, 'approved', 'approved']);
        $product = $productStatement->fetch();
        if (!$product) {
            unset($cart[$id]);
            $_SESSION['cart'] = $cart;
            flash('error', 'An unavailable item was removed from your cart.');
            redirect('cart.php');
        }

        if ($change === 'increase') {
            if ($quantity >= (int) $product['stock']) {
                flash('error', 'You have reached the available stock for this item.');
                redirect('checkout.php');
            }
            $quantity++;
        } else {
            if ($quantity <= 1) {
                flash('error', 'An item quantity cannot be less than one.');
                redirect('checkout.php');
            }
            $quantity--;
        }

        $_SESSION['cart'][$id] = $quantity;
        flash('notice', 'Your item quantity was updated.');
        redirect('checkout.php');
    }

    $paymentMethod = isset($_POST['payment_method']) && is_string($_POST['payment_method'])
        ? $_POST['payment_method']
        : '';
    if (!in_array($paymentMethod, ['cod', 'online_test'], true)) {
        flash('error', 'Choose a valid payment method.');
        redirect('checkout.php');
    }
    $_SESSION['checkout_payment_method'] = $paymentMethod;

    $contactNumber = isset($_POST['contact_number']) && is_string($_POST['contact_number'])
        ? trim($_POST['contact_number'])
        : '';
    if (!valid_contact_number($contactNumber)) {
        flash('error', 'Enter a valid contact number using 7 to 15 digits.');
        redirect('checkout.php');
    }

    $address = isset($_POST['address']) && is_string($_POST['address'])
        ? trim($_POST['address'])
        : '';

    if ($address === '' || strlen($address) > 500) {
        flash('error', 'Please provide a delivery address.');
        redirect('checkout.php');
    }

    $cart = selected_cart();

    if (!$cart) {
        flash('error', cart() ? 'Select at least one product to check out.' : 'Your cart is empty.');
        redirect('cart.php');
    }

    try {
        $connection = db();
        $connection->beginTransaction();

        ksort($cart);

        $selectProduct = $connection->prepare(
            'SELECT id, name, price, stock
             FROM products
             WHERE id = ? AND is_active = 1 AND is_available = 1 AND approval_status = \'approved\'
               AND (shop_id IS NULL OR EXISTS (
                   SELECT 1 FROM shops s WHERE s.id = products.shop_id AND s.status = \'approved\'
               ))
             FOR UPDATE'
        );

        $products = [];
        $totalCents = 0;

        foreach ($cart as $id => $quantity) {
            $selectProduct->execute([(int) $id]);
            $product = $selectProduct->fetch();

            if (!$product || $quantity < 1 || $quantity > (int) $product['stock']) {
                $connection->rollBack();

                flash(
                    'error',
                    'A cart item is unavailable or has insufficient stock. Please review your cart.'
                );

                redirect('cart.php');
            }

            $products[] = [
                'product' => $product,
                'quantity' => (int) $quantity
            ];

            $totalCents += price_to_cents($product['price']) * (int) $quantity;
        }

        $orderNumber = 'CS-' . strtoupper(bin2hex(random_bytes(6)));

        $insertOrder = $connection->prepare(
            'INSERT INTO orders
            (customer_id, order_number, customer_name, customer_email, customer_contact_number, shipping_address, payment_method, total, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $insertOrder->execute([
            $customer['id'],
            $orderNumber,
            $customer['full_name'],
            $customer['email'],
            $contactNumber,
            $address,
            $paymentMethod,
            cents_to_price($totalCents),
            'pending'
        ]);

        $orderId = (int) $connection->lastInsertId();

        $insertItem = $connection->prepare(
            'INSERT INTO order_items
            (order_id, product_id, product_name, unit_price, quantity)
            VALUES (?, ?, ?, ?, ?)'
        );

        $decrementStock = $connection->prepare(
            'UPDATE products SET stock = stock - ? WHERE id = ?'
        );

        foreach ($products as $line) {
            $product = $line['product'];
            $quantity = $line['quantity'];

            $insertItem->execute([
                $orderId,
                $product['id'],
                $product['name'],
                $product['price'],
                $quantity
            ]);

            $decrementStock->execute([
                $quantity,
                $product['id']
            ]);
        }

        $connection->commit();

    } catch (Throwable $exception) {
        if (isset($connection) && $connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }

    $_SESSION['cart'] = array_diff_key(cart(), $cart);
    $_SESSION['cart_selected_product_ids'] = [];

    $_SESSION['last_order'] = [
        'number' => $orderNumber,
        'total' => cents_to_price($totalCents),
        'payment_method' => $paymentMethod
    ];
    unset($_SESSION['checkout_payment_method'], $_SESSION['checkout_delivery_address'], $_SESSION['checkout_contact_number']);

    redirect('order_success.php');
}

$items = cart_items(selected_cart());

if (!$items) {
    flash('error', cart() ? 'Select at least one product to check out.' : 'Add some products to your cart before checking out.');
    redirect('cart.php');
}

$totalCents = 0;
$itemCount = 0;

foreach ($items as $item) {
    $totalCents += price_to_cents($item['price']) * $item['quantity'];
    $itemCount += $item['quantity'];
}

$error = flash('error');
$notice = flash('notice');
$selectedPaymentMethod = $_SESSION['checkout_payment_method'] ?? 'cod';
$deliveryAddress = $_SESSION['checkout_delivery_address'] ?? '';
$deliveryContactNumber = $_SESSION['checkout_contact_number'] ?? $customer['contact_number'];
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Checkout | Sari-Sari</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<?php require __DIR__ . '/site_header.php'; ?>

<main class="page-main checkout-page">

    <div class="checkout-container">

        <!-- CHECKOUT HEADING -->
        <section class="checkout-heading">

            <div>
                <p class="eyebrow">ALMOST YOURS</p>

                <h1 class="page-title">Complete your order.</h1>

                <p class="checkout-subtitle">
                    Just a few more details before your items are on their way.
                </p>
            </div>

            <a href="cart.php" class="checkout-back-link">
                &larr; Back to cart
            </a>

        </section>


        <!-- CHECKOUT PROGRESS -->
        <div class="checkout-progress">

            <div class="checkout-step completed">
                <span class="step-number">&#10003;</span>
                <span>Shopping cart</span>
            </div>

            <div class="progress-line active"></div>

            <div class="checkout-step current">
                <span class="step-number">2</span>
                <span>Checkout</span>
            </div>

            <div class="progress-line"></div>

            <div class="checkout-step">
                <span class="step-number">3</span>
                <span>Confirmation</span>
            </div>

        </div>


        <!-- ALERT MESSAGES -->
        <?php if ($error): ?>
            <div class="checkout-alert error" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($notice): ?>
            <div class="checkout-alert success" role="status">
                <?= e($notice) ?>
            </div>
        <?php endif; ?>


        <!-- MAIN CHECKOUT CONTENT -->
        <form class="checkout-layout" method="post" action="checkout.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <!-- DELIVERY INFORMATION -->
            <div class="checkout-form">

                <div class="checkout-card">

                    <div class="checkout-card-heading">
                        <div>
                            <span class="checkout-section-label">STEP 01</span>
                            <h2>Delivery information</h2>
                            <p>Where should we send your order?</p>
                        </div>

                        <div class="checkout-heading-icon">
                            &#8962;
                        </div>
                    </div>


                    <!-- CUSTOMER INFORMATION -->
                    <div class="checkout-customer-box">

                        <div class="checkout-customer-icon">
                            &#128100;
                        </div>

                        <div class="checkout-customer-info">
                            <span>ORDERING AS</span>

                            <strong>
                                <?= e($customer['full_name']) ?>
                            </strong>

                            <small>
                                <?= e($customer['email']) ?>
                            </small>
                        </div>

                        <span class="verified-label">
                            Account
                        </span>

                    </div>


                    <!-- CONTACT NUMBER -->
                    <div class="checkout-field">
                        <label for="contact-number">
                            Contact number
                            <span class="required-mark">*</span>
                        </label>
                        <p class="field-description">
                            Required for delivery contact. Use 7 to 15 digits; a country code and common separators are accepted.
                        </p>
                        <input
                            id="contact-number"
                            type="number"
                            name="contact_number"
                            autocomplete="tel"
                            inputmode="tel"
                            maxlength="30"
                            value="<?= e((string) $deliveryContactNumber) ?>"
                            placeholder="e.g. 0912 345 6789"
                            required
                        >
                    </div>

                    <!-- DELIVERY ADDRESS -->
                    <div class="checkout-field">

                        <label for="address">
                            Delivery address
                            <span class="required-mark">*</span>
                        </label>

                        <p class="field-description">
                            Enter your complete address, including your barangay,
                            municipality, and any helpful landmarks.
                        </p>

                        <textarea
                            id="address"
                            name="address"
                            autocomplete="street-address"
                            maxlength="500"
                            rows="5"
                            placeholder="House number, street, barangay, municipality, province..."
                            required
                        ><?= e($deliveryAddress) ?></textarea>

                        <div class="field-footer">
                            <span>Make sure your address is correct.</span>
                            <span>Maximum 500 characters</span>
                        </div>

                    </div>

                </div>


                <!-- PAYMENT INFORMATION -->
                <div class="checkout-card payment-card">

                    <div class="checkout-card-heading">
                        <div>
                            <span class="checkout-section-label">STEP 02</span>
                            <h2>Payment information</h2>
                            <p>Review the payment details for this demo checkout.</p>
                        </div>

                        <div class="checkout-heading-icon">
                            &#8369;
                        </div>
                    </div>

                    <div class="payment-demo-notice">
                        <div class="payment-notice-icon">
                            &#9432;
                        </div>

                        <div>
                            <strong>Demo checkout</strong>

                            <p>
                                Testing only. No payment is processed or collected.
                            </p>
                        </div>
                    </div>

                    <div class="payment-method-options" role="radiogroup" aria-label="Payment method">
                        <label class="payment-method-option">
                            <input
                                type="radio"
                                name="payment_method"
                                value="cod"
                                required
                                <?= $selectedPaymentMethod === 'cod' ? 'checked' : '' ?>
                            >
                            <span class="payment-method-copy">
                                <strong>Cash on delivery (COD)</strong>
                                <small>Test selection only; no payment is collected.</small>
                            </span>
                        </label>
                        <label class="payment-method-option">
                            <input
                                type="radio"
                                name="payment_method"
                                value="online_test"
                                <?= $selectedPaymentMethod === 'online_test' ? 'checked' : '' ?>
                            >
                            <span class="payment-method-copy">
                                <strong>Online payment (test only)</strong>
                                <small>Simulated option only; no payment gateway or charge.</small>
                            </span>
                        </label>
                    </div>

                </div>


                <!-- PLACE ORDER -->
                <div class="checkout-submit-area">

                    <button class="button checkout-button" type="submit">
                        Place my order
                        <span>&rarr;</span>
                    </button>

                    <p class="checkout-terms">
                        By placing your order, you confirm that your
                        delivery information is correct.
                    </p>

                </div>

            </div>


            <!-- ORDER SUMMARY -->
            <aside class="checkout-summary">

                <div class="summary-header">
                    <div>
                        <span class="checkout-section-label">YOUR PURCHASE</span>
                        <h2>Order summary</h2>
                    </div>

                    <span class="summary-item-count">
                        <?= $itemCount ?> item<?= $itemCount !== 1 ? 's' : '' ?>
                    </span>
                </div>


                <!-- PRODUCT LIST -->
                <div class="summary-products">

                    <?php foreach ($items as $item): ?>

                        <?php
                        $lineTotal = price_to_cents($item['price']) * $item['quantity'];
                        $canOrder = (int) $item['is_available'] === 1 && (int) $item['stock'] > 0;
                        ?>

                        <div class="summary-product">

                            <div class="summary-product-details">

                                <strong>
                                    <?= e($item['name']) ?>
                                </strong>

                                <div class="checkout-quantity-control">
                                    <span>Quantity: <?= (int) $item['quantity'] ?></span>
                                    <button
                                        class="checkout-quantity-button"
                                        type="submit"
                                        name="quantity_change[<?= (int) $item['id'] ?>]"
                                        value="decrease"
                                        aria-label="Remove one <?= e($item['name']) ?>"
                                        title="Remove one"
                                        formnovalidate
                                        <?= !$canOrder || (int) $item['quantity'] <= 1 ? 'disabled' : '' ?>
                                    >&minus;</button>
                                    <button
                                        class="checkout-quantity-button"
                                        type="submit"
                                        name="quantity_change[<?= (int) $item['id'] ?>]"
                                        value="increase"
                                        aria-label="Add one more <?= e($item['name']) ?>"
                                        title="Add one more"
                                        formnovalidate
                                        <?= !$canOrder || (int) $item['quantity'] >= (int) $item['stock'] ? 'disabled' : '' ?>
                                    >+</button>
                                </div>
                                <?php if (!$canOrder): ?>
                                    <small class="checkout-quantity-limit">Out of stock. <a href="cart.php">Remove it from your cart</a> before checking out.</small>
                                <?php elseif ((int) $item['quantity'] >= (int) $item['stock']): ?>
                                    <small class="checkout-quantity-limit">Maximum available stock</small>
                                <?php endif; ?>

                            </div>

                            <strong class="summary-product-price">
                                <?= money(cents_to_price($lineTotal)) ?>
                            </strong>

                        </div>

                    <?php endforeach; ?>

                </div>


                <!-- PRICE BREAKDOWN -->
                <div class="summary-breakdown">

                    <div class="summary-line">
                        <span>Subtotal</span>

                        <strong>
                            <?= money(cents_to_price($totalCents)) ?>
                        </strong>
                    </div>

                    <div class="summary-line">
                        <span>Shipping</span>

                        <strong class="shipping-label">
                            No shipping charges for this demo
                        </strong>
                    </div>

                </div>


                <!-- TOTAL -->
                <div class="summary-total">

                    <div>
                        <span>Total order value</span>
                        <small>Based on current product prices</small>
                    </div>

                    <strong>
                        <?= money(cents_to_price($totalCents)) ?>
                    </strong>

                </div>


                <!-- SECURITY NOTE -->
                <div class="summary-security">

                    <span class="security-icon">
                        &#128274;
                    </span>

                    <div>
                        <strong>Secure checkout</strong>

                        <p>
                            Your order is processed using your
                            registered customer account.
                        </p>
                    </div>

                </div>

            </aside>

        </form>

    </div>

</main>

<?php require __DIR__ . '/site_footer.php'; ?>

</body>
</html>
