
<?php
$footerCustomer = $customer ?? current_customer();
$accountLink = $footerCustomer ? 'account.php' : 'login.php';
$accountLabel = $footerCustomer ? 'Your account' : 'Sign in';
?>
<footer class="sari-footer">
    <div class="footer-inner">
        <section class="footer-brand-block">
            <a class="footer-brand-link" href="index.php">
                <span class="footer-brand-mark">S.S</span>
                <span class="footer-logo">Sari-Sari</span>
            </a>
            <p class="footer-tagline">Everyday finds, thoughtfully chosen.<br>A little something for every day.</p>
            <span class="footer-promise">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z"/></svg>
                Made for your everyday
            </span>
        </section>

        <nav class="footer-column" aria-label="Shop links">
            <h2>Explore</h2>
            <a class="footer-nav-link" href="index.php">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/></svg>
                <span>Shop all</span>
            </a>
            <a class="footer-nav-link" href="cart.php">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8h14l1 13H4L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                <span>Shopping cart</span>
            </a>
            <a class="footer-nav-link" href="about.php">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 5.5a3 3 0 0 1 0 5.8M18 14a5 5 0 0 1 3 4.6"/></svg>
                <span>About us</span>
            </a>
            <a class="footer-nav-link" href="<?= e($accountLink) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                <span><?= e($accountLabel) ?></span>
            </a>
        </nav>

        <section class="footer-column footer-help">
            <h2>Here to help</h2>
            <p>Need to check an order or update your account? You can find everything in one place.</p>
            <a class="footer-help-link" href="<?= e($accountLink) ?>">
                <span><?= e($footerCustomer ? 'View your account' : 'Sign in to your account') ?></span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
            </a>
        </section>
    </div>

    <div class="footer-bottom">
        <span class="footer-copyright">&copy; <?= date('Y') ?> Sari-Sari. All rights reserved.</span>
        <a href="index.php" class="footer-back-link">Back to the shop <span aria-hidden="true">↑</span></a>
    </div>
</footer>
