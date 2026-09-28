<?php
declare(strict_types=1);
require __DIR__ . '/payment/common.php';
$orderId = payment_safe_text($_GET['order_id'] ?? '', 50);
$order = $orderId !== '' ? payment_load_order($orderId) : null;
if ($order) {
    try { $order = payment_verify_and_update($orderId, 10) ?? $order; } catch (Throwable $e) {}
}
$paid = (bool)($order['paid_verified'] ?? false);
$status = (string)($order['gateway_status'] ?? '');
$statusCode = (string)($order['gateway_status_code'] ?? '');
$config = payment_config();
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="zh-Hant"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>付款結果｜BK 機票里程實戰課</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;500;600;700&family=Noto+Sans+TC:wght@300;400;500;700;900&display=swap" rel="stylesheet"><link rel="stylesheet" href="payment-result.css"></head><body><main class="result-shell">
<?php if (!$order): ?>
<section class="result-card fail"><div class="result-mark">!</div><p class="eyebrow">PAYMENT RESULT</p><h1>找不到這筆訂單。</h1><p class="lead">可能是付款回傳資訊不完整。你可以回到報名頁重新操作，或直接透過 LINE 聯絡我確認。</p><div class="actions"><a class="btn primary" href="registration.html">回報名頁</a><a class="btn secondary" href="<?=h($config['line_url'])?>" target="_blank" rel="noopener">LINE 詢問</a></div></section>
<?php elseif ($paid): ?>
<section class="result-card success"><div class="result-mark">✓</div><p class="eyebrow">PAYMENT COMPLETE</p><h1>付款完成，報名成功。</h1><p class="lead">刷卡狀態已向 PChomePay 再次確認。接下來請加入 LINE 並告訴我「已完成報名」，我會協助後續課程開通與諮詢安排。</p><div class="detail-box"><dl><div><dt>訂單編號</dt><dd><?=h($orderId)?></dd></div><div><dt>課程方案</dt><dd><?=h($order['plan_label'] ?? '')?></dd></div><div><dt>金額</dt><dd>NT$<?=number_format((int)($order['amount'] ?? 0))?></dd></div><div><dt>付款方式</dt><dd>信用卡<?= !empty($order['gateway_payment_info']['installment']) ? '／'.$order['gateway_payment_info']['installment'].' 期' : '' ?></dd></div></dl></div><div class="actions"><a class="btn primary" href="<?=h($config['line_url'])?>" target="_blank" rel="noopener">LINE 回報已完成報名</a><a class="btn secondary" href="index.html">回課程首頁</a></div><p class="fine">如你勾選需要服務契約書，我會依報名資料另行提供。</p></section>
<?php elseif ($status === 'W' || $status === ''): ?>
<section class="result-card"><div class="result-mark">…</div><p class="eyebrow">PAYMENT CHECK</p><h1>正在確認付款狀態。</h1><p class="lead">金流目前仍顯示等待確認。若你剛完成 OTP 驗證，可稍等幾秒後重新整理本頁。</p><div class="status-pending">訂單編號：<?=h($orderId)?><?= $statusCode ? '／狀態代碼：'.h($statusCode) : '' ?></div><div class="actions"><a class="btn primary" href="payment-return.php?order_id=<?=urlencode($orderId)?>">重新確認付款</a><a class="btn secondary" href="<?=h($config['line_url'])?>" target="_blank" rel="noopener">LINE 詢問</a></div></section>
<?php else: ?>
<section class="result-card fail"><div class="result-mark">×</div><p class="eyebrow">PAYMENT NOT COMPLETE</p><h1>這筆付款尚未完成。</h1><p class="lead">目前金流狀態未確認為成功。你可以回報名頁重新建立刷卡訂單，或改用銀行轉帳。</p><div class="status-pending">訂單編號：<?=h($orderId)?><?= $statusCode ? '／狀態代碼：'.h($statusCode) : '' ?></div><div class="actions"><a class="btn primary" href="registration.html?plan=<?=h($order['plan'] ?? '19800')?>">重新付款</a><a class="btn secondary" href="<?=h($config['line_url'])?>" target="_blank" rel="noopener">LINE 詢問</a></div></section>
<?php endif; ?>
</main></body></html>
