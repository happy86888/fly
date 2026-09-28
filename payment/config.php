<?php
$credentials = require __DIR__ . '/credentials.php';

return [
    // sandbox = 測試環境；production = 正式環境
    'environment' => 'sandbox',
    'app_id' => $credentials['app_id'],
    'secret' => $credentials['secret'],
    'api_base' => 'https://sandbox-api.pchomepay.com.tw',
    'site_url' => 'https://fly.briankill.com',
    'line_url' => 'https://line.me/R/ti/p/@tpq5223k',
    'notify_source_ip' => '113.196.231.190',
    'plans' => [
        '19800' => [
            'label' => '機票里程實戰課＋一年一對一諮詢',
            'amount' => 19800,
            'item_name' => 'BK 機票里程實戰課 19,800 方案',
        ],
        '29800' => [
            'label' => '完整課程＋美卡諮詢服務',
            'amount' => 29800,
            'item_name' => 'BK 機票里程實戰課 29,800 方案',
        ],
    ],
];
