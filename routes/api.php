<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Member\AuthController as MemberAuthController;
use App\Http\Controllers\Api\Merchant\AuthController as MerchantAuthController;
use App\Http\Controllers\GitWebhookController;
use App\Http\Controllers\Api\Merchant\MerchantController;
use App\Http\Controllers\Api\Merchant\MerchantStaffController;
use App\Http\Controllers\Api\Member\MemberController;
use App\Http\Controllers\Api\Admin\AdminStaffController;
use App\Http\Controllers\Api\Admin\CompanyInfoController;
use App\Http\Controllers\Api\Admin\BusinessTypeController;
use App\Http\Controllers\Api\Admin\DenominationController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Member\ReferralController;
use App\Http\Controllers\Api\Member\VoucherController;
use App\Http\Controllers\Api\Admin\VoucherController as AdminVoucherController;
use App\Http\Controllers\Api\Admin\CpLevelConfigController;
use App\Http\Controllers\Api\Admin\TransactionController;
use App\Http\Controllers\Api\Admin\NotificationController;
use App\Http\Controllers\Api\Admin\WhatsAppLogController;


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
            Route::post('update', [CompanyInfoController::class, 'update']);
            Route::get('cr-points', [CompanyInfoController::class, 'getCrPoints']);
            Route::post('adjust-cr-points', [CompanyInfoController::class, 'adjustCrPoints']);
            Route::get('statistics', [CompanyInfoController::class, 'getStatistics']);
        });

        // Voucher Management (Admin only)
        Route::prefix('vouchers')->group(function () {
            Route::get('/', [AdminVoucherController::class, 'getAllVouchers']);
            Route::post('/{voucherId}/approve', [AdminVoucherController::class, 'approveVoucher']);
        });
    });
});



// Transaction Management (Admin only)
Route::prefix('transactions')->middleware('auth:admin,member')->group(function () {
    Route::get('/', [TransactionController::class, 'index']);
    Route::get('/all', [TransactionController::class, 'getAllTransactions']);
    Route::get('/{id}', [TransactionController::class, 'show']);
});

// Notification Management (Admin only)
Route::prefix('notifications')->middleware('auth:admin,member')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/all', [NotificationController::class, 'getAllNotifications']);
    Route::get('/{id}', [NotificationController::class, 'show']);
    Route::delete('/{id}', [NotificationController::class, 'destroy']);
});

// WhatsApp Message Log Management (Admin only)
Route::prefix('whatsapp-logs')->middleware('auth:admin,member')->group(function () {
    Route::get('/', [WhatsAppLogController::class, 'index']);
    Route::get('/all', [WhatsAppLogController::class, 'getAllLogs']);
    Route::get('/{id}', [WhatsAppLogController::class, 'show']);
    Route::delete('/{id}', [WhatsAppLogController::class, 'destroy']);
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
    Route::post('/{id}', [AdminStaffController::class, 'update']);

    // Delete admin staff
    Route::delete('/{id}', [AdminStaffController::class, 'destroy']);
});


/*
|--------------------------------------------------------------------------
| Business Type Routes
|--------------------------------------------------------------------------
*/
Route::prefix('business-types')->middleware('auth:admin')->group(function () {
    Route::post('/', [BusinessTypeController::class, 'store']);
    Route::get('/', [BusinessTypeController::class, 'index']);
    Route::get('/all', [BusinessTypeController::class, 'getAllBusinessTypes']);
    Route::get('/{id}', [BusinessTypeController::class, 'show']);
    Route::patch('/{id}', [BusinessTypeController::class, 'update']);
    Route::delete('/{id}', [BusinessTypeController::class, 'destroy']);
});


/*
|--------------------------------------------------------------------------
| Denomination Routes
|--------------------------------------------------------------------------
*/
Route::prefix('denominations')->middleware('auth:admin,merchant,member')->group(function () {
    Route::post('/', [DenominationController::class, 'store'])->middleware('role:admin');
    Route::get('/', [DenominationController::class, 'index'])->middleware('role:admin,merchant,member');
    Route::get('/all', [DenominationController::class, 'getAllDenominations'])->middleware('role:admin,merchant,member');
    Route::get('/{id}', [DenominationController::class, 'show'])->middleware('role:admin');
    Route::patch('/{id}', [DenominationController::class, 'update'])->middleware('role:admin');
    Route::delete('/{id}', [DenominationController::class, 'destroy'])->middleware('role:admin');
});


/*
|--------------------------------------------------------------------------
| Settings Routes
|--------------------------------------------------------------------------
*/
Route::prefix('settings')->middleware('auth:admin,merchant,member')->group(function () {
    Route::get('/', [SettingController::class, 'getSetting'])->middleware('role:admin,merchant,member');
    Route::post('/', [SettingController::class, 'upsertSetting'])->middleware('role:admin');
    Route::delete('/', [SettingController::class, 'deleteSetting'])->middleware('role:admin');
});


/*
|--------------------------------------------------------------------------
| Cp Level Config Routes
|--------------------------------------------------------------------------
*/ 

Route::prefix('cp-config')->middleware('auth:admin')->group(function () {
    Route::get('/', [CpLevelConfigController::class, 'index']);           
    Route::put('/bulk/update', [CpLevelConfigController::class, 'bulkUpdate']);
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

    // Update merchant - only admin or merchant can update
    Route::post('/{id}', [MerchantController::class, 'update'])->middleware('role:admin,merchant');

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



Route::prefix('member')->middleware(['auth:admin,member,merchant'])->group(function () {

    // Refer new member (Both General & Corporate Members)
    Route::post('/refer-new-member', [ReferralController::class, 'referNewMember']);

    // Get referral tree
    Route::get('/referral-tree', [ReferralController::class, 'getReferralTree'])->middleware('role:admin,member');

    // Get directly referred members
    Route::get('/referred-members', [ReferralController::class, 'getReferredMembers'])->middleware('role:admin,member');

    // Voucher routes
    Route::post('/voucher/create', [VoucherController::class, 'createVoucher'])->middleware('role:member');

    // Get all vouchers
    Route::get('/vouchers', [VoucherController::class, 'index'])->middleware('role:admin,member');

});


/*
|--------------------------------------------------------------------------
| Git Webhook Auto Deploy (GET Only)
|--------------------------------------------------------------------------
*/
Route::get('webhook/git-deploy', [GitWebhookController::class, 'autoDeploy'])
    ->name('webhook.git-deploy');
