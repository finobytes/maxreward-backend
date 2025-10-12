<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Member\AuthController as MemberAuthController;
use App\Http\Controllers\Api\Merchant\AuthController as MerchantAuthController;
// use App\Http\Controllers\GitWebhookController;
use App\Http\Controllers\Api\Merchant\MerchantController;


Route::prefix('member')->group(function () {
    Route::post('login', [MemberAuthController::class, 'login']);
    Route::post('logout', [MemberAuthController::class, 'logout']);
    Route::post('refresh', [MemberAuthController::class, 'refresh']);
    Route::post('me', [MemberAuthController::class, 'me']);
});


/*
|--------------------------------------------------------------------------
| Merchant Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('merchant')->group(function () {
    // Public route - login for both Merchant Owner and Staff
    Route::post('login', [MerchantAuthController::class, 'login']);
    
    // Protected routes - require JWT authentication
    Route::middleware('auth:merchant')->group(function () {
        Route::post('me', [MerchantAuthController::class, 'me']);
        Route::post('logout', [MerchantAuthController::class, 'logout']);
        Route::post('refresh', [MerchantAuthController::class, 'refresh']);
    });
});


/*
|--------------------------------------------------------------------------
| Merchant Data Routes (Public)
|--------------------------------------------------------------------------
*/
Route::prefix('merchants')->group(function () {
    // Create new merchant with corporate member, wallets, and staffs
    Route::post('/', [MerchantController::class, 'store']);

    // Get all merchants (with optional filters)
    Route::get('/', [MerchantController::class, 'index']);

    // Get single merchant by ID
    Route::get('/{id}', [MerchantController::class, 'show']);

    // Get merchant by unique number
    Route::get('/unique/{uniqueNumber}', [MerchantController::class, 'getByUniqueNumber']);
});



Route::prefix('admin')->group(function () {
    Route::post('login', [AdminAuthController::class, 'login']);
    Route::post('logout', [AdminAuthController::class, 'logout']);
    Route::post('refresh', [AdminAuthController::class, 'refresh']);
    Route::post('me', [AdminAuthController::class, 'me']);
});


/*
|--------------------------------------------------------------------------
| Git Webhook Route (No CSRF, No Auth)
|--------------------------------------------------------------------------
*/
// Route::get('webhook/git-deploy', [GitWebhookController::class, 'handle'])
//     ->name('webhook.git-deploy');