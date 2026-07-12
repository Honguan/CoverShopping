# 容器化部署

## 初次部署

1. 複製 `.env.example` 為 `.env`，設定長度至少 32 字元的 `APP_KEY`、資料庫帳密與 `MYSQL_ROOT_PASSWORD`。
2. 將 `APP_URL` 設為正式 HTTPS 網址，並保留 `APP_DEBUG=false`、`SESSION_SECURE_COOKIE=true`。
3. 執行 `docker compose up -d --build`，服務預設只綁定 `127.0.0.1:8080`；請由反向代理提供 TLS。
4. 僅在單一部署工作程序執行 `docker compose exec app php artisan migrate --force`。

## 更新與維運

更新版本後執行 `docker compose up -d --build`，再於單一工作程序執行 migration。容器啟動時會快取設定、路由與 Blade 畫面；不會自動 migration，避免多副本同時更新資料庫。Redis 提供快取、Session 與 Queue；資料庫與 Redis 使用 Docker named volume 保留資料。

正式環境請將 MySQL、Redis、檔案儲存與 Queue worker 改為受管服務或獨立可水平擴充的工作程序。部署前後使用 `/health` 健康檢查，它會驗證資料庫與正式 Redis 快取，並由 CI 執行 `composer analyse`、`composer test` 與 `npm run build`。

Compose 的 `app` 服務會自行以 `/health` 執行健康檢查，編排平台可據此等待可用實例後再導入流量。
