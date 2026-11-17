<?php
require __DIR__ . '/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid sign-out request.');
}
if (!csrf_is_valid()) {
    http_response_code(403);
    exit('Your session expired. Please sign in again.');
}

unset($_SESSION['customer_id'], $_SESSION['checkout_after_login'], $_SESSION['last_order']);
session_regenerate_id(true);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
flash('notice', 'You have been signed out.');
redirect('index.php');
