# 功能與可靠性檢查（2026-10-03）

本次以既有 Laravel 單體架構為範圍，沿商品、購物車、結帳、訂單、退貨、會員互動及後台的直接呼叫流程檢查，並以既有 Feature／Service 測試與 MySQL 併發測試驗證。保留註冊 email 選填、結帳時計入優惠券使用次數等既有商業規則。

## 實作與修正

| 範圍 | 問題與結果 | 主要程式／測試 |
| --- | --- | --- |
| 退貨與付款 | 兩個退款入口原本可能留下不一致狀態；現在同步付款、訂單退貨及退貨申請，保留已回補庫存並只寫一次 audit。可補齊舊有「付款已退款、退貨已收件」狀態。 | `OrderPaymentService`、`ReturnRequestService`、`ReturnRefundConsistencyTest` |
| 商品評價 | 重複提交原本觸發唯一索引錯誤；現在回傳首次評價，訂單頁隱藏已提交表單，併發提交仍只建立一筆。 | `ProductReviewService`、`ProductReviewSubmissionTest`、`MySqlCartConcurrencyTest` |
| 地址簿 | 新增編輯既有地址；驗證擁有者並保留預設狀態及歷史訂單快照。 | `AddressBookService`、`AccountMaintenanceTest` |
| 通知 | 已讀通知可再次開啟目的頁，首次已讀時間不會被重寫。 | `UserNotificationController`、`AccountMaintenanceTest` |
| 管理後台 | 原本七組清單只有最新十筆；現在各自分頁、保留其他頁碼，舊資料可操作。 | `AdminDashboardController`、`AdminDashboardPaginationTest` |
| 優惠碼 | 建立前先正規化，再驗證唯一性，避免大小寫重複造成 500。 | `CreateCouponRequest`、`CouponCreationTest` |
| 共用介面 | 修正缺少 Tailwind 樣式造成的巨大分頁箭頭與未翻譯鍵；五語系共用原生分頁。導覽列可換行，手機地址編輯表單維持完整寬度。 | `pagination.blade.php`、`app.css`、`PaginationRenderingTest` |
| 帳號復原 | 補上原生 password broker 流程、五語系復原郵件與一次性 token；連結來源固定使用 `APP_URL`，已變更密碼的舊登入由原生 Session 驗證拒絕。 | `PasswordResetController`、`UserAuthController`、`PasswordRecoveryTest` |

## 驗證範圍與限制

- 完整 PHPUnit 驗證商品搜尋與收藏、角色權限、訪客購物車合併、價格／促銷、結帳交易、出貨、地址、通知、退貨及後台。MySQL 專用測試另於隔離 MySQL 8.4 容器執行，涵蓋唯一索引、資料列鎖、交易回滾、評價與退款競態。
- 瀏覽器使用隔離的測試資料，檢查桌面與 390px 手機版地址編輯、歷史訂單、已讀通知重訪，以及後台第二頁。畫面驗證與自動測試都不代表真實訂單／金流供應商已整合。
- 正式 SMTP 收信需依 `DEPLOYMENT.md` 設定後驗證；本機 log mailer 只保存郵件內容。未登記 email 的帳號無法使用郵件復原。
- 優惠券目前在建立訂單時消耗使用次數，取消或付款失敗不返還；是否改為付款成功計次／取消釋出屬於商業政策變更，未自行改動。
- 本次沒有對遠端 GitHub 執行推送、PR、合併或 Issue 更新；本機通過的測試不能取代必要 GitHub Actions checks。

## 本機驗證結果

本機工作分支：`codex/commerce-flow-improvements`。

| 本機提交 | 目的 |
| --- | --- |
| `13441f1` | 重複／併發評價與退款的一致性。 |
| `942a939` | 優惠碼正規化後的唯一性驗證。 |
| `694673d` | 地址編輯與通知重訪。 |
| `6032c47` | 後台與共用五語系分頁、響應式介面。 |
| `0ccb085` | 密碼復原與失效登入 Session 驗證。 |

| 檢查 | 結果 |
| --- | --- |
| `composer test -- --compact --no-ansi`（PHP 8.5／SQLite） | 146 通過、12 個 MySQL 專用測試略過；2620 assertions。 |
| `MySqlCartConcurrencyTest`（PHP 8.3／MySQL 8.4） | 12 通過；71 assertions。 |
| `composer analyse` | 117 個檔案分析完成，無錯誤。 |
| 修改範圍的 `vendor/bin/pint --test` | 51 個 PHP 檔案通過。 |
| `npm run build`、`git diff --check` | 通過。 |
| 瀏覽器 | 桌面／390px 手機版無水平溢出；完成地址編輯、歷史快照、通知重訪、後台第二頁，以及本機郵件連結復原、舊 Session 失效、新密碼登入。 |

## 修改檔案清單

