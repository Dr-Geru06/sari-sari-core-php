
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= e($pageTitle) ?> | Sari-Sari Admin</title>

    <link rel="stylesheet" href="../style.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body class="admin-body">

<header class="admin-topbar">

    <div class="admin-topbar-left">

        <button type="button"
                class="sidebar-toggle"
                id="sidebarToggle"
                aria-label="Toggle sidebar"
                aria-expanded="true">
            <i class="bi bi-list"></i>
        </button>

        <a class="admin-brand" href="index.php">
            <span class="brand-mark">S.S</span>
            <span class="brand-name">Sari-Sari <small>ADMIN</small></span>
        </a>

    </div>

    <div class="admin-top-actions">
  <h5>Experimental Only not Included ===></h5>
        <button type="button"
                class="admin-store-link"
                data-modal-open="admin-store-preview"
                aria-haspopup="dialog">
            <i class="bi bi-shop"></i>
            <span>View Store</span>
            <i class="bi bi-eye external-icon"></i>
        </button>
  
        <div class="admin-profile-wrapper">

            <button type="button"
                    class="admin-profile-button"
                    id="profileToggle"
                    aria-expanded="false">

                <span class="admin-avatar">
                    <?php if ($admin['profile_image_path']): ?>
                        <img src="../<?= e($admin['profile_image_path']) ?>" alt="">
                    <?php else: ?>
                        <?= e(strtoupper(substr($admin['full_name'], 0, 1))) ?>
                    <?php endif; ?>
                </span>

                <span class="admin-profile-details">
                    <strong><?= e($admin['full_name']) ?></strong>
                    <small>Administrator</small>
                </span>

                <i class="bi bi-chevron-down profile-chevron"></i>

            </button>

            <div class="admin-profile-dropdown" id="profileDropdown">

                <div class="dropdown-user">
                    <strong><?= e($admin['full_name']) ?></strong>
                    <small>Store Administrator</small>
                </div>

                <div class="dropdown-divider"></div>

                <a href="profile.php">
                    <i class="bi bi-person-circle"></i>
                    Profile settings
                </a>

                <button type="button"
                        class="admin-store-preview-menu-link"
                        data-modal-open="admin-store-preview"
                        aria-haspopup="dialog">
                    <i class="bi bi-shop"></i>
                    Visit Store
                </button>

                <form method="post" action="../logout.php">
                    <input type="hidden"
                           name="csrf_token"
                           value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="after_logout" value="admin">

                    <button type="submit" class="dropdown-logout">
                        <i class="bi bi-box-arrow-right"></i>
                        Sign Out
                    </button>
                </form>

            </div>

        </div>

    </div>

</header>

<dialog class="admin-modal admin-store-preview-modal" id="admin-store-preview" aria-labelledby="admin-store-preview-title">
    <div class="admin-store-preview-heading">
        <div>
            <p class="eyebrow">STORE PREVIEW</p>
            <h2 id="admin-store-preview-title">View Store</h2>
        </div>
        <button class="modal-close" type="button" data-modal-close aria-label="Close store preview">&times;</button>
    </div>
    <iframe
        data-preview-src="../index.php?admin_preview=1"
        title="Read-only storefront preview"
        sandbox="allow-scripts"></iframe>
</dialog>

<div class="admin-layout" id="adminLayout">

    <!-- SIDEBAR -->
    <aside class="admin-sidebar" id="adminSidebar">

        <div class="sidebar-section">
            <p class="eyebrow">WORKSPACE</p>

            <nav class="admin-nav" aria-label="Admin sections">

                <a class="<?= $activePage === 'overview' ? 'is-active' : '' ?>"
                   href="index.php">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Overview</span>
                </a>

                <a class="<?= $activePage === 'orders' ? 'is-active' : '' ?>"
                   href="orders.php">
                    <i class="bi bi-bag-check"></i>
                    <span>Orders</span>
                </a>

                <a class="<?= $activePage === 'customers' ? 'is-active' : '' ?>"
                   href="customers.php">
                    <i class="bi bi-people"></i>
                    <span>Customers</span>
                </a>

                <a class="<?= $activePage === 'products' ? 'is-active' : '' ?>"
                   href="products.php">
                    <i class="bi bi-box-seam"></i>
                    <span>Products</span>
                </a>

                <a class="<?= $activePage === 'shops' ? 'is-active' : '' ?>"
                   href="shops.php">
                    <i class="bi bi-person-lines-fill"></i>
                    <span>Seller applications</span>
                </a>

                <a class="<?= $activePage === 'approved_shops' ? 'is-active' : '' ?>"
                   href="approved_shops.php">
                    <i class="bi bi-shop-window"></i>
                    <span>Approved Shops</span>
                </a>

                <a class="<?= $activePage === 'product_reviews' ? 'is-active' : '' ?>"
                   href="product_reviews.php">
                    <i class="bi bi-clipboard-check"></i>
                    <span>Product review</span>
                </a>

                <a class="<?= $activePage === 'categories' ? 'is-active' : '' ?>"
                   href="categories.php">
                    <i class="bi bi-tags"></i>
                    <span>Classifications</span>
                </a>

                <a class="<?= $activePage === 'profile' ? 'is-active' : '' ?>"
                   href="profile.php">
                    <i class="bi bi-person-circle"></i>
                    <span>Profile</span>
                </a>

            </nav>
        </div>

        <!-- STORE CARD -->
        <div class="sidebar-store-card">

            <div class="store-card-icon">
                <i class="bi bi-shop-window"></i>
            </div>

            <div class="store-card-info">
                <strong>Sari-Sari Store</strong>
                <small>Store management</small>
            </div>

            <span class="store-status-dot" title="Store dashboard"></span>

        </div>

        <div class="sidebar-footer">
            <i class="bi bi-shield-check"></i>
            Secure Admin Workspace
        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="admin-main">

        <div class="admin-page-heading">

            <div>
                <div class="admin-breadcrumb">
                    <span>Admin</span>
                    <i class="bi bi-chevron-right"></i>
                    <span><?= e($pageTitle) ?></span>
                </div>

                <h1><?= e($pageTitle) ?></h1>
            </div>

            <div class="admin-date">
                <i class="bi bi-calendar3"></i>
                <?= e(date('D, M j, Y')) ?>
            </div>

        </div>
