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
        unset($_SESSION['cart_selected_product_ids']);
        flash('notice', 'Your cart has been cleared.');
    } elseif ($action === 'remove') {
        $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        if ($id !== false && $id !== null) {
            unset($items[$id]);
            $_SESSION['cart'] = $items;
            $_SESSION['cart_selected_product_ids'] = cart_selection_ids();
            flash('notice', 'Item removed from your cart.');
        }
    } elseif (in_array($action, ['update', 'remove_selected', 'checkout_selected'], true)) {
        $rawSelection = $_POST['selected_products'] ?? [];
        if (!is_array($rawSelection)) {
            flash('error', 'Choose valid cart items.');
            redirect('cart.php');
        }

        $selectedIds = [];
        foreach ($rawSelection as $rawId) {
            $id = filter_var($rawId, FILTER_VALIDATE_INT);
            if ($id === false || $id === null || $id < 1 || !array_key_exists($id, $items)) {
                flash('error', 'A selected item is no longer in your cart. Please review your cart.');
                redirect('cart.php');
            }
            $selectedIds[$id] = (int) $id;
        }
        $selectedIds = array_values($selectedIds);
        $_SESSION['cart_selected_product_ids'] = $selectedIds;

        if ($action === 'remove_selected') {
            if (!$selectedIds) {
                flash('error', 'Select at least one product to remove.');
                redirect('cart.php');
            }
            foreach ($selectedIds as $id) {
                unset($items[$id]);
            }
            $_SESSION['cart'] = $items;
            $_SESSION['cart_selected_product_ids'] = [];
            flash('notice', count($selectedIds) . ' selected ' . (count($selectedIds) === 1 ? 'item was' : 'items were') . ' removed from your cart.');
            redirect('cart.php');
        }

        $quantities = $_POST['quantity'] ?? [];
        if (!is_array($quantities)) {
            flash('error', 'Invalid cart quantity.');
            redirect('cart.php');
        }

        $statement = db()->prepare(
            'SELECT p.stock
             FROM products p
             WHERE p.id = ? AND p.is_active = 1 AND p.is_available = 1 AND p.approval_status = ?
               AND (p.shop_id IS NULL OR EXISTS (
                   SELECT 1 FROM shops s WHERE s.id = p.shop_id AND s.status = ?
               ))'
        );
        $quantityAdjustmentError = false;
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

            $statement->execute([$id, 'approved', 'approved']);
            $product = $statement->fetch();
            if (!$product) {
                unset($items[$id]);
            } elseif ((int) $product['stock'] < 1) {
                unset($items[$id]);
                flash('error', 'An out-of-stock item was removed from your cart.');
                $quantityAdjustmentError = true;
            } elseif ($quantity > (int) $product['stock']) {
                $items[$id] = (int) $product['stock'];
                flash('error', 'A quantity was adjusted to the available stock.');
                $quantityAdjustmentError = true;
            } else {
                $items[$id] = $quantity;
            }
        }
        $_SESSION['cart'] = $items;
        $_SESSION['cart_selected_product_ids'] = array_values(array_filter(
            $selectedIds,
            static fn (int $id): bool => array_key_exists($id, $items)
        ));
        if ($action === 'checkout_selected') {
            if ($quantityAdjustmentError) {
                redirect('cart.php');
            }
            if (!selected_cart()) {
                flash('error', 'Select at least one product to check out.');
                redirect('cart.php');
            }
            redirect('checkout.php');
        }
        if (!$quantityAdjustmentError) {
            flash('notice', 'Your cart has been updated.');
        }
    } elseif ($action === 'add') {
        $returnToCart = isset($_POST['return_to_cart'])
            && is_string($_POST['return_to_cart'])
            && $_POST['return_to_cart'] === '1';
        $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id === null || $id < 1) {
            flash('error', 'That product could not be added.');
            redirect('index.php');
        }

        $statement = db()->prepare(
            'SELECT p.id, p.stock
             FROM products p
             WHERE p.id = ? AND p.is_active = 1 AND p.is_available = 1 AND p.approval_status = ?
               AND (p.shop_id IS NULL OR EXISTS (
                   SELECT 1 FROM shops s WHERE s.id = p.shop_id AND s.status = ?
               ))'
        );
        $statement->execute([$id, 'approved', 'approved']);
        $product = $statement->fetch();
        $current = (int) ($items[$id] ?? 0);

        if (!$product || (int) $product['stock'] < 1) {
            flash('error', 'That product is currently unavailable.');
        } elseif ($current >= (int) $product['stock']) {
            flash('error', 'Your cart already has the available stock for that item.');
        } else {
            $items[$id] = $current + 1;
            $_SESSION['cart'] = $items;
            if (array_key_exists('cart_selected_product_ids', $_SESSION)) {
                $selectedIds = cart_selection_ids();
                if (!in_array($id, $selectedIds, true)) {
                    $selectedIds[] = $id;
                }
                $_SESSION['cart_selected_product_ids'] = $selectedIds;
            }
            flash('notice', 'Added to your cart.');
        }
        redirect($returnToCart ? 'cart.php' : 'index.php');
    }

    redirect('cart.php');
}

