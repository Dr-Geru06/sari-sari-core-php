
<?php
$customer = current_customer();
?>

<header class="site-header">
    <div class="header-container">

        <!-- Brand -->
        <a class="brand" href="index.php">
            <span class="brand-mark">S.S</span>
            <span class="brand-name">Sari-Sari</span>
        </a>

        <!-- Navigation -->
        <nav class="main-nav">

            <a href="index.php">Home</a>

            <?php if ($customer): ?>

                <a href="account.php">My Account</a>

                <?php if ((int) $customer['is_admin'] === 1): ?>
                    <a href="admin/index.php">Admin</a>
                <?php endif; ?>

                <form class="logout-form" method="post" action="logout.php">
                    <input type="hidden"
                           name="csrf_token"
                           value="<?= e(csrf_token()) ?>">

                    <button class="nav-button" type="submit">
                        Sign Out
                    </button>
                </form>

            <?php else: ?>

                <a href="login.php">Sign In</a>
                <a href="register.php" class="register-link">Register</a>

            <?php endif; ?>

            <!-- Cart -->
            <a class="cart-link" href="cart.php">
                <i class="bi bi-bag"></i>
                <span>Cart</span>
                <span class="cart-count"><?= cart_count() ?></span>
            </a>

        </nav>
    </div>
</header>
