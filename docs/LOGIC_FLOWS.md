# 核心邏輯流程

## 商品瀏覽

1. `ProductCatalogController` 透過 `ProductCatalogQuery` 查詢 active 商品；關鍵字在 MySQL database driver 使用 Scout full-text，Meilisearch 直接接收狀態、分類、價格與排序條件，不支援 full-text 的 SQLite 則保留測試／開發 fallback。
2. 商品列表支援關鍵字、分類、價格與排序。
3. 商品詳情會寫入 `recently_viewed_products`，供推薦區使用。
4. `ProductRecommendationService` 提供熱門商品、最近瀏覽與相關商品。
5. 商品圖片上限為 8 張；新檔案先全部寫入，DB transaction 失敗時清除，賣家可刪除圖片並同步移除檔案。

## 地址簿

1. 建立、切換與刪除預設地址會在 transaction 內鎖定會員，避免同一會員的並發競態。
2. 資料庫唯一索引保證每位會員最多一個預設地址；刪除預設地址後，最新的剩餘地址會接替。

## 購物車

1. 訪客購物車用 `session_id`，會員購物車用 `user_id`。
2. 登入或註冊後，`ShoppingCartService::mergeGuestCartIntoUserCart` 合併訪客購物車。
3. 商品有啟用 SKU 時必須選擇有效 SKU，並依該 SKU 限制數量；無 SKU 商品才使用主商品庫存。
4. 購物車頁顯示狀態摘要：下架、SKU 失效、庫存不足、B2B 最低採購量。
5. 清空購物車只會清目前 user 或 session scope。
6. 加入與訪客合併會依商品鎖定後原子累加；資料庫強制單一 owner 與購物車 identity 唯一。

## 結帳與訂單

1. `CheckoutController` 呼叫 `OrderCheckoutService::createOrderFromCart`。
2. 結帳在 database transaction 內執行。
3. 商品與 SKU 會在交易內 `lockForUpdate` 後重新驗證，購物車重複資料會按商品／SKU 累計數量後驗證庫存。
4. 訂單會保留商品名稱、規格名稱、單價、小計、優惠券、配送方式與收件地址快照；訂單顯示及賣家履約只使用地址快照。
5. 建立訂單後會扣庫存、寫入 `inventory_movements`，並清除購物車項目。
6. 付款只能由 unpaid 轉為 paid 或 failed；paid 會進入 processing，failed 會取消訂單並恰好回補一次庫存。未出貨的 paid 訂單可作廢退款並回補一次；已收貨退貨轉為 refunded 時只同步付款狀態。
7. 會員只可取消自己的未付款待處理訂單；取消時以 transaction 鎖定訂單與庫存並寫入回補紀錄。

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
5. admin 依退貨狀態轉換更新訂單 `return_status`；首次標為 `received` 時會在 transaction 內回補商品或 SKU 庫存並寫入庫存帳。
6. 退貨申請會在 transaction 內鎖定並重新驗證訂單；資料庫限制每張訂單只能有一筆申請，且重複的 `received` 更新為 no-op，庫存回補以時間戳保證只執行一次。

## 權限原則

- `RoleMiddleware` 控制 seller/admin 後台入口。
- `Policy` 控制跨資料擁有者的商品更新、出貨、評價、問答回覆、通知讀取與退貨申請。
- Controller 不重複寫大量權限判斷，集中交由 middleware、policy、FormRequest 與 service。
