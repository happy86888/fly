<?php
declare(strict_types=1);
require __DIR__ . '/payment/common.php';
$orderId = payment_safe_text($_GET['order_id'] ?? '', 50);
$order = $orderId ? payment_load_order($orderId) : null;
if ($order) {
    try { $order = payment_verify_and_update($orderId, 8) ?? $order; } catch (Throwable $e) {}
    if (!empty($order['paid_verified'])) {
        header('Location: payment-return.php?order_id=' . rawurlencode($orderId));
        exit;
    }
}
$config = payment_config();
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$plan = h($order['plan'] ?? '19800');
?>
<!doctype html><html lang="zh-Hant"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>付款未完成｜BK 機票里程實戰課</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;500;600;700&family=Noto+Sans+TC:wght@300;400;500;700;900&display=swap" rel="stylesheet"><link rel="stylesheet" href="payment-result.css"></head><body><main class="result-shell"><section class="result-card fail"><div class="result-mark">×</div><p class="eyebrow">PAYMENT FAILED</p><h1>付款沒有完成。</h1><p class="lead">可能是信用卡授權、OTP 驗證或付款頁逾時。你可以重新付款，也可以改用銀行轉帳。</p><?php if ($orderId): ?><div class="status-pending">訂單編號：<?=h($orderId)?></div><?php endif; ?><div class="actions"><a class="btn primary" href="registration.html?plan=<?=$plan?>">重新選擇付款方式</a><a class="btn secondary" href="<?=h($config['line_url'])?>" target="_blank" rel="noopener">LINE 詢問</a></div></section></main></body></html>
