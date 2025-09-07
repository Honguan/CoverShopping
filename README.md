# CoverShopping 電子商務平台

## 專案簡介
CoverShopping 是一個以 PHP 為主的電子商務網站，支援多角色（管理員、賣家、買家）管理商品、訂單、會員、留言等功能，採用自訂 MVC 架構，資料庫使用 MariaDB/MySQL。

## 目錄結構
```
app/
  controllers/   # 控制器（MVC Controller）
  models/        # 資料模型（MVC Model）
  views/         # 頁面檢視（MVC View）
config/
  .env           # 環境變數（資料庫、SMTP 設定）
  database.php   # 資料庫連線設定
  phpmail.tools.php # 郵件工具
  shopping.sql   # 資料庫結構
  PHPMailerN/    # PHPMailer 套件
public/
  css/           # 樣式表
  js/            # 前端 JS
  images/        # 圖片
  common/        # 公用頁首
routes/
  web.php        # 路由分發
```

## 主要功能
- 商品管理、訂單管理、會員管理、留言管理
- 賣家商品 CRUD、訂單出貨/付款/退款
- 買家購物車、下單、訂單查詢
- 管理員全域管理
- 信箱驗證、帳號啟用
- 資料庫安全連線、SMTP 郵件寄送
- .env 管理密碼設定
- .htaccess 保護敏感檔案

## 環境需求
- PHP 7.4+（建議 8.x）
- MariaDB/MySQL
- Apache（支援 .htaccess）
- PHPMailer

## 部署方式
1. 匯入 `config/shopping.sql` 建立資料庫。
2. 設定 `config/.env` 資料庫與 SMTP 參數。
3. 確認 `config/database.php`、`config/phpmail.tools.php` 已導入 .env。
4. 設定 Apache 虛擬主機根目錄至 `CoverShopping`。
5. 確認 `.htaccess` 已啟用，保護敏感檔案。
6. 依需求調整 `public/css`、`public/js`、`public/images`。

## 安全建議
- 勿將 `.env` 納入版本控管。
- 使用 `.htaccess` 禁止存取敏感檔案與目錄。
- 密碼、信箱等敏感資訊請加密儲存。
- 前後端表單皆需驗證，防止 SQL injection。

## 開源授權

本專案採用 MIT License 授權，歡迎自由使用、修改、散布及商業應用。
如有建議、回報問題或欲貢獻程式碼，請透過 GitHub Issue 或 Pull Request 聯絡。

> 本專案採用 MIT License 授權，詳見 LICENSE 檔案。
