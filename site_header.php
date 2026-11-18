
<?php
$customer = current_customer();
$adminStorePreview = isset($_GET['admin_preview']) && is_string($_GET['admin_preview']) && $_GET['admin_preview'] === '1';
$homeUrl = $adminStorePreview ? 'index.php?admin_preview=1' : 'index.php';
?>

<header class="site-header">
    <div class="header-container">

        <!-- Brand -->
        <a class="brand" href="<?= e($homeUrl) ?>">
            <span class="brand-mark">S.S</span>
            <span class="brand-name">Sari-Sari</span>
        </a>

        <!-- Navigation -->
        <nav class="main-nav">

            <a href="<?= e($homeUrl) ?>">Home</a>

            <?php if (!$adminStorePreview && !$customer): ?>

                <a href="login.php">Sign In</a>
                <a href="register.php" class="register-link">Register</a>

            <?php endif; ?>

            <?php if (!$adminStorePreview): ?>
                <a class="cart-link" href="cart.php">
                    <i class="bi bi-bag"></i>
                    <span>Cart</span>
                    <span class="cart-count"><?= cart_count() ?></span>
                </a>

                <?php if ($customer): ?>
                    <details class="customer-profile-menu">
                        <summary class="customer-profile-toggle">
                            <span class="customer-avatar">
                                <?php if ($customer['profile_image_path']): ?>
                                    <img src="<?= e($customer['profile_image_path']) ?>" alt="">
                                <?php else: ?>
                                    <?= e(strtoupper(substr($customer['full_name'], 0, 1))) ?>
                                <?php endif; ?>
                            </span>
                            <span class="customer-profile-name"><?= e($customer['full_name']) ?></span>
                            <span class="customer-profile-chevron" aria-hidden="true"></span>
                        </summary>
                        <div class="customer-profile-dropdown">
                            <div class="customer-dropdown-user">
                                <strong><?= e($customer['full_name']) ?></strong>
                                <small><?= e($customer['email']) ?></small>
                            </div>
                            <a href="account.php">My Account</a>
                            <a href="profile.php">Profile settings</a>
                            <form method="post" action="logout.php">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button type="submit">Sign Out</button>
                            </form>
                        </div>
                    </details>
                <?php endif; ?>
            <?php endif; ?>

        </nav>
    </div>
</header>
<?php if ($adminStorePreview): ?>
    <div class="admin-store-preview-banner" role="note">Admin preview: browsing only. Cart, checkout, and account actions are disabled.</div>
<?php endif; ?>
