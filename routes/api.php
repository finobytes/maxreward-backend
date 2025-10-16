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
use App\Http\Controllers\Api\Admin\AdminStaffController;
use App\Http\Controllers\Api\Admin\CompanyInfoController;


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

        // Company Info Management (Admin only)
        Route::prefix('company')->group(function () {
            Route::get('details', [CompanyInfoController::class, 'getFullDetails']);
            Route::put('update', [CompanyInfoController::class, 'update']);
            Route::get('cr-points', [CompanyInfoController::class, 'getCrPoints']);
            Route::post('adjust-cr-points', [CompanyInfoController::class, 'adjustCrPoints']);
            Route::get('statistics', [CompanyInfoController::class, 'getStatistics']);
        });
    });
});


/*
|--------------------------------------------------------------------------
| Admin Staff Routes (Public)
|--------------------------------------------------------------------------
*/
Route::prefix('admin-staffs')->middleware('auth:admin')->group(function () {
    // Create new admin staff
    Route::post('/', [AdminStaffController::class, 'store']);

    // Get all admin staffs (with optional filters and pagination)
    Route::get('/', [AdminStaffController::class, 'index']);

    // Get all admin staffs without pagination
    Route::get('/all', [AdminStaffController::class, 'getAllStaffs']);

    // Get single admin staff by ID
    Route::get('/{id}', [AdminStaffController::class, 'show']);

    // Update admin staff information
    Route::patch('/{id}', [AdminStaffController::class, 'update']);

    // Delete admin staff
    Route::delete('/{id}', [AdminStaffController::class, 'destroy']);
});


/*
|--------------------------------------------------------------------------
| Merchant Data Routes
|--------------------------------------------------------------------------
*/
Route::prefix('merchants')->middleware('auth:member,merchant,admin')->group(function () {
    // Create new merchant - only admin can create
    Route::post('/', [MerchantController::class, 'store'])->middleware('role:admin,member');

    // Get all merchants - members, merchants, and admins can view
    Route::get('/', [MerchantController::class, 'index'])->middleware('role:member,merchant,admin');

    // Get single merchant by ID - members, merchants, and admins can view
    Route::get('/{id}', [MerchantController::class, 'show'])->middleware('role:member,merchant,admin');

    // Update merchant - only admin can update
    Route::patch('/{id}', [MerchantController::class, 'update'])->middleware('role:admin,merchant');

    // Delete merchant - only admin can delete
    Route::delete('/{id}', [MerchantController::class, 'destroy'])->middleware('role:admin');

    // Get merchant by unique number - members, merchants, and admins can view
    Route::get('/unique/{uniqueNumber}', [MerchantController::class, 'getByUniqueNumber'])->middleware('role:member,merchant,admin');
});


/*
|--------------------------------------------------------------------------
| Merchant Staff Routes
|--------------------------------------------------------------------------
*/
Route::prefix('staffs')->middleware('auth:merchant,admin')->group(function () {
    // Create new staff
    Route::post('/', [MerchantStaffController::class, 'store'])->middleware('role:merchant');

    // Get all staffs (with optional filters)
    Route::get('/', [MerchantStaffController::class, 'index'])->middleware('role:merchant,admin');

    // Get single staff by ID
    Route::get('/{id}', [MerchantStaffController::class, 'show'])->middleware('role:merchant,admin');

    // Update staff information
    Route::patch('/{id}', [MerchantStaffController::class, 'update'])->middleware('role:merchant');

    // Delete staff
    Route::delete('/{id}', [MerchantStaffController::class, 'destroy'])->middleware('role:merchant');

    // Get all staffs by merchant ID
    Route::get('/merchant/{merchantId}', [MerchantStaffController::class, 'getByMerchant'])->middleware('role:merchant,admin');
});


/*
|--------------------------------------------------------------------------
| Member Data Routes (Public)
|--------------------------------------------------------------------------
*/
Route::prefix('members')->middleware('auth:member,admin')->group(function () {
    // Get all members (with optional filters)
    Route::get('/', [MemberController::class, 'index'])->middleware('role:admin');

    // Get only general members
    Route::get('/general', [MemberController::class, 'getGeneralMembers'])->middleware('role:admin');

    // Get only corporate members
    Route::get('/corporate', [MemberController::class, 'getCorporateMembers'])->middleware('role:admin');

    // Get single member by ID
    Route::get('/{id}', [MemberController::class, 'show'])->middleware('role:admin,member');

    // Get member by username
    Route::get('/username/{username}', [MemberController::class, 'getByUsername'])->middleware('role:admin,member');

    // Get member by referral code
    Route::get('/referral/{referralCode}', [MemberController::class, 'getByReferralCode'])->middleware('role:admin,member');

    // Update member information
    Route::patch('/{id}', [MemberController::class, 'update'])->middleware('role:admin,member');
});




/*
|--------------------------------------------------------------------------
| Git Webhook Route (No CSRF, No Auth)
|--------------------------------------------------------------------------
*/
// Route::get('webhook/git-deploy', [GitWebhookController::class, 'handle'])
//     ->name('webhook.git-deploy');