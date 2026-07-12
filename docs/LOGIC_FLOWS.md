# 核心邏輯流程

## 商品瀏覽

1. `ProductCatalogController` 透過 `ProductCatalogQuery` 查詢 active 商品。
2. 商品列表支援關鍵字、分類、價格與排序。
3. 商品詳情會寫入 `recently_viewed_products`，供推薦區使用。
4. `ProductRecommendationService` 提供熱門商品、最近瀏覽與相關商品。

## 購物車

1. 訪客購物車用 `session_id`，會員購物車用 `user_id`。
2. 登入或註冊後，`ShoppingCartService::mergeGuestCartIntoUserCart` 合併訪客購物車。
3. 加入商品時會限制在可購買庫存內。
4. 購物車頁顯示狀態摘要：下架、SKU 失效、庫存不足、B2B 最低採購量。
5. 清空購物車只會清目前 user 或 session scope。

## 結帳與訂單

1. `CheckoutController` 呼叫 `OrderCheckoutService::createOrderFromCart`。
2. 結帳在 database transaction 內執行。
3. 商品與 SKU 會在交易內 `lockForUpdate` 後重新驗證。
4. 訂單會保留商品名稱、規格名稱、單價、小計、優惠券與配送方式快照。
5. 建立訂單後會扣庫存、寫入 `inventory_movements`，並清除購物車項目。
6. 會員只可取消自己的未付款待處理訂單；取消時以 transaction 鎖定訂單與庫存並寫入回補紀錄。

## 價格與促銷

1. `ProductPricingService` 判斷 B2C / B2B 價格。
2. 企業價格只在會員 `account_type=b2b` 且 business profile 為 `approved` 時可用。
3. `CouponDiscountService` 驗證優惠券狀態、時間、使用上限、最低金額與重複使用。
4. `PromotionService` 計算滿額折扣、免運門檻與剩餘免運金額。

## 再次購買

1. 會員從 `/orders/{order}/reorder` 重新加入商品。
2. 系統會跳過下架商品、失效 SKU 與無庫存商品。
3. 庫存不足時只加入目前可購買數量。
4. 回到購物車後以狀態訊息回報加入與略過數量。

## 商家後台

1. seller 可建立商品，商品預設 `pending` 等待管理員審核。
2. seller 可建立自己的商品 SKU。
3. seller 可回覆自己商品的問答，並通知提問會員。
4. seller 只能出貨自己的已付款待出貨 order item；全部出貨後訂單狀態改為 `completed`。

## 管理員後台

1. admin 可更新會員狀態與角色。
2. admin 審核企業資料後會同步會員 `account_type`。
3. admin 可審核商品、更新付款狀態、建立優惠券與配送方式。
4. 付款狀態改為 `paid` 且訂單仍為 `pending` 時，履約狀態改為 `processing`。
5. admin 更新退貨狀態時會同步訂單 `return_status`。

## 權限原則

- `RoleMiddleware` 控制 seller/admin 後台入口。
- `Policy` 控制跨資料擁有者的商品更新、出貨、評價、問答回覆、通知讀取與退貨申請。
- Controller 不重複寫大量權限判斷，集中交由 middleware、policy、FormRequest 與 service。
