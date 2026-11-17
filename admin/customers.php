<?php
require __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('customers.php');
    }

    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    $customerId = filter_var($_POST['customer_id'] ?? null, FILTER_VALIDATE_INT);
    if ($customerId === false || $customerId === null || $customerId < 1) {
        flash('error', 'Choose a valid customer account.');
        redirect('customers.php');
    }

    if ($action === 'update') {
        $customerStatement = db()->prepare('SELECT id, is_admin FROM customers WHERE id = ?');
        $customerStatement->execute([$customerId]);
        $customer = $customerStatement->fetch();
        if (!$customer) {
            flash('error', 'That customer account could not be found.');
            redirect('customers.php');
        }
        if ((int) $customer['is_admin'] === 1) {
            flash('error', 'Administrator accounts cannot be edited from customer management.');
            redirect('customers.php');
        }

        $name = isset($_POST['full_name']) && is_string($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $email = isset($_POST['email']) && is_string($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
        if ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            flash('error', 'Enter a name and valid email address within the listed limits.');
            redirect('customers.php?edit=' . $customerId);
        }

        $newPassword = isset($_POST['new_password']) && is_string($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) && is_string($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $changePassword = $newPassword !== '' || $confirmPassword !== '';
        if ($changePassword && (strlen($newPassword) < 10 || strlen($newPassword) > 72)) {
            flash('error', 'Choose a customer password between 10 and 72 characters.');
            redirect('customers.php?edit=' . $customerId);
        }
        if ($changePassword && $newPassword !== $confirmPassword) {
            flash('error', 'The new customer password and confirmation do not match.');
            redirect('customers.php?edit=' . $customerId);
        }

        try {
            if ($changePassword) {
                $updateStatement = db()->prepare('UPDATE customers SET full_name = ?, email = ?, password_hash = ? WHERE id = ? AND is_admin = 0');
                $updateStatement->execute([$name, $email, password_hash($newPassword, PASSWORD_DEFAULT), $customerId]);
            } else {
                $updateStatement = db()->prepare('UPDATE customers SET full_name = ?, email = ? WHERE id = ? AND is_admin = 0');
                $updateStatement->execute([$name, $email, $customerId]);
            }
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }
            flash('error', 'Another account already uses that email address.');
            redirect('customers.php?edit=' . $customerId);
        }

        flash('notice', $changePassword ? 'Customer account and password updated.' : 'Customer account updated.');
        redirect('customers.php');
    }

    if ($action === 'delete') {
        $connection = db();
        $connection->beginTransaction();
        try {
            $customerStatement = $connection->prepare('SELECT id, is_admin, profile_image_path FROM customers WHERE id = ? FOR UPDATE');
            $customerStatement->execute([$customerId]);
            $customer = $customerStatement->fetch();
            if (!$customer) {
                $connection->rollBack();
                flash('error', 'That customer account could not be found.');
                redirect('customers.php');
            }
            if ((int) $customer['is_admin'] === 1) {
                $connection->rollBack();
                flash('error', 'Administrator accounts cannot be deleted from customer management.');
                redirect('customers.php');
            }

            $orderStatement = $connection->prepare('SELECT COUNT(*) FROM orders WHERE customer_id = ?');
            $orderStatement->execute([$customerId]);
            if ((int) $orderStatement->fetchColumn() > 0) {
                $connection->rollBack();
                flash('error', 'This account has order history and cannot be deleted.');
                redirect('customers.php');
            }

            $deleteStatement = $connection->prepare('DELETE FROM customers WHERE id = ? AND is_admin = 0');
            $deleteStatement->execute([$customerId]);
            $connection->commit();
            remove_profile_image($customer['profile_image_path']);
        } catch (PDOException $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            error_log('Customer deletion failed: ' . $exception->getMessage());
            flash('error', 'The customer account could not be deleted.');
            redirect('customers.php');
        }

        flash('notice', 'Customer account deleted.');
        redirect('customers.php');
    }

    flash('error', 'Choose a valid customer action.');
    redirect('customers.php');
}

$editCustomer = null;
if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($editId === false || $editId === null || $editId < 1) {
        flash('error', 'Choose a valid customer account to edit.');
        redirect('customers.php');
    }

    $editStatement = db()->prepare('SELECT id, full_name, email, is_admin FROM customers WHERE id = ?');
    $editStatement->execute([$editId]);
    $editCustomer = $editStatement->fetch();
    if (!$editCustomer || (int) $editCustomer['is_admin'] === 1) {
        flash('error', 'That customer account cannot be edited here.');
        redirect('customers.php');
    }
}

$pageTitle = 'Customers';
$activePage = 'customers';
$notice = flash('notice');
$error = flash('error');
$customers = db()->query(
    'SELECT c.id, c.full_name, c.email, c.is_admin, c.profile_image_path, c.created_at,
            COALESCE(order_totals.order_count, 0) AS order_count,
            COALESCE(order_totals.order_value, 0) AS order_value
     FROM customers c
     LEFT JOIN (
         SELECT customer_id, COUNT(*) AS order_count, SUM(total) AS order_value
         FROM orders
         WHERE customer_id IS NOT NULL
         GROUP BY customer_id
     ) order_totals ON order_totals.customer_id = c.id
     ORDER BY c.created_at DESC'
)->fetchAll();