$items = cart_items();
$visibleIds = array_map('intval', array_column($items, 'id'));
$selectedIds = array_values(array_intersect(cart_selection_ids(), $visibleIds));
$error = flash('error');
$notice = flash('notice');
$totalCents = 0;
$selectedTotalCents = 0;
foreach ($items as $item) {
    $lineTotal = price_to_cents($item['price']) * $item['quantity'];
    $totalCents += $lineTotal;
    if (in_array((int) $item['id'], $selectedIds, true)) {
        $selectedTotalCents += $lineTotal;
    }
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
<body class="cart-page">
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
            <label class="cart-select-all">
                <input type="checkbox" id="cart-select-all" <?= count($selectedIds) === count($items) ? 'checked' : '' ?>>
                <span>Select all products</span>
            </label>
            <div class="cart-table">
                <?php foreach ($items as $item): ?>
                    <div class="cart-row" data-unit-cents="<?= price_to_cents($item['price']) ?>">
                        <?php if ($item['image_path']): ?>
                            <img class="mini-art cart-product-image" src="<?= e($item['image_path']) ?>" alt="" loading="lazy" decoding="async">
                        <?php else: ?>
                            <div class="mini-art" aria-hidden="true">CS</div>
                        <?php endif; ?>
                        <div class="cart-product">
                            <label class="cart-item-select">
                                <input type="checkbox" name="selected_products[]" value="<?= (int) $item['id'] ?>" <?= in_array((int) $item['id'], $selectedIds, true) ? 'checked' : '' ?>>
                                <strong><?= e($item['name']) ?></strong>
                            </label>
                            <span><?= money($item['price']) ?> each · <?= (int) $item['is_available'] === 1 && (int) $item['stock'] > 0 ? (int) $item['stock'] . ' in stock' : 'Out of stock' ?></span>
                        </div>
                        <label class="quantity-label">Qty <input class="cart-quantity" type="number" name="quantity[<?= (int) $item['id'] ?>]" value="<?= (int) $item['quantity'] ?>" min="0" max="<?= (int) $item['stock'] ?>"></label>
                        <strong class="line-total"><?= money(cents_to_price(price_to_cents($item['price']) * $item['quantity'])) ?></strong>
                        <button class="text-button remove-button" type="submit" form="remove-<?= (int) $item['id'] ?>">Remove</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="cart-actions">
                <a class="text-button" href="index.php">← Continue shopping</a>
                <div class="cart-action-buttons">
                    <button class="button" type="submit" name="action" value="update">Update cart</button>
                    <button class="button secondary" type="submit" name="action" value="remove_selected">Remove selected</button>
                </div>
            </div>
            <section class="order-summary">
                <div><span>Cart subtotal</span><strong id="cart-subtotal"><?= money(cents_to_price($totalCents)) ?></strong></div>
                <div><span>Selected subtotal</span><strong id="selected-subtotal"><?= money(cents_to_price($selectedTotalCents)) ?></strong></div>
                <p><span id="selected-count"><?= count($selectedIds) ?></span> selected. Only checked products will be sent to checkout.</p>
                <button class="button checkout-button" type="submit" name="action" value="checkout_selected">Checkout selected</button>
            </section>
        </form>
        <?php foreach ($items as $item): ?>
            <form id="remove-<?= (int) $item['id'] ?>" method="post" action="cart.php" class="hidden-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/site_footer.php'; ?>
</body>
</html>
