<?php
declare(strict_types=1);
require __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    $config = payment_config();
    $data = payment_read_json_body();

    $planId = payment_safe_text($data['plan'] ?? '', 10);
    $plan = $config['plans'][$planId] ?? null;
    if (!$plan) json_response(['success' => false, 'message' => '課程方案錯誤。'], 422);

    $name = payment_safe_text($data['name'] ?? '', 80);
    $phone = payment_safe_text($data['phone'] ?? '', 30);
    $email = payment_safe_text($data['email'] ?? '', 120);
    $note = payment_safe_text($data['note'] ?? '', 1000);
    $contract = !empty($data['contract']);

    if ($name === '' || $phone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['success' => false, 'message' => '請確認姓名、手機與 Email。'], 422);
    }

    $orderId = payment_generate_order_id();
    $siteUrl = rtrim($config['site_url'], '/');
    $returnUrl = $siteUrl . '/payment-return.php?order_id=' . rawurlencode($orderId);
    $failUrl = $siteUrl . '/payment-failed.php?order_id=' . rawurlencode($orderId);
    $notifyUrl = $siteUrl . '/payment/notify.php';

    $order = [
        'order_id' => $orderId,
        'plan' => $planId,
        'plan_label' => $plan['label'],
        'amount' => (int)$plan['amount'],
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'note' => $note,
        'contract_requested' => $contract,
        'payment_method' => 'CARD',
        'environment' => $config['environment'],
        'status' => 'creating_payment',
        'created_at' => gmdate('c'),
        'updated_at' => gmdate('c'),
    ];
    payment_save_order($order);

    $payload = [
        'order_id' => $orderId,
        'pay_type' => ['CARD'],
        'amount' => (int)$plan['amount'],
        'return_url' => $returnUrl,
        'fail_return_url' => $failUrl,
        'notify_url' => $notifyUrl,
        'buyer_email' => $email,
        'items' => [[
            'name' => $plan['item_name'],
            'url' => $siteUrl . '/',
        ]],
        'card_installment' => '1',
        'return_timer' => 'N',
    ];

    $response = pchomepay_api('POST', '/v1/payment', $payload);
    $body = $response['body'];

    if ($response['status'] < 200 || $response['status'] >= 300 || empty($body['payment_url'])) {
        payment_update_order($orderId, [
            'status' => 'gateway_create_failed',
            'gateway_error_code' => $body['code'] ?? null,
            'gateway_error_message' => $body['message'] ?? ('HTTP ' . $response['status']),
        ]);
        json_response([
            'success' => false,
            'message' => '目前無法建立刷卡訂單，請稍後再試或改用銀行轉帳。',
            'gateway_code' => $body['code'] ?? null,
        ], 502);
    }

    payment_update_order($orderId, [
        'status' => 'payment_page_created',
        'payment_url' => (string)$body['payment_url'],
    ]);

    json_response([
        'success' => true,
        'order_id' => $orderId,
        'payment_url' => (string)$body['payment_url'],
        'environment' => $config['environment'],
    ]);
} catch (Throwable $e) {
    error_log('[BK PChomePay create] ' . $e->getMessage());
    json_response(['success' => false, 'message' => '付款服務暫時無法連線，請稍後再試。'], 500);
}
