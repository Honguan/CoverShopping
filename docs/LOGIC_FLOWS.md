# 核心邏輯流程

## 密碼復原

1. 訪客依註冊 email 申請復原，沿用 Laravel `users` password broker、既有 token 資料表與 60 分鐘有效期。
2. 成功寄送、未知 email 與 broker 寄送節流使用同一提示；端點另外限制來源請求頻率。已登入會員不使用訪客復原頁面。
3. 郵件與頁面支援五語系，復原 URL 固定以設定的 `APP_URL` 產生，避免請求 Host 影響郵件目的地。
4. 重設密碼沿用註冊密碼規則，驗證 token 與確認欄位；成功時更新密碼雜湊、輪換 remember token、觸發 `PasswordReset`，不更動角色或停權狀態，並回到登入頁。token 使用後刪除，重用或過期會被拒絕。
5. 登入與註冊立即記錄 Session 密碼雜湊，原生 `AuthenticateSession` 於後續請求拒絕密碼已變更的舊登入；訪客購物車 Session 不受影響。
6. 僅 email 可回填；密碼與復原 token 不會放入驗證失敗的 old input。正式 SMTP 與無 email 帳號的限制見 `DEPLOYMENT.md`。

## 商品瀏覽

1. `ProductCatalogController` 透過 `ProductCatalogQuery` 查詢 active 商品；關鍵字在 MySQL database driver 使用 Scout full-text，Meilisearch 直接接收狀態、分類、價格與排序條件，不支援 full-text 的 SQLite 則保留測試／開發 fallback。
2. 商品列表支援關鍵字、分類、價格與排序。
3. 商品詳情會寫入 `recently_viewed_products`，供推薦區使用。
4. `ProductRecommendationService` 提供熱門商品、最近瀏覽與相關商品；熱門排行只計入已付款且未取消的訂單，商品 ID 快取 10 分鐘後重新聚合。
5. 商品圖片上限為 8 張；新檔案先全部寫入，DB transaction 失敗時清除，賣家可刪除圖片並同步移除檔案。

## 地址簿

1. 建立、切換與刪除預設地址會在 transaction 內鎖定會員，避免同一會員的並發競態。
2. 資料庫唯一索引保證每位會員最多一個預設地址；刪除預設地址後，最新的剩餘地址會接替。
3. 編輯既有地址沿用會員鎖與擁有者範圍，保留原預設狀態；歷史訂單仍使用結帳時的地址快照。

## 收藏

收藏清單位於 `/favorites`，只讀取登入者自己的收藏並以收藏時間／ID 遞減排序，每頁 24 筆。商品圖片與 SKU 一次 eager load；上架商品沿用商品卡，下架商品只顯示名稱、不可購買提示與移除按鈕。移除沿用 `ProductFavoriteService` 的 user scope，不影響其他會員。

## 購物車

1. 訪客購物車用 `session_id`，會員購物車用 `user_id`。
2. 登入或註冊後，`ShoppingCartService::mergeGuestCartIntoUserCart` 合併訪客購物車；缺貨項目保留數量 1 供調整或移除，不產生數量 0。
3. 商品有啟用 SKU 時必須選擇有效 SKU，並依該 SKU 限制數量；無 SKU 商品才使用主商品庫存。
4. 購物車頁顯示狀態摘要：下架、漏選或失效 SKU、無效數量、庫存不足、B2B 最低採購量；商品查詢一併取得啟用 SKU 是否存在，避免逐項查詢。
5. 清空購物車只會清目前 user 或 session scope。
6. 加入與訪客合併會依商品鎖定後原子累加；資料庫強制單一 owner 與購物車 identity 唯一。

## 結帳與訂單

1. `CheckoutController` 呼叫 `OrderCheckoutService::createOrderFromCart`。
2. 結帳在 database transaction 內執行。
3. 商品與 SKU 會在交易內 `lockForUpdate` 後重新驗證；新增 SKU 後的舊購物車也必須選擇規格。先拒絕任一數量小於 1 的項目，再按商品／SKU 累計重複資料並驗證庫存；不合法時不寫入訂單、庫存或優惠券，也不刪除購物車。
4. 訂單會保留商品名稱、規格名稱、單價、小計、優惠券、配送方式與收件地址快照；訂單顯示及賣家履約只使用地址快照。
5. 建立訂單後會扣庫存、寫入 `inventory_movements`，並清除購物車項目。
6. 付款只能由 unpaid 轉為 paid 或 failed；paid 會進入 processing，failed 會取消訂單並恰好回補一次庫存。未出貨的 paid 訂單可作廢退款並回補一次；已收貨退貨的兩個退款入口都同步付款、訂單退貨與退貨申請狀態，不再回補庫存；既有付款已退款但退貨仍為 received 的資料可由同一路徑補齊。
7. 會員只可取消自己的未付款待處理訂單；取消時以 transaction 鎖定訂單與庫存並寫入回補紀錄。

## 價格與促銷

1. `ProductPricingService` 判斷 B2C / B2B 價格。
2. 企業價格只在會員 `account_type=b2b` 且 business profile 為 `approved` 時可用。
3. `CouponDiscountService` 驗證優惠券狀態、時間、使用上限、最低金額與重複使用。
4. `PromotionService` 計算滿額折扣、免運門檻與剩餘免運金額。
5. 新建優惠碼在唯一驗證前統一轉為大寫，避免驗證與儲存代碼不同。

## 評價與通知

1. 評價先核對商品、訂單項目與買家權限，再以既有唯一鍵取得或建立；重送保留首次內容，僅建立時寫入一次 audit log。訂單頁 eager load 評價，已提交項目改顯示提示。
2. 標記通知已讀使用條件更新保留首次時間；已讀通知仍可從通知中心重新開啟目標。

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
7. 退貨更新與付款退款皆採訂單→退貨申請鎖序，退款由付款服務統一同步並各記錄一次 audit。
8. 七組後台清單各自分頁，保留其他組頁碼並以 ID 決定同時間資料的穩定順序；配送方式仍以 sort_order 優先。
9. 所有完整分頁共用原生 HTML/CSS 導覽，沿用 Laravel 產生的頁碼與 URL；上一頁、下一頁及無障礙標籤支援五語系。

## 權限原則

- `RoleMiddleware` 控制 seller/admin 後台入口。
- `Policy` 控制跨資料擁有者的商品更新、出貨、評價、問答回覆、通知讀取與退貨申請。
- Controller 不重複寫大量權限判斷，集中交由 middleware、policy、FormRequest 與 service。
