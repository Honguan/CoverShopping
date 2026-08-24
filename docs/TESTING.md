# 測試策略

## 驗收命令

完整驗收：

```bash
composer install
composer analyse
composer test
npm run build
git diff --check
```

Windows 可使用：

```powershell
npm.cmd run build
```

本機缺 PHP / Composer 時，`composer analyse` 與 `composer test` 需交給 GitHub Actions 或 PHP 8.3 環境執行。

## Issue 修正驗收流程

1. 從最新 `origin/main` 建立 `issue/<issue-number>-<short-description>` 分支。
2. 本機執行與變更直接相關的最小測試，再執行完整驗收命令。
3. 建立以 `main` 為 base 的 PR；標題包含 Issue 編號，body 使用 `Refs #<issue-number>`。
4. 等待 GitHub Actions 的 `PR metadata` 與 `Quality` checks 成功後才合併。
5. 合併後必須等待 `main` 分支的 `Quality` workflow 再次成功。
6. 更新本機 `main`，確認 merge commit 與 Issue 驗收條件後才關閉 Issue。

`PR metadata` 會檢查分支命名、PR 標題、Issue 是否仍為 open，以及 PR body 不得提前使用自動關閉關鍵字。`Quality` 會對本次變更的 PHP 檔執行 Pint check，並執行前端 build、Larastan、PHPUnit、`git diff --check` 與 Docker smoke test。

## 測試範圍

### 公開與會員流程

- 訪客可瀏覽首頁、商品列表與商品詳情。
- 訪客可加入 session 購物車。
- 熱門商品只依已付款且未取消的銷量排序，重複請求使用 10 分鐘快取。
- 商品詳情的評價與問答各自分頁，頁碼參數互不衝突且關聯維持 eager loading。
- 註冊、登入、登出可正常運作。
- 會員可收藏、提問、評價、申請退貨、查看通知與標記已讀。
- 未登入使用者不可進入訂單、地址、通知與企業資料頁。

### 購物車與結帳

- 購物車支援清空且只清自己的 user/session scope。
- 購物車顯示下架、規格失效、庫存不足與企業最低量提醒。
- 結帳建立訂單快照並扣庫存。
- 優惠券、滿額折扣、免運與配送快照正確。
- SKU 庫存與商品庫存分開扣減。
- 再次購買只加入可購買數量並回報略過數量。
- MySQL 會驗證無 SKU 唯一鍵、owner XOR 約束與雙程序並發加入的原子性。

### B2B / B2C

- 一般會員使用 B2C 價格。
- 企業會員需有 approved business profile 才可使用 B2B 價格。
- B2B 最低採購量不足時不可結帳。

### 商家後台

- 商家可建立商品與 SKU。
- 商品圖片測試涵蓋累積 8 張限制、檔案／DB 失敗清理與刪除同步。
- 商家可回覆自己商品的問答。
- 商家只能出貨自己的 order item。
- 買家不可存取商家後台。

### 管理員後台

- 管理員可調整會員狀態與角色。
- 管理員可審核企業資料與商品狀態。
- 管理員可更新付款狀態，付款後訂單進入 processing。
- 管理員可建立優惠券與配送方式。
- 管理員可更新退貨狀態並同步訂單 return_status。

### 在地化

- 五種支援語系的 `ui.*` key 與 placeholder 集合必須一致。
- 結帳錯誤與成功訊息需依目前請求語系顯示。
- 通知保存 translation key，並在接收者查看時依目前語系翻譯。

## 目前測試檔

- `FavoriteAndReturnTest.php`：收藏、地址簿、預設地址唯一與接替、再次購買、購物車清空、退貨。
- `OrderCheckoutServiceTest.php`：結帳、庫存、優惠券、配送、B2B 價格。
- `PublicEndpointFlowTest.php`：公開端點、會員互動、通知與登入保護。
- `BackOfficeFlowTest.php`：商家後台、管理員後台與角色阻擋。
- `ServiceLogicFlowTest.php`：購物車狀態、價格、促銷、推薦。
- `RoleMiddlewareTest.php`：角色 middleware 基本阻擋。
- `ProductCatalogSearchTest.php`：Meilisearch 的 active、分類、價格與排序合約。

CI 的 Docker smoke test 會寫入 `uploads` volume、替換 app 容器，再確認同一檔案仍可由 `/storage/...` 讀取。
MySQL 工作流會實際並發切換預設地址，驗證會員鎖與唯一索引。
MySQL 8.4 workflow 會驗證購物車唯一性、雙重結帳、重複退貨、優惠券使用上限、交易 rollback、預設地址競態與 full-text `EXPLAIN`；這些測試是 PR 與 `main` 的必要合併條件。
