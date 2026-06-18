# CoverShopping

CoverShopping 是以 Laravel 為核心的現代化購物網站，支援 B2C 與 B2B 銷售流程。新版專案已提升到根目錄，舊版自製 MVC 結構已移除。

## 環境需求

- PHP 8.3+
- Composer 2.8+
- Node.js 20+
- MySQL 8
- Apache + PHP-FPM
- Redis（快取與佇列）
- Meilisearch（商品搜尋，可先用資料庫搜尋）

## 安裝

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
npm run build
```

## Apache

VirtualHost 的 `DocumentRoot` 請指向：

```apache
/path/to/CoverShopping/public
```

## 舊資料匯入

如需匯入舊站資料，請在 `.env` 設定 `LEGACY_DB_*`，再執行：

```bash
php artisan legacy:import-shopping
```

## 主要功能

- 會員、商家、管理員角色
- B2C 一般價格與 B2B 企業價格
- 商品分類、多圖、SKU、庫存
- 商品推薦、最近瀏覽、價格篩選與排序
- 購物車、優惠券、滿額折扣、免運門檻、配送、結帳
- 訂單快照、庫存鎖定、出貨狀態
- 地址簿、收藏、評價、問答、通知、再次購買
- 商家低庫存提醒
- 退貨申請與後台審核
- 操作紀錄與集中權限控管

## 品質檢查

```bash
composer format
composer analyse
composer test
composer quality
```
