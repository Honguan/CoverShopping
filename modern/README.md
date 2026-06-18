# CoverShopping Modern

新版以 Laravel 單體架構承接舊 CoverShopping，先保留舊專案，再以 `modern/` 逐步替換正式流量。

## 環境

- PHP 8.3+
- Composer 2.8+
- Node.js 20+
- MySQL 8
- Apache + PHP-FPM

## 安裝

```bash
cd modern
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
npm run build
```

## 匯入舊資料

`.env` 內設定 `LEGACY_DB_*` 指向舊 `shopping` 資料庫後執行：

```bash
php artisan legacy:import-shopping
```

匯入內容包含舊會員、管理員、商品、商品圖片路徑、訂單與訂單明細快照。

## Apache

正式切換時將 VirtualHost `DocumentRoot` 指向：

```apache
/path/to/CoverShopping/modern/public
```

並啟用 rewrite，讓所有請求進入 Laravel front controller。

## 驗收

```bash
php artisan test
```

需確認：

- 可註冊、登入、登出。
- 商品可查詢、瀏覽詳情、加入購物車。
- 登入後可結帳，庫存會在交易中扣減。
- 庫存不足時不可建立訂單。
- 管理員、商家、會員權限互不越權。
