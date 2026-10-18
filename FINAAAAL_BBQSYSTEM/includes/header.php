<?php
/** Shared page header — expects bootstrap.php to be loaded already. */
$currentUser = current_user();
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
/** Presentation-only: marks the nav link for the page being rendered (aria-current). */
$navCurrent = static function (string $path) use ($currentScript): string {
    return basename($path) === $currentScript ? ' aria-current="page"' : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? config('app.name', 'BulkBites BBQ')) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/css/style.css') ?>">
</head>
<body>
<header class="site-header">
  <a class="skip-link" href="#main-content">Skip to main content</a>
  <div class="container nav">
    <a class="brand" href="<?= e(url('index.php')) ?>">&#128293; BulkBites BBQ</a>
    <nav class="nav-links" aria-label="Main">
      <?php if ($currentUser): ?>
        <span class="badge badge-role"><?= e($currentUser['role']) ?></span>
        <?php if ($currentUser['role'] === 'CUSTOMER'): ?>
          <?php $notifUnread = unread_notification_count((int) $currentUser['id']); ?>
          <a class="cart-link" href="<?= e(url('customer/notifications.php')) ?>" aria-label="Notifications"<?= $navCurrent('customer/notifications.php') ?>>
            &#128276;
            <span class="notif-badge" id="notif-count"<?= $notifUnread > 0 ? '' : ' style="display:none"' ?>><?= $notifUnread ?></span>
          </a>
          <a href="<?= e(url('customer/dashboard.php')) ?>"<?= $navCurrent('customer/dashboard.php') ?>>Home</a>
          <a href="<?= e(url('customer/menu.php')) ?>"<?= $navCurrent('customer/menu.php') ?>>Menu</a>
          <a href="<?= e(url('customer/orders.php')) ?>"<?= $navCurrent('customer/orders.php') ?>>Orders</a>
          <a class="cart-link" href="<?= e(url('customer/cart.php')) ?>"<?= $navCurrent('customer/cart.php') ?>>
            &#128722; Cart
            <?php if (cart_count() > 0): ?><span class="cart-count"><?= cart_count() ?></span><?php endif; ?>
          </a>
        <?php else: ?>
          <?php $staffUnread = unread_notification_count((int) $currentUser['id']); ?>
          <a class="cart-link" href="<?= e(url('staff/notifications.php')) ?>" aria-label="Notifications"<?= $navCurrent('staff/notifications.php') ?>>
            &#128276;
            <span class="notif-badge" id="notif-count"<?= $staffUnread > 0 ? '' : ' style="display:none"' ?>><?= $staffUnread ?></span>
          </a>
          <?php if ($currentUser['role'] === 'STAFF'): ?>
            <a href="<?= e(url('staff/dashboard.php')) ?>"<?= $navCurrent('staff/dashboard.php') ?>>Dashboard</a>
            <a href="<?= e(url('staff/kitchen.php')) ?>"<?= $navCurrent('staff/kitchen.php') ?>>KDS</a>
            <a href="<?= e(url('staff/orders.php')) ?>"<?= $navCurrent('staff/orders.php') ?>>Orders</a>
            <a href="<?= e(url('staff/inventory.php')) ?>"<?= $navCurrent('staff/inventory.php') ?>>Inventory</a>
          <?php else: ?>
            <a href="<?= e(url('manager/dashboard.php')) ?>"<?= $navCurrent('manager/dashboard.php') ?>>Dashboard</a>
            <a href="<?= e(url('manager/products.php')) ?>"<?= $navCurrent('manager/products.php') ?>>Products</a>
            <a href="<?= e(url('manager/inventory.php')) ?>"<?= $navCurrent('manager/inventory.php') ?>>Inventory</a>
            <a href="<?= e(url('manager/customers.php')) ?>"<?= $navCurrent('manager/customers.php') ?>>Customers</a>
            <a href="<?= e(url('manager/reports.php')) ?>"<?= $navCurrent('manager/reports.php') ?>>Reports</a>
            <a href="<?= e(url('manager/users.php')) ?>"<?= $navCurrent('manager/users.php') ?>>Staff</a>
            <a href="<?= e(url('manager/email.php')) ?>"<?= $navCurrent('manager/email.php') ?>>Email</a>
          <?php endif; ?>
          <a class="nav-user" href="<?= e(user_home($currentUser['role'])) ?>"<?= $navCurrent(user_home($currentUser['role'])) ?>><?= e($currentUser['full_name']) ?></a>
        <?php endif; ?>
      <?php else: ?>
        <a href="<?= e(url('login.php')) ?>"<?= $navCurrent('login.php') ?>>Customer login</a>
        <a href="<?= e(url('auth/staff_login.php')) ?>"<?= $navCurrent('auth/staff_login.php') ?>>Staff/Manager login</a>
      <?php endif; ?>
    </nav>
    <?php if ($currentUser): ?>
      <form class="inline-form nav-logout" method="post" action="<?= e(url('auth/logout.php')) ?>">
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
