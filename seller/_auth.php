<?php
require_once dirname(__DIR__) . '/app.php';
require_once dirname(__DIR__) . '/product_images.php';

$sellerCustomer = current_customer();
if (!$sellerCustomer) {
    flash('notice', 'Sign in with your customer account to open the seller dashboard.');
    redirect('../login.php');
}

$sellerShop = seller_shop_for_customer((int) $sellerCustomer['id']);
if (!$sellerShop || $sellerShop['status'] !== 'approved') {
    flash('notice', 'Your seller dashboard is available after your shop application is approved.');
    redirect('../profile.php');
}
