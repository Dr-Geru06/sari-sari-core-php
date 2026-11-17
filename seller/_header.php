<?php
$sellerInitial = strtoupper(substr($sellerCustomer['full_name'], 0, 1));
?>
<header class="seller-topbar">
    <a class="brand" href="index.php"><span class="brand-mark">S.S</span><span class="brand-name">Seller Studio</span></a>
    <nav aria-label="Seller navigation">
        <a href="index.php">Dashboard</a>
        <a href="products.php">Products</a>
        <a href="../index.php">Visit shop</a>
        <a href="../account.php"><?= e($sellerCustomer['full_name']) ?></a>
    </nav>
</header>
