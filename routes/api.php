<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Member\AuthController as MemberAuthController;
use App\Http\Controllers\Api\Merchant\AuthController as MerchantAuthController;
// use App\Http\Controllers\GitWebhookController;
use App\Http\Controllers\Api\Merchant\MerchantController;
use App\Http\Controllers\Api\Merchant\StaffController as MerchantStaffController;
use App\Http\Controllers\Api\Member\MemberController;


/*
|--------------------------------------------------------------------------
| Member Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('member')->group(function () {
    Route::post('login', [MemberAuthController::class, 'login']);

    // Protected routes - require JWT authentication
    Route::middleware('auth:member')->group(function () {
        Route::post('logout', [MemberAuthController::class, 'logout']);
        Route::post('refresh', [MemberAuthController::class, 'refresh']);
        Route::post('me', [MemberAuthController::class, 'me']);
    });
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
| Admin Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    // Public route - login for both Admin and Staff
    Route::post('login', [AdminAuthController::class, 'login']);

    // Protected routes - require JWT authentication
    Route::middleware('auth:admin')->group(function () {
        Route::post('logout', [AdminAuthController::class, 'logout']);
        Route::post('refresh', [AdminAuthController::class, 'refresh']);
        Route::post('me', [AdminAuthController::class, 'me']);
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

    // Update merchant and staff information (partial update)
    Route::patch('/{id}', [MerchantController::class, 'update']);

    // Delete merchant and all related data
    Route::delete('/{id}', [MerchantController::class, 'destroy']);

    // Get merchant by unique number
    Route::get('/unique/{uniqueNumber}', [MerchantController::class, 'getByUniqueNumber']);
});


/*
|--------------------------------------------------------------------------
| Staff Routes (Public)
|--------------------------------------------------------------------------
*/
Route::prefix('staffs')->group(function () {
    // Create new staff
    Route::post('/', [MerchantStaffController::class, 'store']);

    // Get all staffs (with optional filters)
    Route::get('/', [MerchantStaffController::class, 'index']);

    // Get single staff by ID
    Route::get('/{id}', [MerchantStaffController::class, 'show']);

    // Update staff information
    Route::patch('/{id}', [MerchantStaffController::class, 'update']);

    // Delete staff
    Route::delete('/{id}', [MerchantStaffController::class, 'destroy']);

    // Get all staffs by merchant ID
    Route::get('/merchant/{merchantId}', [MerchantStaffController::class, 'getByMerchant']);
});


/*
|--------------------------------------------------------------------------
| Member Data Routes (Public)
|--------------------------------------------------------------------------
*/
Route::prefix('members')->group(function () {
    // Get all members (with optional filters)
    Route::get('/', [MemberController::class, 'index']);

    // Get only general members
    Route::get('/general', [MemberController::class, 'getGeneralMembers']);

    // Get only corporate members
    Route::get('/corporate', [MemberController::class, 'getCorporateMembers']);

    // Get single member by ID
    Route::get('/{id}', [MemberController::class, 'show']);

    // Get member by username
    Route::get('/username/{username}', [MemberController::class, 'getByUsername']);

    // Get member by referral code
    Route::get('/referral/{referralCode}', [MemberController::class, 'getByReferralCode']);

    // Update member information
    Route::patch('/{id}', [MemberController::class, 'update']);
});

/*
|--------------------------------------------------------------------------
| Git Webhook Route (No CSRF, No Auth)
|--------------------------------------------------------------------------
*/
// Route::get('webhook/git-deploy', [GitWebhookController::class, 'handle'])
//     ->name('webhook.git-deploy');