<?php
require __DIR__ . '/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid sign-out request.');
}

$validToken = csrf_is_valid();
$returnToAdmin = $validToken
    && isset($_POST['after_logout'])
    && is_string($_POST['after_logout'])
    && $_POST['after_logout'] === 'admin';

if (!$validToken) {
    http_response_code(403);
    exit('Your session expired. Reload the page and try signing out again.');
}

if ($returnToAdmin) {
    unset($_SESSION['admin_id']);
} else {
    unset($_SESSION['customer_id'], $_SESSION['checkout_after_login'], $_SESSION['checkout_payment_method'], $_SESSION['checkout_delivery_address'], $_SESSION['last_order'], $_SESSION['cart']);
}

session_regenerate_id(true);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
flash('notice', $returnToAdmin
    ? 'You have been signed out of the admin dashboard.'
    : 'You have been signed out of the storefront.');
redirect($returnToAdmin ? 'admin/login.php' : 'login.php');
