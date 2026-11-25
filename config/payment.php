<?php

$gateway = env('PAYMENT_GATEWAY', 'manual');
$ecpayEndpoint = env('ECPAY_MODE', 'stage') === 'production'
    ? 'https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5'
    : 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5';

return [
    // manual：管理員在後台手動更新付款狀態；stripe／ecpay：會員可在訂單頁線上付款。
    'gateway' => $gateway,

    // The checkout host the order page may submit to; added to the CSP form-action directive.
    'checkout_origin' => match ($gateway) {
        'stripe' => 'https://checkout.stripe.com',
        'ecpay' => (string) parse_url($ecpayEndpoint, PHP_URL_SCHEME).'://'.parse_url($ecpayEndpoint, PHP_URL_HOST),
        default => null,
    },

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => strtolower((string) env('STRIPE_CURRENCY', 'twd')),
    ],

    'ecpay' => [
        'merchant_id' => env('ECPAY_MERCHANT_ID'),
        'hash_key' => env('ECPAY_HASH_KEY'),
        'hash_iv' => env('ECPAY_HASH_IV'),
        'mode' => env('ECPAY_MODE', 'stage'),
        'endpoint' => $ecpayEndpoint,
    ],
];
