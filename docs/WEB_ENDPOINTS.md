# Web Endpoints 契約

目前沒有 `routes/api.php`；本文件描述 `routes/web.php` 的現行 Web endpoints。這些端點回傳 Blade 頁面或 redirect，不是 JSON REST API。

## Catalog

| Method | Path | Route name | 說明 |
| --- | --- | --- | --- |
| GET | `/` | `catalog.index` | 商品列表、分類、熱門商品、最近瀏覽。 |
| GET | `/products/{product}` | `catalog.show` | 商品詳情、多圖、SKU、評價、問答與相關商品。 |

## Auth

| Method | Path | Route name | 說明 |
| --- | --- | --- | --- |
| GET | `/login` | `login` | 登入頁。 |
| POST | `/login` | `login.store` | 登入並合併訪客購物車。 |
| GET | `/register` | `register` | 註冊頁。 |
| POST | `/register` | `register.store` | 建立 customer 帳號並登入。 |
| POST | `/logout` | `logout` | 登出並重建 session token。 |

## Cart / Checkout / Orders

| Method | Path | Route name | 說明 |
| --- | --- | --- | --- |
| GET | `/cart` | `cart.index` | 顯示購物車、狀態提醒、結帳預設地址與配送。 |
| POST | `/cart/items` | `cart.items.store` | 加入商品或 SKU 到 user/session 購物車；商品有啟用 SKU 時必須傳入該商品的有效 `product_variant_id`。 |
| DELETE | `/cart/items` | `cart.items.clear` | 清空目前 user/session 購物車。 |
| PATCH | `/cart/items/{cartItem}` | `cart.items.update` | 調整單一購物車項目數量。 |
| DELETE | `/cart/items/{cartItem}` | `cart.items.destroy` | 移除單一購物車項目。 |
| POST | `/checkout` | `checkout.store` | 建立訂單、扣庫存、套用優惠與運費。 |
| GET | `/orders` | `orders.index` | 會員訂單列表。 |
| POST | `/orders/{order}/reorder` | `orders.reorder` | 將可購買商品再次加入購物車。 |
| POST | `/orders/{order}/cancel` | `orders.cancel` | 取消自己的未付款待處理訂單並回補庫存。 |

## Account / Engagement

| Method | Path | Route name | 說明 |
| --- | --- | --- | --- |
| GET | `/addresses` | `addresses.index` | 地址簿。 |
| POST | `/addresses` | `addresses.store` | 新增地址，可設為預設。 |
| PATCH | `/addresses/{address}/default` | `addresses.default` | 設定預設地址。 |
| DELETE | `/addresses/{address}` | `addresses.destroy` | 刪除自己的地址。 |
| POST | `/products/{product}/favorite` | `favorites.store` | 收藏商品。 |
| DELETE | `/products/{product}/favorite` | `favorites.destroy` | 取消收藏。 |
| POST | `/products/{product}/reviews` | `reviews.store` | 對已完成訂單商品評價。 |
| POST | `/products/{product}/questions` | `questions.store` | 對商品提問。 |
| POST | `/orders/{order}/returns` | `returns.store` | 對已出貨或完成訂單申請退貨。 |
| GET | `/notifications` | `notifications.index` | 會員通知列表。 |
| PATCH | `/notifications/{notification}/read` | `notifications.read` | 標記自己的通知為已讀。 |
| GET | `/business-profile` | `business_profile.edit` | 企業資料頁。 |
| POST | `/business-profile` | `business_profile.store` | 送出 B2B 企業審核資料。 |

## Seller

所有 seller endpoints 需 `auth` 且角色為 `seller` 或 `admin`。

| Method | Path | Route name | 說明 |
| --- | --- | --- | --- |
| GET | `/seller/products` | `seller.products.index` | 商家商品、低庫存與問答管理。 |
| POST | `/seller/products` | `seller.products.store` | 建立商品，seller 預設 pending。 |
| PATCH | `/seller/products/{product}` | `seller.products.update` | 更新自己的商品。 |
| POST | `/seller/products/{product}/variants` | `seller.products.variants.store` | 建立商品 SKU。 |
| GET | `/seller/orders/export` | `seller.orders.export` | 匯出自己的訂單明細 CSV，包含 B2B 採購單號。 |
| GET | `/seller/orders` | `seller.orders.index` | 商家訂單明細。 |
| PATCH | `/seller/orders/{order}/items/{orderItem}/ship` | `seller.orders.items.ship` | 出貨自己的訂單明細。 |
| POST | `/seller/questions/{productQuestion}/answers` | `seller.questions.answer` | 回覆自己商品的問答。 |

## Admin

所有 admin endpoints 需 `auth` 且角色為 `admin`。

| Method | Path | Route name | 說明 |
| --- | --- | --- | --- |
| GET | `/admin/dashboard` | `admin.dashboard` | 管理員總覽。 |
| PATCH | `/admin/users/{user}/status` | `admin.users.status` | 更新會員狀態或角色。 |
| PATCH | `/admin/business-profiles/{businessProfile}` | `admin.business_profiles.status` | 審核企業資料。 |
| PATCH | `/admin/products/{product}/status` | `admin.products.status` | 審核商品狀態。 |
| PATCH | `/admin/orders/{order}/payment` | `admin.orders.payment` | 更新付款狀態。 |
| POST | `/admin/coupons` | `admin.coupons.store` | 建立優惠券。 |
| POST | `/admin/shipping-methods` | `admin.shipping_methods.store` | 建立配送方式。 |
| PATCH | `/admin/returns/{returnRequest}` | `admin.returns.status` | 更新退貨狀態。 |