require __DIR__ . '/_header.php';
?>
<?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
<section class="admin-panel">
    <div class="panel-heading">
        <div><p class="eyebrow">CUSTOMER DIRECTORY</p><h2>Accounts</h2></div>
        <span class="panel-caption"><?= count($customers) ?> accounts</span>
    </div>
    <?php if (!$customers): ?>
        <div class="admin-empty">Customer accounts will appear here after registration.</div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table customer-admin-table">
                <thead><tr><th>Customer</th><th>Joined</th><th>Orders</th><th>Order value</th><th>Access</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td>
                            <div class="customer-table-identity">
                                <span class="customer-table-avatar">
                                    <?php if ($customer['profile_image_path']): ?>
                                        <img src="../<?= e($customer['profile_image_path']) ?>" alt="">
                                    <?php else: ?>
                                        <?= e(strtoupper(substr($customer['full_name'], 0, 1))) ?>
                                    <?php endif; ?>
                                </span>
                                <span><strong><?= e($customer['full_name']) ?></strong><small><?= e($customer['email']) ?></small></span>
                            </div>
                        </td>
                        <td><?= e(date('M j, Y', strtotime($customer['created_at']))) ?></td>
                        <td><?= number_format((int) $customer['order_count']) ?></td>
                        <td class="table-money"><?= money((string) $customer['order_value']) ?></td>
                        <td><span class="access-pill <?= (int) $customer['is_admin'] === 1 ? 'admin-access' : '' ?>"><?= (int) $customer['is_admin'] === 1 ? 'Administrator' : 'Customer' ?></span></td>
                        <td>
                            <?php if ((int) $customer['is_admin'] === 1): ?>
                                <span class="admin-note">Protected</span>
                            <?php else: ?>
                                <div class="product-admin-actions">
                                    <button
                                        class="button small secondary"
                                        type="button"
                                        data-modal-open="customer-edit-modal"
                                        data-customer-id="<?= (int) $customer['id'] ?>"
                                        data-customer-name="<?= e($customer['full_name']) ?>"
                                        data-customer-email="<?= e($customer['email']) ?>">Edit</button>
                                    <?php if ((int) $customer['order_count'] > 0): ?>
                                        <button class="button small danger" type="button" disabled title="Accounts with order history cannot be deleted">Delete</button>
                                    <?php else: ?>
                                        <button
                                            class="button small danger"
                                            type="button"
                                            data-modal-open="customer-delete-modal"
                                            data-customer-id="<?= (int) $customer['id'] ?>"
                                            data-customer-name="<?= e($customer['full_name']) ?>">Delete</button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="admin-note">Customer accounts can be updated or deleted when they have no orders. Administrator accounts are protected; account creation remains on the store registration page.</p>
    <?php endif; ?>
</section>
<dialog class="admin-modal" id="customer-edit-modal" aria-labelledby="customer-edit-title" <?= $editCustomer ? 'data-auto-open' : '' ?>>
    <div class="admin-modal-heading">
        <div><p class="eyebrow">CUSTOMER DIRECTORY</p><h2 id="customer-edit-title">Edit customer</h2></div>
        <button class="modal-close" type="button" data-modal-close aria-label="Close dialog">&times;</button>
    </div>
    <form class="customer-edit-form" method="post" action="customers.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="customer_id" value="<?= $editCustomer ? (int) $editCustomer['id'] : '' ?>">
        <div>
            <label for="customer-name">Full name</label>
            <input id="customer-name" name="full_name" maxlength="120" value="<?= $editCustomer ? e($editCustomer['full_name']) : '' ?>" required>
        </div>
        <div>
            <label for="customer-email">Email address</label>
            <input id="customer-email" type="email" name="email" maxlength="190" value="<?= $editCustomer ? e($editCustomer['email']) : '' ?>" required>
        </div>
        <div class="customer-password-fields">
            <p class="admin-note">To reset this customer's password, enter a new one below. Leave both fields blank to keep it unchanged.</p>
            <div>
                <label for="customer-new-password">New password</label>
                <input id="customer-new-password" type="password" name="new_password" autocomplete="new-password" minlength="10" maxlength="72">
            </div>
            <div>
                <label for="customer-confirm-password">Confirm new password</label>
                <input id="customer-confirm-password" type="password" name="confirm_password" autocomplete="new-password" minlength="10" maxlength="72">
            </div>
            <small class="field-hint">Use at least 10 characters; passwords are limited to 72 bytes.</small>
        </div>
        <div class="customer-edit-actions">
            <button class="button small" type="submit">Save changes</button>
            <button class="button small secondary" type="button" data-modal-close>Cancel</button>
        </div>
    </form>
    <p class="admin-note">Passwords are never shown. Customers can also change their own password in profile settings.</p>
</dialog>
<dialog class="admin-modal" id="customer-delete-modal" aria-labelledby="customer-delete-title">
    <div class="admin-modal-heading">
        <div><p class="eyebrow">CUSTOMER DIRECTORY</p><h2 id="customer-delete-title">Delete customer?</h2></div>
        <button class="modal-close" type="button" data-modal-close aria-label="Close dialog">&times;</button>
    </div>
    <form method="post" action="customers.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="customer_id" value="">
        <p class="customer-delete-copy">Delete <strong data-customer-name></strong>? This permanently removes the account. Accounts with order history cannot be deleted.</p>
        <div class="customer-edit-actions">
            <button class="button small danger" type="submit">Delete account</button>
            <button class="button small secondary" type="button" data-modal-close>Cancel</button>
        </div>
    </form>
</dialog>
<?php require __DIR__ . '/_footer.php'; ?>
