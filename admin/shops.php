<?php
require __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnTo = isset($_POST['return_to']) && $_POST['return_to'] === 'approved_shops.php'
        ? 'approved_shops.php'
        : 'shops.php';
    if (!csrf_is_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect($returnTo);
    }

    $shopId = filter_var($_POST['shop_id'] ?? null, FILTER_VALIDATE_INT);
    $status = isset($_POST['status']) && is_string($_POST['status']) ? $_POST['status'] : '';
    $reviewNote = isset($_POST['review_note']) && is_string($_POST['review_note']) ? trim($_POST['review_note']) : '';
    if ($shopId === false || $shopId === null || $shopId < 1 || !in_array($status, ['approved', 'rejected'], true) || strlen($reviewNote) > 500) {
        flash('error', 'Choose a valid shop decision and keep the note within 500 characters.');
        redirect($returnTo);
    }

    $shopStatement = db()->prepare('SELECT id FROM shops WHERE id = ?');
    $shopStatement->execute([$shopId]);
    if (!$shopStatement->fetch()) {
        flash('error', 'That seller application could not be found.');
        redirect($returnTo);
    }

    $connection = db();
    $connection->beginTransaction();
    try {
        $updateStatement = $connection->prepare(
            'UPDATE shops SET status = ?, review_note = ?, approved_at = ? WHERE id = ?'
        );
        $updateStatement->execute([
            $status,
            $reviewNote !== '' ? $reviewNote : null,
            $status === 'approved' ? date('Y-m-d H:i:s') : null,
            $shopId
        ]);
        if ($status === 'rejected') {
            $hideProducts = $connection->prepare(
                'UPDATE products
                 SET is_active = 0, approval_status = ?, review_note = ?
                 WHERE shop_id = ? AND approval_status = ?'
            );
            $hideProducts->execute([
                'pending',
                'The shop must be approved before these products can be reviewed again.',
                $shopId,
                'approved'
            ]);
        }
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }

    if ($status === 'approved') {
        flash('notice', 'Shop approved. The seller dashboard is now available.');
    } else {
        flash('notice', $returnTo === 'approved_shops.php' ? 'Shop approval revoked.' : 'Shop application rejected.');
    }
    redirect($returnTo);
}

$pageTitle = 'Seller applications';
$activePage = 'shops';
$notice = flash('notice');
$error = flash('error');
$shops = db()->query(
    'SELECT s.id, s.store_name, s.description, s.status, s.review_note, s.created_at, s.approved_at,
            c.full_name, c.email, c.contact_number
     FROM shops s
     INNER JOIN customers c ON c.id = s.customer_id
     WHERE s.status = \'pending\'
     ORDER BY s.created_at DESC'
)->fetchAll();

require __DIR__ . '/_header.php';
?>
<?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
<section class="admin-panel">
    <div class="panel-heading">
        <div><p class="eyebrow">MARKETPLACE</p><h2>Applications awaiting approval</h2></div>
        <span class="panel-caption"><?= count($shops) ?> pending</span>
    </div>
    <?php if (!$shops): ?>
        <div class="admin-empty">There are no seller applications waiting for approval.</div>
    <?php else: ?>
        <div class="seller-shop-list">
            <?php foreach ($shops as $shop): ?>
                <article class="seller-shop-review">
                    <div class="seller-shop-review-heading">
                        <div>
                            <h3><?= e($shop['store_name']) ?></h3>
                            <span class="seller-review-status status-<?= e($shop['status']) ?>"><?= e(ucfirst($shop['status'])) ?></span>
                        </div>
                        <small>Submitted <?= e(date('M j, Y', strtotime($shop['created_at']))) ?></small>
                    </div>
                    <p><?= e($shop['description']) ?></p>
                    <div class="seller-applicant-details">
                        <strong><?= e($shop['full_name']) ?></strong>
                        <span><?= e($shop['email']) ?></span>
                        <span><?= e($shop['contact_number'] !== '' ? $shop['contact_number'] : 'No contact number') ?></span>
                    </div>
                    <?php if ($shop['review_note']): ?>
                        <p class="admin-note">Previous decision note: <?= e($shop['review_note']) ?></p>
                    <?php endif; ?>
                    <form class="seller-review-form" method="post" action="shops.php">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="shop_id" value="<?= (int) $shop['id'] ?>">
                        <label class="sr-only" for="shop-review-note-<?= (int) $shop['id'] ?>">Decision note for <?= e($shop['store_name']) ?></label>
                        <input id="shop-review-note-<?= (int) $shop['id'] ?>" name="review_note" maxlength="500" placeholder="Optional note for the applicant">
                        <button class="button small" type="submit" name="status" value="approved">Approve shop</button>
                        <button class="button small danger" type="submit" name="status" value="rejected">Reject</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
