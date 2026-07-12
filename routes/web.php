<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\BusinessAccountController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\ProductCatalogController;
use App\Http\Controllers\ProductFavoriteController;
use App\Http\Controllers\SellerDashboardController;
use App\Http\Controllers\ShoppingCartController;
use App\Http\Controllers\UserAuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\ProductQuestionController;
use App\Http\Controllers\UserNotificationController;
use App\Http\Controllers\ReturnRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProductCatalogController::class, 'showProductList'])->name('catalog.index');
Route::get('/products/{product}', [ProductCatalogController::class, 'showProductDetail'])->name('catalog.show');
Route::get('/locale/{locale}', [LocaleController::class, 'update'])
    ->whereIn('locale', array_keys(config('app.supported_locales')))
    ->name('locale.update');

Route::middleware('guest')->group(function () {
    Route::get('/login', [UserAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [UserAuthController::class, 'loginUser'])->middleware('throttle:login')->name('login.store');
    Route::get('/register', [UserAuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [UserAuthController::class, 'registerUser'])->middleware('throttle:register')->name('register.store');
});

Route::post('/logout', [UserAuthController::class, 'logoutUser'])->middleware('auth')->name('logout');

Route::get('/cart', [ShoppingCartController::class, 'showCart'])->name('cart.index');
Route::post('/cart/items', [ShoppingCartController::class, 'addItem'])->name('cart.items.store');
Route::delete('/cart/items', [ShoppingCartController::class, 'clearItems'])->name('cart.items.clear');
Route::patch('/cart/items/{cartItem}', [ShoppingCartController::class, 'changeItemQuantity'])->name('cart.items.update');
Route::delete('/cart/items/{cartItem}', [ShoppingCartController::class, 'removeItem'])->name('cart.items.destroy');

Route::post('/checkout', [CheckoutController::class, 'createOrderFromCart'])->middleware('auth')->name('checkout.store');
Route::get('/orders', [CustomerOrderController::class, 'showCustomerOrders'])->middleware('auth')->name('orders.index');
Route::post('/orders/{order}/reorder', [CustomerOrderController::class, 'reorder'])->middleware('auth')->name('orders.reorder');
Route::get('/addresses', [AddressController::class, 'showAddresses'])->middleware('auth')->name('addresses.index');
Route::post('/addresses', [AddressController::class, 'storeAddress'])->middleware('auth')->name('addresses.store');
Route::patch('/addresses/{address}/default', [AddressController::class, 'setDefaultAddress'])->middleware('auth')->name('addresses.default');
Route::delete('/addresses/{address}', [AddressController::class, 'deleteAddress'])->middleware('auth')->name('addresses.destroy');
Route::post('/products/{product}/favorite', [ProductFavoriteController::class, 'addProductToFavorites'])->middleware('auth')->name('favorites.store');
Route::delete('/products/{product}/favorite', [ProductFavoriteController::class, 'removeProductFromFavorites'])->middleware('auth')->name('favorites.destroy');
Route::post('/products/{product}/reviews', [ProductReviewController::class, 'createProductReview'])->middleware('auth')->name('reviews.store');
Route::post('/products/{product}/questions', [ProductQuestionController::class, 'askProductQuestion'])->middleware('auth')->name('questions.store');
Route::post('/orders/{order}/returns', [ReturnRequestController::class, 'requestOrderReturn'])->middleware('auth')->name('returns.store');
Route::get('/notifications', [UserNotificationController::class, 'showNotifications'])->middleware('auth')->name('notifications.index');
Route::patch('/notifications/{notification}/read', [UserNotificationController::class, 'markNotificationRead'])->middleware('auth')->name('notifications.read');
Route::get('/business-profile', [BusinessAccountController::class, 'showBusinessProfileForm'])->middleware('auth')->name('business_profile.edit');
Route::post('/business-profile', [BusinessAccountController::class, 'submitBusinessProfile'])->middleware('auth')->name('business_profile.store');

Route::middleware(['auth', 'role:seller,admin'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/products', [SellerDashboardController::class, 'showSellerProducts'])->name('products.index');
    Route::post('/products', [SellerDashboardController::class, 'createProduct'])->name('products.store');
    Route::patch('/products/{product}', [SellerDashboardController::class, 'updateProductInfo'])->name('products.update');
    Route::post('/products/{product}/variants', [SellerDashboardController::class, 'createProductVariant'])->name('products.variants.store');
    Route::get('/orders', [SellerDashboardController::class, 'showSellerOrders'])->name('orders.index');
    Route::patch('/orders/{order}/items/{orderItem}/ship', [SellerDashboardController::class, 'markOrderItemShipped'])->name('orders.items.ship');
    Route::post('/questions/{productQuestion}/answers', [SellerDashboardController::class, 'answerProductQuestion'])->name('questions.answer');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'showDashboard'])->name('dashboard');
    Route::patch('/users/{user}/status', [AdminDashboardController::class, 'changeUserStatus'])->name('users.status');
    Route::patch('/business-profiles/{businessProfile}', [AdminDashboardController::class, 'reviewBusinessProfile'])->name('business_profiles.status');
    Route::patch('/products/{product}/status', [AdminDashboardController::class, 'changeProductStatus'])->name('products.status');
    Route::patch('/orders/{order}/payment', [AdminDashboardController::class, 'changeOrderPaymentStatus'])->name('orders.payment');
    Route::post('/coupons', [AdminDashboardController::class, 'createCoupon'])->name('coupons.store');
    Route::post('/shipping-methods', [AdminDashboardController::class, 'createShippingMethod'])->name('shipping_methods.store');
    Route::patch('/returns/{returnRequest}', [AdminDashboardController::class, 'changeReturnStatus'])->name('returns.status');
});
