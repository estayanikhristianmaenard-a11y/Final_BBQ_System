<?php
/** Shared page header — expects bootstrap.php to be loaded already. */
$currentUser = current_user();
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isOperations = $currentUser && $currentUser['role'] !== 'CUSTOMER';
$customerExperience = $customerExperience ?? !$isOperations;
$scriptParts = explode('/', str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''));
$pageClass = 'page-' . preg_replace('/[^a-z0-9-]/', '', strtolower(($scriptParts[count($scriptParts) - 2] ?? 'public') . '-' . pathinfo($currentScript, PATHINFO_FILENAME)));
/** Presentation-only: marks the nav link for the page being rendered (aria-current). */
$navCurrent = static function (string $path) use ($currentScript): string {
    return (basename($path) === $currentScript || ($currentScript === 'index.php' && $path === 'customer/menu.php')) ? ' aria-current="page"' : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="<?= e(url('assets/images/brand-mark.svg')) ?>" type="image/svg+xml">
<meta name="theme-color" content="#242824">
<title><?= e($pageTitle ?? config('app.name', 'BulkBites BBQ')) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/css/style.css') ?>">
<?php if (!empty($customerExperience)): ?>
<link rel="stylesheet" href="<?= e(url('assets/css/storefront.css')) ?>?v=<?= (int) filemtime(dirname(__DIR__) . '/assets/css/storefront.css') ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(url('assets/css/ui.css')) ?>?v=<?= (int) filemtime(dirname(__DIR__) . '/assets/css/ui.css') ?>">
</head>
<body class="<?= e(trim(($customerExperience ? 'storefront ' : '') . ($isOperations ? 'operations ' : '') . (!empty($hasCartDock) ? 'has-cart-dock ' : '') . $pageClass)) ?>">
<header class="site-header">
  <a class="skip-link" href="#main-content">Skip to main content</a>
  <div class="container nav">
    <a class="brand" href="<?= e(url('index.php')) ?>"><img class="brand-mark" src="<?= e(url('assets/images/brand-mark.svg')) ?>" alt="" width="36" height="36"> BulkBites BBQ</a>
    <?php if ($isOperations): ?><button class="btn btn-outline nav-toggle" type="button" aria-controls="primary-nav" aria-expanded="false" hidden>Pages</button><?php endif; ?>
    <nav class="nav-links" id="primary-nav" aria-label="Main">
      <?php if ($currentUser): ?>
        <span class="badge badge-role"><?= e($currentUser['role']) ?></span>
        <?php if ($currentUser['role'] === 'CUSTOMER'): ?>
          <a href="<?= e(url('customer/menu.php')) ?>"<?= $navCurrent('customer/menu.php') ?>>Menu</a>
          <a href="<?= e(url('customer/cart.php')) ?>"<?= $navCurrent('customer/cart.php') ?>><svg class="nav-icon" aria-hidden="true"><use href="<?= e(url('assets/images/nav-icons.svg#cart')) ?>"></use></svg> <span data-cart-nav-count>Cart (<?= cart_count() ?>)</span></a>
          <a href="<?= e(url('customer/orders.php')) ?>"<?= $navCurrent('customer/orders.php') ?>>My orders</a>
        <?php elseif ($currentUser['role'] === 'STAFF'): ?>
          <a href="<?= e(url('staff/kitchen.php')) ?>"<?= $navCurrent('staff/kitchen.php') ?>>Kitchen</a>
          <a href="<?= e(url('staff/orders.php')) ?>"<?= $navCurrent('staff/orders.php') ?>>Orders</a>
        <?php else: ?>
          <a href="<?= e(url('manager/dashboard.php')) ?>"<?= $navCurrent('manager/dashboard.php') ?>>Dashboard</a>
          <a href="<?= e(url('staff/orders.php')) ?>"<?= $navCurrent('staff/orders.php') ?>>Orders</a>
          <a href="<?= e(url('manager/products.php')) ?>"<?= $navCurrent('manager/products.php') ?>>Products &amp; stock</a>
          <a href="<?= e(url('manager/reports.php')) ?>"<?= $navCurrent('manager/reports.php') ?>>Reports</a>
          <a href="<?= e(url('manager/store.php')) ?>"<?= in_array($currentScript, ['store.php','email.php','demo.php','users.php','customers.php','categories.php'], true) ? ' aria-current="page"' : '' ?>>Settings</a>
        <?php endif; ?>
      <?php else: ?>
        <a href="<?= e(url('customer/menu.php')) ?>"<?= $navCurrent('customer/menu.php') ?>>Menu</a>
        <a href="<?= e(url('customer/cart.php')) ?>"<?= $navCurrent('customer/cart.php') ?>><svg class="nav-icon" aria-hidden="true" focusable="false"><use href="<?= e(url('assets/images/nav-icons.svg#cart')) ?>"></use></svg> <span data-cart-nav-count>Cart (<?= cart_count() ?>)</span></a>
        <a href="<?= e(url('login.php')) ?>"<?= $navCurrent('login.php') ?>>Sign in</a>
      <?php endif; ?>
    </nav>
    <?php if ($currentUser): require __DIR__ . '/notification-menu.php'; endif; ?>
    <?php if ($currentUser): ?>
      <form class="inline-form nav-logout" method="post" action="<?= e(url('logout.php')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-primary btn-sm" type="submit">Log out</button>
      </form>
    <?php endif; ?>
  </div>
</header>
<main class="container" id="main-content">
<?php foreach (get_flashes() as $flashMessage): ?>
  <div class="flash flash-<?= e($flashMessage['type']) ?>" role="<?= e($flashMessage['type']) === 'error' ? 'alert' : 'status' ?>"><?= e($flashMessage['message']) ?></div>
<?php endforeach; ?>
