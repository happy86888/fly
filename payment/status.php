<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$orderId = payment_safe_text($_GET['order_id'] ?? '', 50);
if ($orderId === '' || !payment_load_order($orderId)) {
    json_response(['success' => false, 'message' => '找不到訂單。'], 404);
}
try {
    $order = payment_verify_and_update($orderId, 8) ?? payment_load_order($orderId);
} catch (Throwable $e) {
    $order = payment_load_order($orderId);
}
json_response([
    'success' => true,
    'order_id' => $orderId,
    'paid' => (bool)($order['paid_verified'] ?? false),
    'status' => $order['gateway_status'] ?? null,
    'status_code' => $order['gateway_status_code'] ?? null,
    'plan_label' => $order['plan_label'] ?? '',
    'amount' => $order['amount'] ?? null,
]);
