# CoverShopping 資料庫參考

## 設計原則

- 使用 MySQL 8 InnoDB，訂單建立以 transaction 與 row lock 保護庫存。
- 商品、SKU、價格、配送與優惠券在下單時寫入訂單快照，避免後續改價影響歷史訂單。
- B2C 與 B2B 共用商品資料，B2B 以企業會員審核狀態、企業價與最低採購量控制。
- 舊站資料透過匯入指令轉入新版資料表，不再讓正式流程依賴舊表。

## 核心資料表

### users

- 會員、商家與管理員帳號。
- `role`：`customer`、`seller`、`admin`。
- `account_type`：`b2c`、`b2b`。
- `status`：`pending`、`active`、`suspended`。

### business_profiles

- 企業會員資料與審核狀態。
- `status`：`pending`、`approved`、`rejected`。
- 企業價需同時符合 `users.account_type = b2b` 與 `business_profiles.status = approved`。

### categories

- 商品分類，可支援多層分類。
- 商品列表與後台篩選應索引分類欄位。

### products

- 商品主資料，包含商家、分類、一般價格、企業價格、最低企業採購量、庫存與狀態。
- `status`：`draft`、`pending`、`active`、`archived`。
- 無 SKU 商品使用 `products.inventory`。

### product_variants

- SKU 規格資料，例如尺寸、顏色、款式。
- 有 SKU 商品使用 `product_variants.inventory`。
- `price_delta` 用於在商品基礎價格上調整規格價差。

### product_images

- 商品多圖。
- `is_primary` 代表列表與詳情預設主圖。

### cart_items

- 未登入使用 `session_id`，登入後使用 `user_id`。
- 登入時將同一 session 的購物車合併到會員購物車。

### coupons

- 優惠券規則。
- 支援固定金額與百分比折扣。
- 建議索引 `code`、`starts_at`、`ends_at`、`is_active`。

### shipping_methods

- 配送方式與運費。
- 結帳只可使用 `is_active = true` 的配送方式。

### orders

- 訂單主檔。
- `sales_channel`：`b2c`、`b2b`。
- 金額公式：`total = subtotal - discount_total + shipping_fee`。
- `payment_status`：`unpaid`、`paid`、`failed`、`refunded`。
- `fulfillment_status`：`pending`、`processing`、`partially_shipped`、`completed`、`cancelled`。
- `return_status`：`none`、`requested`、`approved`、`received`、`refunded`、`rejected`。
- `shipping_address_snapshot`：結帳時保存收件人、電話與完整地址，不受地址簿後續修改或刪除影響。

### order_items

- 訂單明細快照。
- 保存商品名稱、SKU 名稱、單價、數量與小計。
- 商家出貨以 `seller_id` 判斷可操作範圍。

### coupon_redemptions

- 優惠券使用紀錄。
- 與訂單建立在同一 transaction 內，失敗時一起 rollback。

### product_reviews

- 商品評價。
- 僅允許訂單明細屬於本人，且訂單已出貨或完成後建立。

### product_questions / product_question_answers

- 商品問答。
- 買家提問，商品商家或管理員回覆。

### notifications

- 站內通知。
- 未讀通知以 `read_at IS NULL` 判斷。

### return_requests

- 退貨申請。
- `order_id` 唯一，資料庫拒絕同一訂單的重複申請。
- `inventory_restocked_at` 記錄首次收貨回補時間，避免重複回補庫存。

### inventory_movements

- 庫存異動紀錄。
- `quantity_delta` 正數代表補貨，負數代表扣庫。
- `inventory_after` 保存異動後庫存。

### audit_logs

- 後台與交易關鍵操作紀錄。
- 可逐步改由 `spatie/laravel-activitylog` 接手。

## 結帳交易流程

```text
BEGIN
  lock cart_items by user_id or session_id
  lock products / product_variants
  validate product active
  validate stock
  validate B2B price and minimum quantity
  validate shipping method
  lock coupon if coupon exists
  create order
  create order_items snapshots
  decrement stock
  create inventory_movements
  create coupon_redemptions
  increment coupon used_count
  delete cart_items
COMMIT
```

任一步驟失敗必須 rollback，避免訂單、庫存、優惠券使用次數不一致。

## 建議索引

- `products(status, category_id, seller_id)`
- `products(seller_id, status)`
- `product_variants(product_id, sku)`
- `cart_items(user_id, product_id, product_variant_id)`
- `cart_items(session_id, product_id, product_variant_id)`
- `orders(user_id, created_at)`
- `orders(payment_status, fulfillment_status)`
- `order_items(seller_id, shipping_status)`
- `notifications(user_id, read_at, created_at)`
- `return_requests(order_id, status)`
- `coupon_redemptions(coupon_id, user_id)`
