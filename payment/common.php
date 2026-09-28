<?php
declare(strict_types=1);

function payment_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

function payment_storage_dir(): string
{
    $dir = __DIR__ . '/storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function payment_read_json_body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function payment_order_path(string $orderId): string
{
    if (!preg_match('/^[A-Za-z0-9_-]{1,50}$/', $orderId)) {
        throw new InvalidArgumentException('Invalid order id');
    }
    return payment_storage_dir() . '/order-' . $orderId . '.json';
}

function payment_save_order(array $order): void
{
    $orderId = (string)($order['order_id'] ?? '');
    $path = payment_order_path($orderId);
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    $json = json_encode($order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException('Cannot encode order');
    }
    if (file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Cannot save order');
    }
    @chmod($path, 0600);
}

function payment_load_order(string $orderId): ?array
{
    try {
        $path = payment_order_path($orderId);
    } catch (Throwable $e) {
        return null;
    }
    if (!is_file($path)) return null;
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function payment_update_order(string $orderId, array $changes): ?array
{
    $order = payment_load_order($orderId);
    if (!$order) return null;
    foreach ($changes as $key => $value) {
        $order[$key] = $value;
    }
    $order['updated_at'] = gmdate('c');
    payment_save_order($order);
    return $order;
}

function payment_generate_order_id(): string
{
    // 最長 50 字，只使用英數、-、_。
    return 'BK' . gmdate('YmdHis') . strtoupper(bin2hex(random_bytes(5)));
}

function payment_http_request(string $method, string $url, array $headers = [], ?array $json = null, int $timeout = 12): array
{
    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('cURL initialization failed');

    $responseHeaders = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => static function ($curl, $headerLine) use (&$responseHeaders) {
            $len = strlen($headerLine);
            $parts = explode(':', $headerLine, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $len;
        },
    ]);

    if ($json !== null) {
        $encoded = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) throw new RuntimeException('JSON encode failed');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
    }

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('PChomePay connection failed: ' . $curlError);
    }

    $decoded = json_decode((string)$body, true);
    return [
        'status' => $status,
        'body' => is_array($decoded) ? $decoded : ['raw' => (string)$body],
        'headers' => $responseHeaders,
    ];
}

function pchomepay_get_token(bool $forceNew = false): string
{
    $config = payment_config();
    $tokenPath = payment_storage_dir() . '/token.json';

    if (!$forceNew && is_file($tokenPath)) {
        $cached = json_decode((string)file_get_contents($tokenPath), true);
        if (is_array($cached) && !empty($cached['token']) && (int)($cached['expired_timestamp'] ?? 0) > time() + 60) {
            return (string)$cached['token'];
        }
    }

    $basic = base64_encode($config['app_id'] . ':' . $config['secret']);
    $response = payment_http_request(
        'POST',
        rtrim($config['api_base'], '/') . '/v1/token',
        [
            'Authorization: Basic ' . $basic,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        null
    );

    $body = $response['body'];
    if ($response['status'] < 200 || $response['status'] >= 300 || empty($body['token'])) {
        $message = $body['message'] ?? $body['code'] ?? ('HTTP ' . $response['status']);
        throw new RuntimeException('PChomePay token error: ' . (string)$message);
    }

    $cache = [
        'token' => (string)$body['token'],
        'expired_timestamp' => (int)($body['expired_timestamp'] ?? (time() + (int)($body['expired_in'] ?? 28800))),
    ];
    file_put_contents($tokenPath, json_encode($cache), LOCK_EX);
    @chmod($tokenPath, 0600);
    return $cache['token'];
}

function pchomepay_api(string $method, string $path, ?array $payload = null, int $timeout = 12): array
{
    $config = payment_config();
    $token = pchomepay_get_token(false);
    $headers = [
        'Accept: application/json',
        'pcpay-token: ' . $token,
    ];
    if ($payload !== null) $headers[] = 'Content-Type: application/json';

    $response = payment_http_request(
        $method,
        rtrim($config['api_base'], '/') . $path,
        $headers,
        $payload,
        $timeout
    );

    if ((int)($response['body']['code'] ?? 0) === 10003 || (int)($response['body']['code'] ?? 0) === 10004) {
        @unlink(payment_storage_dir() . '/token.json');
        $token = pchomepay_get_token(true);
        $headers = ['Accept: application/json', 'pcpay-token: ' . $token];
        if ($payload !== null) $headers[] = 'Content-Type: application/json';
        $response = payment_http_request(
            $method,
            rtrim($config['api_base'], '/') . $path,
            $headers,
            $payload,
            $timeout
        );
    }
    return $response;
}

function pchomepay_query_order(string $orderId, int $timeout = 10): array
{
    return pchomepay_api('GET', '/v1/payment/' . rawurlencode($orderId), null, $timeout);
}

function payment_verify_and_update(string $orderId, int $timeout = 10): ?array
{
    $local = payment_load_order($orderId);
    if (!$local) return null;

    $response = pchomepay_query_order($orderId, $timeout);
    $remote = $response['body'];
    if ($response['status'] < 200 || $response['status'] >= 300 || empty($remote['order_id'])) {
        payment_update_order($orderId, [
            'verify_error' => $remote['message'] ?? $remote['code'] ?? ('HTTP ' . $response['status']),
        ]);
        return payment_load_order($orderId);
    }

    $amountMatches = (int)($remote['amount'] ?? 0) === (int)($local['amount'] ?? -1);
    $status = (string)($remote['status'] ?? '');
    $verifiedPaid = $amountMatches && $status === 'S';

    return payment_update_order($orderId, [
        'gateway_status' => $status,
        'gateway_status_code' => $remote['status_code'] ?? null,
        'gateway_pay_type' => $remote['pay_type'] ?? null,
        'gateway_trade_amount' => $remote['trade_amount'] ?? null,
        'gateway_pay_date' => $remote['pay_date'] ?? null,
        'gateway_payment_info' => $remote['payment_info'] ?? null,
        'amount_verified' => $amountMatches,
        'paid_verified' => $verifiedPaid,
        'verified_at' => gmdate('c'),
    ]);
}

function payment_safe_text($value, int $max = 500): string
{
    $text = trim((string)$value);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    return mb_substr($text, 0, $max);
}
