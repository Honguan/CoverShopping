# 容器化部署

## 初次部署

1. 複製 `.env.example` 為 `.env`，設定長度至少 32 字元的 `APP_KEY`、資料庫帳密與 `MYSQL_ROOT_PASSWORD`。
2. 將 `APP_URL` 設為正式 HTTPS 網址，並保留 `APP_DEBUG=false`、`SESSION_SECURE_COOKIE=true`。將 `TRUSTED_PROXIES` 設為實際反向代理的 IP／CIDR；多筆以逗號分隔，未設定時不信任任何代理，請勿使用 `*`。
3. 執行 `docker compose up -d --build`，服務預設只綁定 `127.0.0.1:8080`；應只允許設定於 `TRUSTED_PROXIES` 的反向代理連線，由其提供 TLS 並轉送標準 `X-Forwarded-*` 標頭，不可將 app port 直接暴露於公網。
4. 僅在單一部署工作程序執行 `docker compose exec app php artisan migrate --force`。

## 更新與維運

### 密碼復原郵件

密碼復原連結固定使用 `APP_URL`，正式環境必須設定為會員可存取的 HTTPS 網址。`.env.example` 的 `MAIL_MAILER=log` 僅供本機開發，郵件內容會寫入應用程式日誌，不會寄至信箱；請限制日誌存取權限。

正式寄信請使用 `MAIL_MAILER=smtp`，設定 `MAIL_SCHEME`、`MAIL_HOST`、`MAIL_PORT`、`MAIL_USERNAME`、`MAIL_PASSWORD`、`MAIL_FROM_ADDRESS` 與 `MAIL_FROM_NAME`。常見 STARTTLS 使用 `MAIL_SCHEME=smtp`／587，隱式 TLS 使用 `MAIL_SCHEME=smtps`／465；以實際郵件服務設定為準。更新設定後重新部署以重建設定快取，並使用測試帳號確認收信與連結來源。

目前復原郵件同步寄送；寄送失敗時須檢查郵件服務與應用程式日誌。未登記 email 的既有帳號不能使用郵件復原，註冊 email 仍維持選填。自動測試驗證通知內容與 token 流程，不代表正式 SMTP 已完成收信驗證。

新登入與註冊會記錄 Session 密碼雜湊；重設密碼後，舊登入會在下一次請求登出。部署前已存在、尚未記錄雜湊的 Session 會在第一次請求初始化，無法保證因部署前的密碼變更而立即失效；若維運要求撤銷這些舊 Session，須依實際 Session driver 安排登出切換。

更新版本後執行 `docker compose up -d --build`，再於單一工作程序執行 migration。容器啟動時會快取設定、路由與 Blade 畫面；不會自動 migration，避免多副本同時更新資料庫。Redis 提供快取、Session 與 Queue；資料庫、Redis 與 `storage/app/public` 分別使用 `database`、`redis`、`uploads` named volume 保留資料，啟動時會建立 `public/storage` 連結。

使用 `SCOUT_DRIVER=meilisearch` 時，搜尋欄位設定異動後執行 `php artisan scout:sync-index-settings`，模型欄位異動後再執行 `php artisan scout:import "App\\Models\\Product"`。

請定期備份 `uploads` volume，並在還原演練中確認 `/storage/...` 可讀取。此本機 volume 方案只適用單一 app 主機；多主機或多副本部署必須將 Laravel `public` disk 改為所有副本共用的物件儲存，不能各自使用本機 volume。

正式環境請將 MySQL、Redis、檔案儲存與 Queue worker 改為受管服務或獨立可水平擴充的工作程序。`/health/live` 只確認 Laravel 可啟動，供平台判斷是否重啟容器；`/health/ready` 會驗證 migration、核心資料表，以及目前啟用的 cache/session driver，只有回傳 200 才可導入流量。

部署時先等待 `/health/live`，再由單一工作程序執行 migration，最後等待 `/health/ready` 成功。Compose 的 `app` healthcheck 使用 readiness；migration 前回傳 503 是預期行為。CI 會自動驗證 migration 前失敗、migration 後成功與 cache/session 依賴失敗情境。

MySQL 的 healthcheck 必須使用應用程式帳密，以 TCP 連線至目標資料庫並執行 `SELECT 1`。初始化期間的暫時伺服器只開放 Unix socket，不能用 localhost 的 `mysqladmin ping` 當作就緒判斷；帳密錯誤或資料庫不存在時也必須保持 unhealthy。
