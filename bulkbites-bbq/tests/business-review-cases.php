<?php
/** Regression cases for operational dates, payment stages, and cancelled balances. */
if (PHP_SAPI !== 'cli' || !isset($pdo, $name) || !preg_match('/^bulkbites_baseline_[a-f0-9]{12}$/D', $name)) { exit(1); }
$pdo->beginTransaction();
try {
    $beforeCounts = staff_order_counts();
    $insert = $pdo->prepare('INSERT INTO orders (order_number, customer_id, status, payment_method, payment_status, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    $dates = $pdo->query('SELECT NOW() AS today, DATE_SUB(NOW(), INTERVAL 1 DAY) AS yesterday')->fetch();
    $make = static function (string $status, string $method, string $payment, string $created) use ($insert, $pdo, $customer): int {
        $insert->execute(['REVIEW-' . bin2hex(random_bytes(5)), $customer, $status, $method, $payment, $created]);
        return (int) $pdo->lastInsertId();
    };
    $history = $pdo->prepare('INSERT INTO order_status_history (order_id, old_status, new_status, created_at) VALUES (?, "READY", ?, ?)');
    $completed = $make('COMPLETED', 'CASH', 'PAID', $dates['yesterday']);
    $history->execute([$completed, 'COMPLETED', $dates['today']]);
    $history->execute([$completed, 'COMPLETED', $dates['today']]);
    $oldCompleted = $make('COMPLETED', 'CASH', 'PAID', $dates['today']);
    $history->execute([$oldCompleted, 'COMPLETED', $dates['yesterday']]);
    $cancelled = $make('CANCELLED', 'CASH', 'UNPAID', $dates['yesterday']);
    $history->execute([$cancelled, 'CANCELLED', $dates['today']]);
    $oldCancelled = $make('CANCELLED', 'CASH', 'UNPAID', $dates['today']);
    $history->execute([$oldCancelled, 'CANCELLED', $dates['yesterday']]);
    $unknownDate = $make('COMPLETED', 'CASH', 'LEGACY_UNVERIFIED', $dates['today']);
    $counts = staff_order_counts();
    baseline_check($counts['completed_today'] === $beforeCounts['completed_today'] + 1, 'Completion date determines today counts; duplicate history and creation dates cannot inflate them');
    baseline_check($counts['cancelled_today'] === $beforeCounts['cancelled_today'] + 1, 'Cancellation counts use the actual cancellation date across midnight');
    $managerCounts = manager_dashboard_stats();
    baseline_check($managerCounts['completed_today'] === $counts['completed_today'] && $managerCounts['cancelled_today'] === $counts['cancelled_today'], 'Staff and manager use the same event dates; legacy orders without terminal history have no invented completion date');
    $make('NEW', 'CASH', 'UNPAID', $dates['today']);
    $waiting = $make('NEW', 'DEMO', 'PENDING', $dates['today']);
    $make('NEW', 'DEMO', 'PAID', $dates['today']);
    $counts = staff_order_counts();
    baseline_check($counts['new'] === $beforeCounts['new'] + 3 && $counts['awaiting_acceptance'] === $beforeCounts['awaiting_acceptance'] + 2 && $counts['awaiting_payment'] === $beforeCounts['awaiting_payment'] + 1, 'Cash and paid online orders await acceptance; unpaid online orders await payment');
    $pdo->exec('UPDATE orders SET payment_status="PAID" WHERE id=' . $waiting);
    $paidCounts = staff_order_counts();
    baseline_check($paidCounts['new'] === $counts['new'] && $paidCounts['awaiting_payment'] === $counts['awaiting_payment'] - 1 && $paidCounts['awaiting_acceptance'] === $counts['awaiting_acceptance'] + 1, 'Payment moves the order between staff counts without changing the total new count');
    foreach (['UNPAID','PENDING','EXPIRED','FAILED'] as $payment) {
        baseline_check(payment_status_label($payment, 'CANCELLED') === 'Cancelled - no payment due', 'Cancelled ' . $payment . ' order does not ask for payment at pickup');
    }
    baseline_check(payment_status_label('REFUND_REQUIRED', 'CANCELLED') === 'Refund required' && payment_status_label('REFUNDED', 'CANCELLED') === 'Refunded', 'Cancelled paid orders retain the truthful refund status');
    baseline_check(str_contains(payment_status_label('PAID', 'CANCELLED'), 'refund') && payment_status_label('UNPAID', 'NEW') === 'Unpaid - cash at pickup', 'Paid cancellation needs refund guidance and active cash orders keep pickup instructions');
    $waitingOrder = ['status'=>'NEW','payment_method'=>'CASH','payment_status'=>'UNPAID','created_at'=>date('Y-m-d H:i:s', time()-360)];
    baseline_check(str_contains(order_acceptance_wait_text($waitingOrder), '6 min'), 'Old cash order warns staff about delayed acceptance');
    baseline_check(order_acceptance_wait_text(array_replace($waitingOrder, ['payment_method'=>'DEMO','payment_status'=>'PAID'])) !== '', 'Paid online order can warn about delayed acceptance');
    baseline_check(order_acceptance_wait_text(array_replace($waitingOrder, ['payment_method'=>'DEMO','payment_status'=>'PENDING'])) === '', 'Online order awaiting payment has no acceptance warning');
    baseline_check(order_acceptance_wait_text(array_replace($waitingOrder, ['status'=>'ACCEPTED'])) === '', 'Accepted order no longer shows the acceptance warning');
    baseline_check(order_acceptance_wait_text(array_replace($waitingOrder, ['created_at'=>date('Y-m-d H:i:s')])) === '', 'Fresh new order has no premature warning');
    baseline_check(order_acceptance_wait_text(array_replace($waitingOrder, ['created_at'=>'invalid'])) === '', 'Missing or invalid order date does not invent a wait time');
} finally {
    $pdo->rollBack();
}
