<?php
require_once dirname(__DIR__) . '/app.php';

$admin = current_admin();
if (!$admin) {
    flash('notice', 'Sign in with an administrator account to open the dashboard.');
    redirect('login.php');
}
