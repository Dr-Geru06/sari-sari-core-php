<?php
require_once dirname(__DIR__) . '/app.php';

$admin = current_customer();
if (!$admin) {
    $_SESSION['admin_after_login'] = true;
    flash('notice', 'Sign in with an administrator account to open the dashboard.');
    redirect('../login.php');
}
if ((int) $admin['is_admin'] !== 1) {
    http_response_code(403);
    exit('Administrator access is required.');
}
