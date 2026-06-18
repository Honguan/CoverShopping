# CoverShopping 開發說明

## 專案定位

CoverShopping 目前以 Laravel 單體架構維護，正式購物網站程式碼位於專案根目錄。Apache 上線時應指向 `public/`，不要直接暴露專案根目錄。

## 開發環境

- PHP 8.3+
- Composer 2.8+
- Node.js 20+
- MySQL 8
- Redis
- Meilisearch（可選，未啟用時使用資料庫搜尋）

## 第一次安裝

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
npm run build
```

## 常用指令

```bash
php artisan serve
npm run dev
npm run build
composer format
composer analyse
composer test
composer quality
```

## 目錄說明

- `app/Http/Controllers`：只處理請求、呼叫服務、回傳結果。
- `app/Http/Requests`：集中表單驗證。
- `app/Policies`：集中權限判斷。
- `app/Services`：訂單、購物車、價格、優惠券與操作紀錄邏輯。
- `app/Queries`：列表查詢、搜尋、排序與分頁。
- `database/migrations`：新版資料庫結構。
- `database/SCHEMA_REFERENCE.md`：資料表與商業邏輯對照。
- `resources/views`：Blade 頁面。
- `routes/web.php`：網站路由。

## 資料庫

開發環境請在 `.env` 設定 MySQL 連線：

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=covershopping
DB_USERNAME=root
DB_PASSWORD=
```

舊站資料匯入需額外設定 `LEGACY_DB_*`，再執行：

```bash
php artisan legacy:import-shopping
```

## 搜尋與快取

預設可先使用資料庫搜尋：

```env
SCOUT_DRIVER=database
```

若要啟用 Meilisearch：

```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=
```

Redis 建議用於快取、session 與佇列：

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

## 開發規範

- Controller 不直接寫複雜商業邏輯。
- 表單輸入先放到 FormRequest。
- 權限判斷先放到 Policy。
- 價格、庫存、優惠券、配送費由 Service 計算。
- 訂單建立必須保留 transaction 與庫存鎖定。
- 修改資料庫結構必須同步更新 `database/SCHEMA_REFERENCE.md`。
- 新增重要流程需補測試。

## 測試重點

- 會員註冊與登入。
- 購物車新增、更新、刪除。
- B2C 與 B2B 價格計算。
- 優惠券折扣。
- SKU 庫存扣減。
- 結帳 transaction 與避免超賣。
- 商家只能管理自己的商品與訂單。
- 買家只能查看自己的訂單、通知與退貨。

## 部署注意

- Apache `DocumentRoot` 必須指向 `public/`。
- 正式環境需執行 `npm run build`。
- 正式環境請設定 `APP_ENV=production` 與 `APP_DEBUG=false`。
- `.env`、`storage/`、`vendor/`、`node_modules/` 不應提交到版本庫。
- 部署後執行：

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
