<?php
require __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('product_reviews.php');
    }

    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
    $status = isset($_POST['status']) && is_string($_POST['status']) ? $_POST['status'] : '';
    $reviewNote = isset($_POST['review_note']) && is_string($_POST['review_note']) ? trim($_POST['review_note']) : '';
    if ($productId === false || $productId === null || $productId < 1 || !in_array($status, ['approved', 'rejected'], true) || strlen($reviewNote) > 500) {
        flash('error', 'Choose a valid product review decision and keep the note within 500 characters.');
        redirect('product_reviews.php');
    }

    $updateStatement = db()->prepare(
        'UPDATE products p
         INNER JOIN shops s ON s.id = p.shop_id AND s.status = ?
         SET p.approval_status = ?, p.is_active = ?, p.review_note = ?
         WHERE p.id = ? AND p.shop_id IS NOT NULL AND p.approval_status = ?'
    );
    $updateStatement->execute([
        'approved',
        $status,
        $status === 'approved' ? 1 : 0,
        $status === 'rejected' && $reviewNote !== '' ? $reviewNote : null,
        $productId,
        'pending'
    ]);
    if ($updateStatement->rowCount() !== 1) {
        flash('error', 'That product is no longer waiting for review, or its shop is not approved.');
        redirect('product_reviews.php');
    }

    flash('notice', $status === 'approved' ? 'Product approved and published in the storefront.' : 'Product rejected and kept out of the storefront.');
    redirect('product_reviews.php');
}

$pageTitle = 'Product review';
$activePage = 'product_reviews';
$notice = flash('notice');
$error = flash('error');
$products = db()->query(
    'SELECT p.id, p.name, p.description, p.price, p.stock, p.image_path, p.created_at,
            c.name AS category_name, s.store_name, s.id AS shop_id, s.status AS shop_status,
            u.full_name AS seller_name, u.email AS seller_email
     FROM products p
     INNER JOIN shops s ON s.id = p.shop_id
     INNER JOIN customers u ON u.id = s.customer_id
     INNER JOIN product_categories c ON c.id = p.category_id
     WHERE p.approval_status = \'pending\'
     ORDER BY p.created_at'
)->fetchAll();

require __DIR__ . '/_header.php';
?>
<?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
<section class="admin-panel">
    <div class="panel-heading">
        <div><p class="eyebrow">CATALOG SAFETY</p><h2>Products awaiting approval</h2></div>
        <span class="panel-caption"><?= count($products) ?> pending</span>
    </div>
    <?php if (!$products): ?>
        <div class="admin-empty">There are no seller products waiting for review.</div>
    <?php else: ?>
        <div class="seller-product-review-list">
            <?php foreach ($products as $product): ?>
                <article class="seller-product-review">
                    <?php if ($product['image_path']): ?>
                        <img src="../<?= e($product['image_path']) ?>" alt="">
                    <?php else: ?>
                        <span class="admin-no-image">No image</span>
                    <?php endif; ?>
                    <div class="seller-product-review-copy">
                        <div class="seller-shop-review-heading">
                            <div><h3><?= e($product['name']) ?></h3><span><?= e($product['store_name']) ?> · <?= e($product['seller_name']) ?></span></div>
                            <strong><?= money($product['price']) ?></strong>
                        </div>
                        <p><?= e($product['description']) ?></p>
                        <small><?= e($product['category_name']) ?> · <?= (int) $product['stock'] ?> in stock · <?= e($product['seller_email']) ?></small>
                        <?php if ($product['shop_status'] !== 'approved'): ?>
                            <p class="alert error">This shop is no longer approved. Approving the product will not publish it until the shop is approved again.</p>
                        <?php endif; ?>
                        <form class="seller-review-form" method="post" action="product_reviews.php">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                            <label class="sr-only" for="product-review-note-<?= (int) $product['id'] ?>">Review note for <?= e($product['name']) ?></label>
                            <input id="product-review-note-<?= (int) $product['id'] ?>" name="review_note" maxlength="500" placeholder="Optional note for the seller">
                            <button class="button small" type="submit" name="status" value="approved" <?= $product['shop_status'] === 'approved' ? '' : 'disabled' ?>>Approve and publish</button>
                            <button class="button small danger" type="submit" name="status" value="rejected">Reject</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
