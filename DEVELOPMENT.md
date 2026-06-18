# CoverShopping 開發文件

## 專案定位

CoverShopping 是 Laravel 單體電商系統。第一階段優先保證會員、商品、購物車、結帳、訂單、庫存、商家後台與管理員後台穩定運作，再逐步擴充搜尋、推薦、促銷與 B2B 能力。

## 目錄職責

- `app/Http/Controllers`：接收請求、呼叫 Service、回傳 View 或 redirect。
- `app/Http/Requests`：集中表單驗證與基本授權。
- `app/Policies`：集中跨角色資料權限。
- `app/Services`：交易、價格、促銷、購物車、推薦、audit log 等核心邏輯。
- `app/Queries`：商品列表查詢、篩選與排序。
- `database/migrations`：新版資料表定義。
- `resources/views`：Blade 前後台頁面。
- `routes/web.php`：目前所有公開 Web endpoints。
- `tests/Feature`：流程、端點、服務邏輯與權限測試。

## 開發流程

1. 從目前正式新版 Laravel 根目錄工作：`C:\Users\666dd\Documents\GitHub\html\CoverShopping`。
2. 修改前先確認工作樹：`git status --short`。
3. 新功能先補 Feature 或 Service 測試，再實作。
4. Controller 保持薄層，複雜邏輯放 Service / Policy / FormRequest。
5. 完成後執行品質檢查，提交只包含本輪相關檔案。

## 本機命令

```bash
composer install
npm install
php artisan migrate
composer test
composer analyse
npm run build
```

Windows PowerShell 若 `npm` 被執行原則擋下，使用：

```powershell
npm.cmd run build
```

## 環境限制

目前部分本機環境可能沒有 PHP / Composer。若無法執行 `composer test` 或 `composer analyse`，需在 GitHub Actions 或具備 PHP 8.3 的環境補跑。

## 資料一致性原則

- 結帳必須使用 database transaction。
- 扣庫存必須在交易內重新讀取商品 / SKU 並確認庫存。
- 訂單明細保留商品名稱、規格、單價與小計快照。
- B2B 價格只在會員企業資料審核通過時可用。
- 後台跨角色操作必須經 middleware 或 policy 阻擋。

## 文件更新規則

- 新增或修改 Web endpoint 時同步更新 `docs/WEB_ENDPOINTS.md`。
- 新增交易、購物車、後台或通知流程時同步更新 `docs/LOGIC_FLOWS.md`。
- 新增測試策略或驗收命令時同步更新 `docs/TESTING.md`。
