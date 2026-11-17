<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | Shop Admin</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="admin-body">
<header class="admin-topbar">
    <a class="brand" href="index.php"><span class="brand-mark">S.S</span><span>Sari-Sari Admin</span></a>
    <div class="admin-top-actions">
        <span class="admin-identity"><?= e($admin['full_name']) ?></span>
        <a class="admin-store-link" href="../index.php">View store</a>
        <form method="post" action="../logout.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="admin-signout">Sign out</button>
        </form>
    </div>
</header>
<div class="admin-layout">
    <aside class="admin-sidebar">
        <p class="eyebrow">WORKSPACE</p>
        <nav class="admin-nav" aria-label="Admin sections">
            <a class="<?= $activePage === 'overview' ? 'is-active' : '' ?>" href="index.php"><span>01</span>Overview</a>
            <a class="<?= $activePage === 'orders' ? 'is-active' : '' ?>" href="orders.php"><span>02</span>Orders</a>
            <a class="<?= $activePage === 'customers' ? 'is-active' : '' ?>" href="customers.php"><span>03</span>Customers</a>
            <a class="<?= $activePage === 'products' ? 'is-active' : '' ?>" href="products.php"><span>04</span>Products</a>
        </nav>
        <div class="sidebar-note">
            <span class="sidebar-note-mark">CS</span>
            <strong>Sari-Sari Store</strong>
            <span>Store management</span>
        </div>
    </aside>
    <main class="admin-main">
        <div class="admin-page-heading">
            <div><p class="eyebrow">SARI-SARI STORE / ADMIN</p><h1><?= e($pageTitle) ?></h1></div>
            <span class="admin-date"><?= e(date('D, M j, Y')) ?></span>
        </div>
