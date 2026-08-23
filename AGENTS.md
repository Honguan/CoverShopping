# Repository Guidelines

## 專案結構與模組

CoverShopping 是 Laravel 13 電商單體專案。`app/Http/Controllers` 負責接收請求與回傳回應；表單驗證、權限及核心商業邏輯分別放在 `app/Http/Requests`、`app/Policies`、`app/Services`。商品查詢集中於 `app/Queries`，Web 路由位於 `routes/web.php`。Blade 畫面與前端資源存放於 `resources/`，公開入口及建置產物位於 `public/`。資料庫異動放在 `database/migrations`；流程、權限與服務測試集中於 `tests/Feature`。架構與端點異動時，請同步更新 `docs/` 內相關文件。

## 建置、測試與本機開發

- `composer install`：安裝 PHP 相依套件。
- `npm install`：安裝 Vite 前端工具。
- `php artisan migrate`：套用資料庫 migration。
- `npm run dev`：啟動 Vite 開發伺服器。
- `npm run build`：建立正式前端資源；PowerShell 可改用 `npm.cmd run build`。
- `composer test`：執行 PHPUnit 測試。
- `composer analyse`：執行 Larastan 靜態分析。
- `composer format`：使用 Laravel Pint 格式化 PHP。
- `composer quality`：依序執行格式化、分析與測試。

## 程式風格與命名

PHP 遵循 PSR-4 與 Laravel 慣例，使用 4 空格縮排；Blade、JavaScript 與 JSON 使用 2 空格。類別採 `PascalCase`，方法與變數採 `camelCase`，migration 檔名沿用時間戳與 `snake_case`。Controller 保持精簡，交易、價格、庫存及促銷邏輯放入 Service；資料驗證與授權優先沿用 FormRequest、Policy 或 middleware。提交前執行 `composer format`，Pint 會排序 import 並移除未使用 import。

## 測試準則

測試使用 PHPUnit 12 與 Laravel 測試工具，預設採 SQLite 記憶體資料庫。測試類別以 `Test.php` 結尾，方法名稱應描述可觀察行為。新功能及修正需補充最小必要的 Feature 或 Service 測試，特別覆蓋結帳交易、庫存、B2B 價格及角色權限。完整驗收執行 `composer analyse`、`composer test`、`npm run build` 與 `git diff --check`。

## Commit 與 Pull Request

沿用 Git 歷史中的 Conventional Commits，例如 `feat: add coupon validation`、`fix: prevent duplicate checkout`、`refactor: centralize seller shipments`。每次提交只包含單一目的。PR 應說明變更原因、驗證命令及受影響流程，連結相關 issue；畫面異動需附前後截圖，資料表或設定異動需列出 migration 與 `.env.example` 影響。請勿提交 `.env`、憑證、快取、日誌或建置暫存檔。

## Issue 交付工作流

1. 一張 Issue 使用一個 `issue/<issue-number>-<short-description>` 分支，且必須從最新的 `origin/main` 建立。
2. 完成修正與最小必要測試後，PR 只能以 `main` 為 base；標題必須包含 `#<issue-number>`。
3. PR body 使用 `Refs #<issue-number>`，在 main 驗證完成前不得使用 `Fixes`、`Closes` 或 `Resolves`。
4. `PR metadata` 與 `Quality` 兩個 GitHub Actions checks 全部成功後才可合併；不得以本機測試取代 workflow 結果。
5. 合併後等待 `main` 的 `Quality` workflow 成功，再以最新 `origin/main` 驗證 merge commit、Issue 驗收條件與相關檔案。
6. main 驗證成功後才關閉 Issue，並最多留下一個包含 merge commit 與測試摘要的完成留言；失敗時保持 Issue 開啟，另開後續 Issue 分支修正。
