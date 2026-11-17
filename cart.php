<?php
require __DIR__ . '/app.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('cart.php');
    }

    $action = (string) ($_POST['action'] ?? '');
    $items = cart();

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        flash('notice', 'Your cart has been cleared.');
    } elseif ($action === 'remove') {
        $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        if ($id !== false && $id !== null) {
            unset($items[$id]);
            $_SESSION['cart'] = $items;
            flash('notice', 'Item removed from your cart.');
        }
    } elseif ($action === 'update') {
        $quantities = $_POST['quantity'] ?? [];
        if (!is_array($quantities)) {
            flash('error', 'Invalid cart quantity.');
            redirect('cart.php');
        }

        $statement = db()->prepare('SELECT stock FROM products WHERE id = ? AND is_active = 1');
        foreach ($quantities as $rawId => $rawQuantity) {
            $id = filter_var($rawId, FILTER_VALIDATE_INT);
            $quantity = filter_var($rawQuantity, FILTER_VALIDATE_INT);
            if ($id === false || $quantity === false || !array_key_exists($id, $items)) {
                continue;
            }
            if ($quantity < 1) {
                unset($items[$id]);
                continue;
            }

            $statement->execute([$id]);
            $product = $statement->fetch();
            if (!$product) {
                unset($items[$id]);
            } elseif ((int) $product['stock'] < 1) {
                unset($items[$id]);
                flash('error', 'An out-of-stock item was removed from your cart.');
            } elseif ($quantity > (int) $product['stock']) {
                $items[$id] = (int) $product['stock'];
                flash('error', 'A quantity was adjusted to the available stock.');
            } else {
                $items[$id] = $quantity;
            }
        }
        $_SESSION['cart'] = $items;
        if (!flash('error')) {
            flash('notice', 'Your cart has been updated.');
        }
    } elseif ($action === 'add') {
        $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id === null || $id < 1) {
            flash('error', 'That product could not be added.');
            redirect('index.php');
        }

        $statement = db()->prepare('SELECT id, stock FROM products WHERE id = ? AND is_active = 1');
        $statement->execute([$id]);
        $product = $statement->fetch();
        $current = (int) ($items[$id] ?? 0);

        if (!$product || (int) $product['stock'] < 1) {
            flash('error', 'That product is currently unavailable.');
        } elseif ($current >= (int) $product['stock']) {
            flash('error', 'Your cart already has the available stock for that item.');
        } else {
            $items[$id] = $current + 1;
            $_SESSION['cart'] = $items;
            flash('notice', 'Added to your cart.');
        }
        redirect('index.php');
    }

    redirect('cart.php');
}

$items = cart_items();
$cart = cart();
$error = flash('error');
$notice = flash('notice');
$totalCents = 0;
foreach ($items as $item) {
    $totalCents += price_to_cents($item['price']) * $item['quantity'];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your cart | Corner Shop</title>
    <link rel="stylesheet" href="style.css">
    <script src="cart.js" defer></script>
</head>
<body>
<?php require __DIR__ . '/site_header.php'; ?>
<main class="page-main">
    <p class="eyebrow">YOUR BAG</p><h1 class="page-title">Shopping cart</h1>
    <?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if (!$items): ?>
        <div class="empty-state"><h2>Your cart is waiting</h2><p>Browse the collection and add something you love.</p><a class="button" href="index.php">Continue shopping</a></div>
    <?php else: ?>
        <form method="post" action="cart.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="update">
            <div class="cart-table">
                <?php foreach ($items as $item): ?>
                    <div class="cart-row" data-unit-cents="<?= price_to_cents($item['price']) ?>">
                        <div class="mini-art">CS</div>
                        <div class="cart-product"><strong><?= e($item['name']) ?></strong><span><?= money($item['price']) ?> each · <?= (int) $item['stock'] ?> in stock</span></div>
                        <label class="quantity-label">Qty <input class="cart-quantity" type="number" name="quantity[<?= (int) $item['id'] ?>]" value="<?= (int) $item['quantity'] ?>" min="0" max="<?= (int) $item['stock'] ?>"></label>
                        <strong class="line-total"><?= money(cents_to_price(price_to_cents($item['price']) * $item['quantity'])) ?></strong>
                        <button class="text-button remove-button" type="submit" form="remove-<?= (int) $item['id'] ?>">Remove</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="cart-actions">
                <a class="text-button" href="index.php">← Continue shopping</a>
                <button class="button secondary" type="submit">Update cart</button>
            </div>
        </form>
        <?php foreach ($items as $item): ?>
            <form id="remove-<?= (int) $item['id'] ?>" method="post" action="cart.php" class="hidden-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
            </form>
        <?php endforeach; ?>
        <section class="order-summary">
            <div><span>Subtotal</span><strong id="cart-subtotal"><?= money(cents_to_price($totalCents)) ?></strong></div>
            <p>Shipping and any applicable taxes will be confirmed separately.</p>
            <a class="button checkout-button" href="checkout.php">Continue to checkout</a>
        </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/site_footer.php'; ?>
</body>
</html>
