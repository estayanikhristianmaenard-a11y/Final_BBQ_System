<?php
/** Landing page — routes logged-in users to their own dashboard. */
require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    $u = current_user();
    if ($u) {
        redirect(user_home($u['role']));
    }
}

$pageTitle = 'BulkBites BBQ — Affordable Filipino Ihaw-Ihaw';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <h1>&#128293; BulkBites BBQ</h1>
  <p>
    Affordable Filipino grilled food — BBQ, Isaw Manok, Bituka Baboy, Tenga Baboy,
    Balunan, and Hotdog. Order digitally, skip the line, and pick up fresh off the coals.
    Greensite Compound, Sta. Lucia, Pasig City — near SM City East.
  </p>
  <div class="hero-actions">
    <a class="btn btn-primary" href="<?= e(url('login.php')) ?>">Order now — customer login</a>
    <a class="btn btn-outline" href="<?= e(url('auth/staff_login.php')) ?>">Staff/Manager login</a>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
