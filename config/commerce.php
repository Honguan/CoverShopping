<?php

return [
    'free_shipping_threshold' => env('FREE_SHIPPING_THRESHOLD', 1200),
    'order_discount_threshold' => env('ORDER_DISCOUNT_THRESHOLD', 3000),
    'order_discount_amount' => env('ORDER_DISCOUNT_AMOUNT', 200),
    'low_stock_threshold' => env('LOW_STOCK_THRESHOLD', 5),
];
