<?php

use App\Http\Controllers\AccountOrderController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReturnRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/products/{product}', [CatalogController::class, 'show'])->name('catalog.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->name('cart.items.update');
Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.items.destroy');

Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('auth')->name('checkout.store');
Route::get('/orders', [AccountOrderController::class, 'index'])->middleware('auth')->name('orders.index');
Route::post('/products/{product}/favorite', [FavoriteController::class, 'store'])->middleware('auth')->name('favorites.store');
Route::delete('/products/{product}/favorite', [FavoriteController::class, 'destroy'])->middleware('auth')->name('favorites.destroy');
Route::post('/orders/{order}/returns', [ReturnRequestController::class, 'store'])->middleware('auth')->name('returns.store');

Route::middleware(['auth', 'role:seller,admin'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/products', [SellerController::class, 'products'])->name('products.index');
    Route::post('/products', [SellerController::class, 'storeProduct'])->name('products.store');
    Route::patch('/products/{product}', [SellerController::class, 'updateProduct'])->name('products.update');
    Route::get('/orders', [SellerController::class, 'orders'])->name('orders.index');
    Route::patch('/orders/{order}/items/{orderItem}/ship', [SellerController::class, 'shipItem'])->name('orders.items.ship');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::patch('/users/{user}/status', [AdminController::class, 'updateUserStatus'])->name('users.status');
    Route::patch('/products/{product}/status', [AdminController::class, 'updateProductStatus'])->name('products.status');
    Route::patch('/orders/{order}/payment', [AdminController::class, 'updatePaymentStatus'])->name('orders.payment');
});