- [.env.example](../.env.example)
- [DEVELOPMENT.md](../DEVELOPMENT.md)
- [app/Http/Controllers/AddressController.php](../app/Http/Controllers/AddressController.php)
- [app/Http/Controllers/AdminDashboardController.php](../app/Http/Controllers/AdminDashboardController.php)
- [app/Http/Controllers/CustomerOrderController.php](../app/Http/Controllers/CustomerOrderController.php)
- [app/Http/Controllers/PasswordResetController.php](../app/Http/Controllers/PasswordResetController.php)
- [app/Http/Controllers/UserAuthController.php](../app/Http/Controllers/UserAuthController.php)
- [app/Http/Controllers/UserNotificationController.php](../app/Http/Controllers/UserNotificationController.php)
- [app/Http/Requests/CreateCouponRequest.php](../app/Http/Requests/CreateCouponRequest.php)
- [app/Http/Requests/ResetPasswordRequest.php](../app/Http/Requests/ResetPasswordRequest.php)
- [app/Http/Requests/SendPasswordResetLinkRequest.php](../app/Http/Requests/SendPasswordResetLinkRequest.php)
- [app/Models/OrderItem.php](../app/Models/OrderItem.php)
- [app/Providers/AppServiceProvider.php](../app/Providers/AppServiceProvider.php)
- [app/Services/AddressBookService.php](../app/Services/AddressBookService.php)
- [app/Services/OrderPaymentService.php](../app/Services/OrderPaymentService.php)
- [app/Services/ProductReviewService.php](../app/Services/ProductReviewService.php)
- [app/Services/ReturnRequestService.php](../app/Services/ReturnRequestService.php)
- [bootstrap/app.php](../bootstrap/app.php)
- [docs/DEPLOYMENT.md](../docs/DEPLOYMENT.md)
- [docs/LOGIC_FLOWS.md](../docs/LOGIC_FLOWS.md)
- [docs/PROJECT_AUDIT.md](../docs/PROJECT_AUDIT.md)
- [docs/TESTING.md](../docs/TESTING.md)
- [docs/WEB_ENDPOINTS.md](../docs/WEB_ENDPOINTS.md)
- [lang/en.json](../lang/en.json)
- [lang/en/pagination.php](../lang/en/pagination.php)
- [lang/en/passwords.php](../lang/en/passwords.php)
- [lang/en/ui.php](../lang/en/ui.php)
- [lang/es.json](../lang/es.json)
- [lang/es/pagination.php](../lang/es/pagination.php)
- [lang/es/passwords.php](../lang/es/passwords.php)
- [lang/es/ui.php](../lang/es/ui.php)
- [lang/ja.json](../lang/ja.json)
- [lang/ja/pagination.php](../lang/ja/pagination.php)
- [lang/ja/passwords.php](../lang/ja/passwords.php)
- [lang/ja/ui.php](../lang/ja/ui.php)
- [lang/ko.json](../lang/ko.json)
- [lang/ko/pagination.php](../lang/ko/pagination.php)
- [lang/ko/passwords.php](../lang/ko/passwords.php)
- [lang/ko/ui.php](../lang/ko/ui.php)
- [lang/zh_TW.json](../lang/zh_TW.json)
- [lang/zh_TW/pagination.php](../lang/zh_TW/pagination.php)
- [lang/zh_TW/passwords.php](../lang/zh_TW/passwords.php)
- [lang/zh_TW/ui.php](../lang/zh_TW/ui.php)
- [resources/css/app.css](../resources/css/app.css)
- [resources/views/account/addresses.blade.php](../resources/views/account/addresses.blade.php)
- [resources/views/admin/dashboard.blade.php](../resources/views/admin/dashboard.blade.php)
- [resources/views/auth/forgot-password.blade.php](../resources/views/auth/forgot-password.blade.php)
- [resources/views/auth/login.blade.php](../resources/views/auth/login.blade.php)
- [resources/views/auth/reset-password.blade.php](../resources/views/auth/reset-password.blade.php)
- [resources/views/notifications/index.blade.php](../resources/views/notifications/index.blade.php)
- [resources/views/orders/index.blade.php](../resources/views/orders/index.blade.php)
- [resources/views/pagination.blade.php](../resources/views/pagination.blade.php)
- [routes/web.php](../routes/web.php)
- [tests/Feature/AccountMaintenanceTest.php](../tests/Feature/AccountMaintenanceTest.php)
- [tests/Feature/AdminDashboardPaginationTest.php](../tests/Feature/AdminDashboardPaginationTest.php)
- [tests/Feature/CouponCreationTest.php](../tests/Feature/CouponCreationTest.php)
- [tests/Feature/MySqlCartConcurrencyTest.php](../tests/Feature/MySqlCartConcurrencyTest.php)
- [tests/Feature/PaginationRenderingTest.php](../tests/Feature/PaginationRenderingTest.php)
- [tests/Feature/PasswordRecoveryTest.php](../tests/Feature/PasswordRecoveryTest.php)
- [tests/Feature/ProductReviewSubmissionTest.php](../tests/Feature/ProductReviewSubmissionTest.php)
- [tests/Feature/ReturnRefundConsistencyTest.php](../tests/Feature/ReturnRefundConsistencyTest.php)
- [tests/Support/return_refund_worker.php](../tests/Support/return_refund_worker.php)
- [tests/Support/review_create_worker.php](../tests/Support/review_create_worker.php)
- [tests/TestCase.php](../tests/TestCase.php)
