<?php
require __DIR__ . '/app.php';

$search = trim((string) ($_GET['q'] ?? ''));
if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

if ($search !== '') {
    $statement = db()->prepare('SELECT id, name, description, price, stock FROM products WHERE is_active = 1 AND (name LIKE ? OR description LIKE ?) ORDER BY name');
    $statement->execute(['%' . $search . '%', '%' . $search . '%']);
} else {
    $statement = db()->query('SELECT id, name, description, price, stock FROM products WHERE is_active = 1 ORDER BY name');
}
$products = $statement->fetchAll();
$notice = flash('notice');
$error = flash('error');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>1D9Gs</title>
    <link rel="stylesheet" href="style.css">
    <script src="shop.js" defer></script>
    <link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
<?php require __DIR__ . '/site_header.php'; ?>
<main>
    <section class="hero">
        <p class="eyebrow">GOOD THINGS, MADE SIMPLE</p>
        <h1>Find your next<br><span>everyday favorite.</span></h1>
        <p>Thoughtful essentials for home, work, and everywhere in between.</p>
        <form class="search-form" method="get" action="index.php">
            <label class="sr-only" for="search">Search products</label>
            <input id="search" type="search" name="q" value="<?= e($search) ?>" placeholder="Search the shop">
            <button type="submit">Search</button>
        </form>
    </section>

    <section class="catalog">
        <div class="section-heading">
            <div><p class="eyebrow">THE COLLECTION</p><h2><?= $search !== '' ? 'Search results' : 'Shop all' ?></h2></div>
            <p><?= count($products) ?> <?= count($products) === 1 ? 'item' : 'items' ?></p>
        </div>
        <?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <?php if (!$products): ?>
            <div class="empty-state"><h3>No products found</h3><p>Try a different search, or browse the full collection.</p><a class="button secondary" href="index.php">View all products</a></div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <article class="product-card reveal-card">
                        <div class="product-art"><span>CS</span><small>EVERYDAY<br>ESSENTIALS</small></div>
                        <div class="product-info">
                            <h3><?= e($product['name']) ?></h3>
                            <p><?= e($product['description']) ?></p>
                            <div class="product-bottom">
                                <strong><?= money($product['price']) ?></strong>
                                <?php if ((int) $product['stock'] > 0): ?>
                                    <form class="add-to-cart-form" method="post" action="cart.php">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                        <button class="button small" type="submit">Add to cart</button>
                                    </form>
                                <?php else: ?>
                                    <span class="stock-note">Sold out</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/site_footer.php'; ?>
</body>
</html>
