<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Member\AuthController;
use App\Http\Controllers\Api\Merchant\AuthController as MerchantAuthController;


Route::prefix('member')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('me', [AuthController::class, 'me']);
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
        Route::get('me', [MerchantAuthController::class, 'me']);
        Route::post('logout', [MerchantAuthController::class, 'logout']);
        Route::post('refresh', [MerchantAuthController::class, 'refresh']);
    });
});