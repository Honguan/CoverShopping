# CoverShopping

CoverShopping 是以 Laravel 為核心的 B2C / B2B 電商單體專案，目標是在大型電商的交易穩定性與小型電商的擴充彈性之間取得平衡。

## 技術環境

- PHP 8.3+
- Laravel 13
- Composer 2.8+
- Node.js 22+
- MySQL 8 或測試用 SQLite
- Apache + PHP-FPM
- Redis：快取、Session、Queue 可選
- Meilisearch：商品搜尋可選，預設可使用 Laravel Scout database driver

## 快速開始

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

Apache / Nginx 的網站根目錄請指向：

```text
/path/to/CoverShopping/public
```

## 主要功能

- 商品瀏覽、分類、搜尋、推薦、最近瀏覽。
- 購物車支援訪客 session 與會員購物車，登入後合併。
- 結帳使用 transaction 與 row lock，訂單保留商品與價格快照。
- B2C 一般價格與 B2B 企業價格、企業最低採購量。
- 優惠券、滿額折扣、免運門檻、配送方式。
- 地址簿、收藏、商品評價、商品問答、通知、退貨申請。
- 商家後台：商品、SKU、低庫存、訂單出貨、問答回覆。
- 管理員後台：會員狀態、企業審核、商品審核、付款狀態、退貨狀態、優惠券與配送方式。
- Audit log 記錄重要後台與交易操作。

## API 現況

目前專案沒有 `routes/api.php`，也尚未提供 REST API v1。現行公開互動介面是 `routes/web.php` 內的 Web endpoints。端點契約請見 [docs/WEB_ENDPOINTS.md](docs/WEB_ENDPOINTS.md)。

## 測試與品質

```bash
composer test
composer analyse
composer format
npm run build
```

本機若缺 PHP / Composer，可先執行：

```bash
npm.cmd run build
git diff --check
```

完整 PHP 測試需在具備 PHP 8.3 與 Composer 的環境或 GitHub Actions 執行。測試策略請見 [docs/TESTING.md](docs/TESTING.md)。

## 文件

- [DEVELOPMENT.md](DEVELOPMENT.md)：開發環境、架構與交付流程。
- [docs/WEB_ENDPOINTS.md](docs/WEB_ENDPOINTS.md)：Web endpoints 契約。
- [docs/LOGIC_FLOWS.md](docs/LOGIC_FLOWS.md)：核心流程與資料一致性。
- [docs/TESTING.md](docs/TESTING.md)：測試範圍與驗收命令。
