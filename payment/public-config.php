<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
$config = payment_config();
json_response([
    'environment' => $config['environment'],
    'sandbox' => $config['environment'] === 'sandbox',
]);
