<?php
require __DIR__ . '/_auth.php';

$pageTitle = 'Approved shops';
$activePage = 'approved_shops';
$notice = flash('notice');
$error = flash('error');
$shops = db()->query(
    'SELECT s.id, s.store_name, s.description, s.approved_at, c.full_name, c.email, c.contact_number
     FROM shops s
     INNER JOIN customers c ON c.id = s.customer_id
     WHERE s.status = \'approved\'
     ORDER BY s.approved_at DESC, s.store_name'
)->fetchAll();

require __DIR__ . '/_header.php';
?>
<?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
<section class="admin-panel">
    <div class="panel-heading">
        <div><p class="eyebrow">MARKETPLACE</p><h2>Approved seller shops</h2></div>
        <a class="panel-link" href="shops.php">Seller applications →</a>
    </div>
    <?php if (!$shops): ?>
        <div class="admin-empty">Approved shops will appear here after applications are accepted.</div>
    <?php else: ?>
        <div class="seller-shop-list">
            <?php foreach ($shops as $shop): ?>
                <article class="seller-shop-review">
                    <div class="seller-shop-review-heading">
                        <div>
                            <h3><?= e($shop['store_name']) ?></h3>
                            <span class="seller-review-status status-approved">Approved</span>
                        </div>
                        <small>Approved <?= $shop['approved_at'] ? e(date('M j, Y', strtotime($shop['approved_at']))) : 'date unavailable' ?></small>
                    </div>
                    <p><?= e($shop['description']) ?></p>
                    <div class="seller-applicant-details">
                        <strong><?= e($shop['full_name']) ?></strong>
                        <span><?= e($shop['email']) ?></span>
                        <span><?= e($shop['contact_number'] !== '' ? $shop['contact_number'] : 'No contact number') ?></span>
                    </div>
                    <a class="button small secondary" href="../shop.php?id=<?= (int) $shop['id'] ?>">View public shop</a>
                    <form class="seller-review-form" method="post" action="shops.php">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="shop_id" value="<?= (int) $shop['id'] ?>">
                        <input type="hidden" name="return_to" value="approved_shops.php">
                        <label class="sr-only" for="shop-review-note-<?= (int) $shop['id'] ?>">Reason for revoking <?= e($shop['store_name']) ?> approval</label>
                        <input id="shop-review-note-<?= (int) $shop['id'] ?>" name="review_note" maxlength="500" placeholder="Optional reason for revoking approval">
                        <button class="button small danger" type="submit" name="status" value="rejected">Revoke approval</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
