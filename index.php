<?php
require __DIR__ . '/app.php';

$adminStorePreview = isset($_GET['admin_preview']) && is_string($_GET['admin_preview']) && $_GET['admin_preview'] === '1';
$search = trim((string) ($_GET['q'] ?? ''));
if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

$categories = db()->query('SELECT id, name FROM product_categories ORDER BY name')->fetchAll();
$rawCategoryId = $_GET['category'] ?? null;
$categoryId = is_string($rawCategoryId) ? filter_var($rawCategoryId, FILTER_VALIDATE_INT) : false;
$selectedCategory = null;
if (is_int($categoryId) && $categoryId > 0) {
    foreach ($categories as $category) {
        if ((int) $category['id'] === $categoryId) {
            $selectedCategory = $category;
            break;
        }
    }
}

$conditions = ['p.is_active = 1', 'p.approval_status = \'approved\'', '(p.shop_id IS NULL OR s.id IS NOT NULL)'];
$parameters = [];
if ($search !== '') {
    $conditions[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $parameters[] = '%' . $search . '%';
    $parameters[] = '%' . $search . '%';
}
if ($selectedCategory !== null) {
    $conditions[] = 'p.category_id = ?';
    $parameters[] = (int) $selectedCategory['id'];
}
$statement = db()->prepare(
    'SELECT p.id, p.shop_id, p.name, p.description, p.seller_name, p.seller_details, p.price, p.stock, p.is_available, p.image_path, c.name AS category_name, s.store_name
     FROM products p
     INNER JOIN product_categories c ON c.id = p.category_id
     LEFT JOIN shops s ON s.id = p.shop_id AND s.status = \'approved\'
     WHERE ' . implode(' AND ', $conditions) . '
     ORDER BY p.name'
);
$statement->execute($parameters);
$products = $statement->fetchAll();
$notice = flash('notice');
$error = flash('error');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SariSari</title>
    <link rel="stylesheet" href="style.css">
    <script src="shop.js" defer></script>
    <link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="storefront-home">
<?php require __DIR__ . '/site_header.php'; ?>
<main>
    <section class="hero">
        <img class="hero-illustration" src="assets/gold-shopping-hero.svg" alt="A golden shopping bag with everyday essentials" fetchpriority="high">
        <p class="eyebrow">GOOD THINGS, MADE SIMPLE</p>
        <h1>Find your next<br><span>everyday favorite.</span></h1>
        <p>Thoughtful essentials for home, work, and everywhere in between.</p>
        <?php if (!$adminStorePreview): ?>
            <form class="search-form" method="get" action="index.php">
                <label class="sr-only" for="search">Search products</label>
                <input id="search" type="search" name="q" value="<?= e($search) ?>" placeholder="Search the shop">
                <?php if ($selectedCategory !== null): ?><input type="hidden" name="category" value="<?= (int) $selectedCategory['id'] ?>"><?php endif; ?>
                <button type="submit">Search</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="catalog">
        <div class="section-heading">
            <div><p class="eyebrow">THE COLLECTION</p><h2><?= $selectedCategory !== null ? e($selectedCategory['name']) : ($search !== '' ? 'Search results' : 'Shop all') ?></h2></div>
            <p><?= count($products) ?> <?= count($products) === 1 ? 'item' : 'items' ?></p>
        </div>
        <nav class="category-filters" aria-label="Shop by classification">
            <?php
            $allFilterQuery = $search !== '' ? ['q' => $search] : [];
            if ($adminStorePreview) {
                $allFilterQuery['admin_preview'] = '1';
            }
            $allFilterUrl = 'index.php' . ($allFilterQuery ? '?' . http_build_query($allFilterQuery) : '');
            ?>
            <a class="<?= $selectedCategory === null ? 'is-active' : '' ?>" href="<?= e($allFilterUrl) ?>" <?= $selectedCategory === null ? 'aria-current="page"' : '' ?>>All</a>
            <?php foreach ($categories as $category): ?>
                <?php
                $filterQuery = $search !== '' ? ['q' => $search, 'category' => (int) $category['id']] : ['category' => (int) $category['id']];
                if ($adminStorePreview) {
                    $filterQuery['admin_preview'] = '1';
                }
                $filterUrl = 'index.php?' . http_build_query($filterQuery);
                $isSelectedCategory = $selectedCategory !== null && (int) $selectedCategory['id'] === (int) $category['id'];
                ?>
                <a class="<?= $isSelectedCategory ? 'is-active' : '' ?>" href="<?= e($filterUrl) ?>" <?= $isSelectedCategory ? 'aria-current="page"' : '' ?>><?= e($category['name']) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <?php if (!$products): ?>
            <div class="empty-state"><h3>No products found</h3><p>Try a different search, or browse the full collection.</p><a class="button secondary" href="index.php<?= $adminStorePreview ? '?admin_preview=1' : '' ?>">View all products</a></div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <?php $canOrder = (int) $product['is_available'] === 1 && (int) $product['stock'] > 0; ?>
                    <article
                        class="product-card reveal-card"
                        data-product-id="<?= (int) $product['id'] ?>"
                        data-product-name="<?= e($product['name']) ?>"
                        data-product-description="<?= e($product['description']) ?>"
                        data-product-seller-name="<?= e($product['seller_name']) ?>"
                        data-product-seller-details="<?= e((string) $product['seller_details']) ?>"
                        data-product-price="<?= e(money($product['price'])) ?>"
                        data-product-stock="<?= (int) $product['stock'] ?>"
                        data-product-available="<?= (int) $product['is_available'] ?>"
                        data-product-store-url="<?= $product['shop_id'] ? e('shop.php?id=' . (int) $product['shop_id'] . ($adminStorePreview ? '&admin_preview=1' : '')) : '' ?>"
                        data-product-image="<?= e((string) $product['image_path']) ?>">
                        <div class="product-art <?= $product['image_path'] ? 'has-image' : '' ?>">
                            <button class="product-art-trigger" type="button" data-product-open aria-label="View details for <?= e($product['name']) ?>" aria-haspopup="dialog">
                                <?php if ($product['image_path']): ?>
                                    <img src="<?= e($product['image_path']) ?>" alt="" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <span>CS</span><small><?= e(strtoupper($product['category_name'])) ?></small>
                                <?php endif; ?>
                            </button>
                        </div>
                        <div class="product-info">
                            <h3><button class="product-title-trigger" type="button" data-product-open aria-haspopup="dialog"><?= e($product['name']) ?></button></h3>
                            <span class="product-category-label"><?= e($product['category_name']) ?></span>
                            <?php if ($product['shop_id']): ?><span class="product-seller-link">Sold by <a href="shop.php?id=<?= (int) $product['shop_id'] ?><?= $adminStorePreview ? '&amp;admin_preview=1' : '' ?>"><?= e($product['store_name']) ?></a></span><?php endif; ?>
                            <p><?= e($product['description']) ?></p>
                            <div class="product-stock-count<?= $canOrder ? '' : ' is-sold-out' ?>">
                                <?php if ($canOrder): ?>
                                    <span>Remaining stock</span>
                                    <strong><?= (int) $product['stock'] ?></strong>
                                <?php else: ?>
                                    <span>Availability</span>
                                    <strong>Out of stock</strong>
                                <?php endif; ?>
                            </div>
                            <div class="product-bottom">
                                <strong><?= money($product['price']) ?></strong>
                                <?php if ($canOrder && !$adminStorePreview): ?>
                                    <form class="add-to-cart-form" method="post" action="cart.php">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                        <button class="button small" type="submit">Add to cart</button>
                                    </form>
                                <?php elseif ($adminStorePreview): ?>
                                    <span class="stock-note">View only</span>
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
<dialog class="store-product-modal" id="product-details-modal" aria-labelledby="product-dialog-name" aria-describedby="product-dialog-description">
    <div class="store-product-modal-heading">
        <p class="eyebrow">PRODUCT DETAILS</p>
        <button class="modal-close" type="button" data-product-modal-close aria-label="Close product details">&times;</button>
    </div>
    <div class="store-product-modal-body">
        <div class="store-product-modal-image">
            <img alt="" data-product-dialog-image hidden>
            <span data-product-dialog-fallback>CS</span>
        </div>
        <div class="store-product-modal-info">
            <h2 id="product-dialog-name" data-product-dialog-name></h2>
            <p id="product-dialog-description" data-product-dialog-description></p>
            <section class="seller-details-card" aria-label="Seller details">
                <p class="eyebrow">SOLD BY</p>
                <strong data-product-dialog-seller></strong>
                <p data-product-dialog-seller-details></p>
                <a class="seller-store-link" data-product-dialog-store-link hidden>Visit this seller's shop</a>
            </section>
            <div class="product-dialog-price">
                <strong data-product-dialog-price></strong>
                <span data-product-dialog-stock></span>
            </div>
            <?php if (!$adminStorePreview): ?>
                <form class="product-dialog-cart-form" method="post" action="cart.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="">
                    <input type="hidden" name="return_to_cart" value="1">
                    <button class="button" type="submit" data-product-dialog-add>Add to cart</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</dialog>
<?php require __DIR__ . '/site_footer.php'; ?>
</body>
</html>
