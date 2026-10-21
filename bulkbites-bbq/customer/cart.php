<?php
/**
 * Customer cart — quantity controls, remove, reset order (with confirmation),
 * and a summary computed from MySQL. Guests verify their email at checkout.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_customer_or_guest();
$customerExperience = true;
$hasCartInteraction = true;

// Cart actions preserve the normal form fallback and return state for JS requests.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $productId = (int) ($_POST['product_id'] ?? 0);
    if (isset($_POST['reset'])) {
        cart_clear(); $result=['ok'=>true,'message'=>'Your cart is now empty.'];
    } elseif (isset($_POST['remove'])) {
        cart_remove($productId); $result=['ok'=>true,'message'=>'Item removed from your cart.'];
    } elseif (isset($_POST['step'])) {
        $step = (int) $_POST['step'];
        $result = cart_set_qty($productId, max(1, cart_has($productId) + $step));
    } else {
        $result = cart_set_qty($productId, (int) ($_POST['qty'] ?? 1));
    }
    if (is_ajax()) {
        $view=cart_view(); $user=current_user();
        ob_start(); require dirname(__DIR__) . '/includes/cart-content.php'; $html=ob_get_clean();
        header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
        echo json_encode(['ok'=>$result['ok'],'message'=>implode(' ', array_merge([$result['message']], $view['notices'])),'cart'=>cart_feedback_state($view),'html'=>$html]);
        exit;
    }
    flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect(url('customer/cart.php'));
}

// --- Render ------------------------------------------------------------------
$view = cart_view();
foreach ($view['notices'] as $notice) {
    flash('warning', $notice);
}

$user = current_user();
$hasCartDock = $view['items'] > 0;
$pageTitle = 'My Cart — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <h1>Your cart</h1>
  <p class="muted">Check your food and quantities, then continue to checkout.</p>
</section>

<div data-cart-content>
<?php require dirname(__DIR__) . '/includes/cart-content.php'; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
