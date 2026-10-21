<nav class="settings-links" aria-label="Settings sections">
<?php foreach (['store.php'=>'Store', 'demo.php'=>'Ordering QR'] as $file=>$label): ?>
  <a class="btn btn-outline btn-sm" href="<?= e(url('manager/' . $file)) ?>"<?= $currentScript === $file ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
<?php endforeach; ?>
</nav>
<details class="advanced-settings-links"<?= in_array($currentScript, ['email.php','users.php','customers.php','categories.php'], true) ? ' open' : '' ?>>
  <summary>Advanced settings</summary>
  <nav class="settings-links" aria-label="Advanced settings sections">
    <?php foreach (['email.php'=>'Email setup', 'users.php'=>'Staff accounts', 'customers.php'=>'Customer directory', 'categories.php'=>'Categories'] as $file=>$label): ?>
      <a class="btn btn-outline btn-sm" href="<?= e(url('manager/' . $file)) ?>"<?= $currentScript === $file ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
</details>
