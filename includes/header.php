<?php
if (!isset($pageTitle)) {
    $pageTitle = 'SalesPro | Technology for Everyone';
}
if (!isset($pageDescription)) {
    $pageDescription = 'Shop laptops, desktops, printers, monitors and accessories at SalesPro.';
}
if (!isset($activeNav)) {
    $activeNav = '';
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/customer-auth.php';
$cartCount = getCartCount();
$headerCustomer = isset($_SESSION['customer_id'], $_SESSION['customer_auth_version']) ? currentCustomer(getDbConnection()) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDescription) ?>">
  <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <a class="sr skip" href="#main">Skip to content</a>
  <header class="site-header">
    <div class="nav-row">
      <a class="brand" href="index.php" aria-label="SalesPro home">
        <img class="site-logo" src="salespro-logo.png" alt="SalesPro logo">
      </a>
      <nav class="main-nav" id="nav" aria-label="Main navigation">
        <a href="index.php" class="<?= $activeNav === 'home' ? 'active' : '' ?>">Home</a>
        <a href="products.php" class="<?= $activeNav === 'products' ? 'active' : '' ?>">Products</a>
        <a href="about.php" class="<?= $activeNav === 'about' ? 'active' : '' ?>">About</a>
        <a href="support.php" class="<?= $activeNav === 'support' ? 'active' : '' ?>">Support</a>
        <a href="contact.php" class="<?= $activeNav === 'contact' ? 'active' : '' ?>">Contact</a>
      </nav>
      <div class="header-actions">
        <a class="icon-btn" href="<?= $headerCustomer ? 'account.php' : 'login.php' ?>" aria-label="<?= $headerCustomer ? 'Account' : 'Sign in' ?>" title="<?= $headerCustomer ? 'Account' : 'Sign in' ?>">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg>
        </a>
        <a class="icon-btn" href="products.php#search" aria-label="Search products" title="Search products">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
        </a>
        <a class="icon-btn" href="cart.php" aria-label="Shopping cart" title="Shopping Cart">
          <svg viewBox="0 0 24 24"><path d="M3 4h2l2.5 11h10L20 7H6.5"/><circle cx="9" cy="19" r="1.2"/><circle cx="17" cy="19" r="1.2"/></svg>
          <span class="badge" data-n="<?= $cartCount ?>"><?= $cartCount > 0 ? $cartCount : '' ?></span>
        </a>
        <button class="icon-btn menu-toggle" aria-label="Menu" aria-controls="nav" aria-expanded="false">
          <svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
      </div>
    </div>
  </header>
