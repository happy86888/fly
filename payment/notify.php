<?php
declare(strict_types=1);
require __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'method not allowed';
    exit;
}

$notifyType = payment_safe_text($_POST['notify_type'] ?? '', 50);
$notifyRaw = (string)($_POST['notify_message'] ?? '');
$message = json_decode($notifyRaw, true);
$orderId = is_array($message) ? payment_safe_text($message['order_id'] ?? '', 50) : '';

if ($orderId !== '' && payment_load_order($orderId)) {
    $config = payment_config();
    $remoteAddr = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $amountMatches = (int)($message['amount'] ?? -1) === (int)(payment_load_order($orderId)['amount'] ?? -2);
    $sourceIpMatches = $remoteAddr === (string)$config['notify_source_ip'];

    payment_update_order($orderId, [
        'last_notify_type' => $notifyType,
        'last_notify_at' => gmdate('c'),
        'notify_source_ip' => $remoteAddr,
        'notify_source_ip_matches' => $sourceIpMatches,
        'notify_amount_matches' => $amountMatches,
        'notify_gateway_status' => $message['status'] ?? null,
        'notify_gateway_status_code' => $message['status_code'] ?? null,
    ]);

    // 通知本身沒有簽章欄位，不直接只憑通知標成「已付款」。
    // 若來源 IP 與金額皆吻合，才嘗試向 API 再查一次訂單狀態做最終確認。
    if ($notifyType === 'order_confirm' && $amountMatches && $sourceIpMatches) {
        try {
            payment_verify_and_update($orderId, 2);
        } catch (Throwable $e) {
            error_log('[BK PChomePay notify verify] ' . $e->getMessage());
        }
    }
}

// 文件要求 Notify URL 在 3 秒內回傳 success。
header('Content-Type: text/plain; charset=utf-8');
echo 'success';
