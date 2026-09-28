# BK 機票里程實戰課｜PChomePay 串接版

## 已完成
- `registration.html` 保留 19,800 / 29,800 兩個方案。
- 新增信用卡付款（PChomePay Hosted Payment Page），同時保留原本銀行轉帳。
- 信用卡卡號 / 有效期限 / CVV 不會經過本站，使用者會被導向 PChomePay 付款頁。
- `payment/create.php` 於伺服器端取得 token、建立 CARD 訂單並取得 `payment_url`。
- `payment/notify.php` 接收 PChomePay Notify。
- `payment-return.php` 回站後再向 PChomePay API 查詢訂單，只有 API 回覆狀態 `S` 且金額一致才顯示付款成功。
- 報名資料與訂單狀態暫存於 `payment/storage/`，該目錄已附 `.htaccess` 禁止 Web 讀取。

## 目前狀態：Sandbox 測試環境
`payment/config.php` 目前設定：
- `environment = sandbox`
- API：`https://sandbox-api.pchomepay.com.tw`
- 使用你提供的「測試環境」APP ID / SECRET（放在伺服器端 `payment/credentials.php`，不會出現在 HTML / JavaScript）。

### 正式上線前
1. 至 PChomePay 取得「正式環境」APP ID / SECRET。
2. 編輯 `payment/credentials.php` 換成正式憑證。
3. 編輯 `payment/config.php`：
   - `environment` 改為 `production`
   - `api_base` 改為 `https://api.pchomepay.com.tw`
4. 確認 `https://fly.briankill.com/` 已啟用 HTTPS。
5. 主機須支援 PHP 8+、cURL，且 PHP 對 `payment/storage/` 有寫入權限。
6. 若 PChomePay 後台有啟用 IP 白名單，需加入主機「對外連線 IP」。

## 測試
PChomePay 文件提供 Sandbox 測試卡，可先測成功 / 失敗情境。
請勿使用真實信用卡測試 Sandbox。

## 安全提醒
- 不要把 `payment/credentials.php` 上傳到公開 GitHub。
- 正式環境 SECRET 建議使用主機環境變數或放在 Web Root 之外。
- 你在聊天中貼出的畫面是「測試環境」SECRET；正式金鑰請不要用截圖公開傳遞。
