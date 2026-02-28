<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\RoleController;
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
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\SubCategoryController;
use App\Http\Controllers\Api\Admin\ModelController;
use App\Http\Controllers\Api\Admin\BrandController;
use App\Http\Controllers\Api\Admin\SectionController;
use App\Http\Controllers\Api\Admin\ActionController;
use App\Http\Controllers\Api\Merchant\ProductController;
use App\Http\Controllers\Api\Admin\GenderController;
use App\Http\Controllers\Api\Admin\AttributeController;
use App\Http\Controllers\Api\Admin\AttributeItemController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Member\ReferralController;
use App\Http\Controllers\Api\Member\VoucherController;
use App\Http\Controllers\Api\Admin\VoucherController as AdminVoucherController;
use App\Http\Controllers\Api\Admin\CpLevelConfigController;
use App\Http\Controllers\Api\Admin\TransactionController;
use App\Http\Controllers\Api\Admin\NotificationController;
use App\Http\Controllers\Api\Admin\WhatsAppLogController;
use App\Http\Controllers\Api\Admin\EmailLogController;
use App\Http\Controllers\Api\Admin\CountryController;
use App\Http\Controllers\Api\Admin\CpTransactionController as AdminCpTransactionController;
use App\Http\Controllers\Api\Member\CpTransactionController as MemberCpTransactionController;
use App\Http\Controllers\Api\Merchant\CpTransactionController as MerchantCpTransactionController;
use App\Http\Controllers\Api\Admin\CpTransactionController as CpDistributionPoolController;
use App\Http\Controllers\Api\Admin\MemberCommunityPointController as AdminMemberCommunityPointController;
use App\Http\Controllers\Api\Member\MemberCommunityPointController as MemberMemberCommunityPointController;
use App\Http\Controllers\Api\Merchant\MemberCommunityPointController as MerchantMemberCommunityPointController;
use App\Http\Controllers\Api\Admin\CpUnlockHistoryController as AdminCpUnlockHistoryController;
use App\Http\Controllers\Api\Member\CpUnlockHistoryController as MemberCpUnlockHistoryController;
use App\Http\Controllers\Api\Merchant\CpUnlockHistoryController as MerchantCpUnlockHistoryController;
use App\Http\Controllers\Api\Member\CartController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\Merchant\MerchantShippingRateController;
use App\Http\Controllers\Api\Admin\ShippingZoneController;
use App\Http\Controllers\Api\Admin\ShippingMethodController;
use App\Http\Controllers\Api\Merchant\OrderExchangeController;
use App\Http\Controllers\Api\OrderAutoCompleteController;
use App\Http\Controllers\Api\StripeWebhookController; // NEW: Stripe Webhook Controller


/*
|--------------------------------------------------------------------------
| Stripe Webhook Route (Public - No Authentication Required)
|--------------------------------------------------------------------------
| This must be placed BEFORE any auth middleware routes
| Stripe will call this endpoint to notify payment status
*/
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);


/*
|--------------------------------------------------------------------------
| Member Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('member')->group(function () {
    Route::post('login', [MemberAuthController::class, 'login']);

    // Get member by username by qr code
    Route::get('/username/{username}', [MemberController::class, 'getByUsername']);
    // Refer new member (Both General & Corporate Members)
    Route::post('/refer-new-member-qr', [ReferralController::class, 'referNewMember']);

    // Protected routes - require JWT authentication
    Route::middleware(['auth:member', 'member.status'])->group(function () {
        Route::post('logout', [MemberAuthController::class, 'logout']);
        Route::post('refresh', [MemberAuthController::class, 'refresh']);
        Route::post('me', [MemberAuthController::class, 'me']);
        Route::post('update-profile', [MemberController::class, 'updateProfile']);
        Route::post('change-password', [MemberController::class, 'changePassword']);

        // Notifications count
        Route::get('notifications/count', [MemberController::class, 'getNotificationsCount']);

        // CP Transaction routes for Member
        Route::prefix('cp-transactions')->group(function () {
            Route::get('/', [MemberCpTransactionController::class, 'index']);
            Route::get('/{id}', [MemberCpTransactionController::class, 'show']);
        });

        // Member Community Points routes for Member
        Route::prefix('community-points')->group(function () {
            Route::get('/', [MemberMemberCommunityPointController::class, 'index']);
            Route::get('/{id}', [MemberMemberCommunityPointController::class, 'show']);
        });

        // CP Unlock History routes for Member
        Route::prefix('unlock-history')->group(function () {
            Route::get('/', [MemberCpUnlockHistoryController::class, 'index']);
            Route::get('/{id}', [MemberCpUnlockHistoryController::class, 'show']);
        });

        // Cart Routes
        Route::prefix('cart')->group(function () {
            Route::get('/', [CartController::class, 'index']);
            Route::post('/', [CartController::class, 'store']);
            Route::put('/{id}', [CartController::class, 'update']);
            Route::delete('/{id}', [CartController::class, 'destroy']);
            Route::delete('/', [CartController::class, 'clear']);
            Route::get('/count', [CartController::class, 'count']);
        });
        // member calculate shipping
        Route::post('calculate-shipping', [OrderController::class, 'calculateShipping']);

    });
});

/*
|--------------------------------------------------------------------------
| Forgot Password Routes (Public)
|--------------------------------------------------------------------------
*/
Route::prefix('forgot-password')->group(function () {
    Route::post('request', [ForgotPasswordController::class, 'sendCode']);
    Route::post('verify', [ForgotPasswordController::class, 'verifyCode']);
    Route::post('reset', [ForgotPasswordController::class, 'resetPassword']);
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
        Route::post('change-password', [MerchantController::class, 'changePassword']);

        // Notifications count
        Route::get('notifications/count', [MerchantController::class, 'getNotificationsCount']);

        // CP Transaction routes for Merchant
        Route::prefix('cp-transactions')->group(function () {
            Route::get('/', [MerchantCpTransactionController::class, 'index']);
            Route::get('/{id}', [MerchantCpTransactionController::class, 'show']);
        });

        // Member Community Points routes for Merchant
        Route::prefix('community-points')->group(function () {
            Route::get('/', [MerchantMemberCommunityPointController::class, 'index']);
            Route::get('/{id}', [MerchantMemberCommunityPointController::class, 'show']);
        });

        // CP Unlock History routes for Merchant
        Route::prefix('unlock-history')->group(function () {
            Route::get('/', [MerchantCpUnlockHistoryController::class, 'index']);
            Route::get('/{id}', [MerchantCpUnlockHistoryController::class, 'show']);
        });

        /*
        |--------------------------------------------------------------------------
        | Merchant Shipping Rates
        |--------------------------------------------------------------------------
        */

        Route::prefix('shipping-rates')->group(function () {
            //Get merchant's shipping rates
            Route::get('/', [MerchantShippingRateController::class, 'index']);
            //Get available zones and methods for setup
            Route::get('/options', [MerchantShippingRateController::class, 'getOptions']);
            //Get single shipping rate
            Route::get('/{id}', [MerchantShippingRateController::class, 'show']);
            //Create new shipping rate
            Route::post('/', [MerchantShippingRateController::class, 'store']);
            //Update shipping rate
            Route::put('/{id}', [MerchantShippingRateController::class, 'update']);
            //Delete shipping rate
            Route::delete('/{id}', [MerchantShippingRateController::class, 'destroy']);
            //Toggle rate status
            Route::patch('/{id}/toggle-status', [MerchantShippingRateController::class, 'toggleStatus']);
            
            //Bulk create shipping rates for all zones
            Route::post('/bulk-create', [MerchantShippingRateController::class, 'bulkCreate']);
            //Delete all rates for a specific zone/method combination
            Route::delete('/bulk-delete', [MerchantShippingRateController::class, 'bulkDelete']);
        });

    });
});


/*
|--------------------------------------------------------------------------
| Shared Role Management Routes (Admin & Merchant)
|--------------------------------------------------------------------------
*/
Route::prefix('roles')->middleware('auth:admin,merchant')->group(function () {
    // Assign role to merchant - accessible by both admin and merchant
    // Route::post('assign-merchant', [RoleController::class, 'assignRoleToMerchant']);
});

// Get active shipping methods (for public use)
Route::get('shipping-methods/active', [ShippingMethodController::class, 'getActiveMethods'])->middleware('auth:admin,merchant,member');

// Detect zone by postcode (for public use)
Route::get('shipping-zones/detect', [ShippingZoneController::class, 'detectByPostcode'])->middleware('auth:admin,merchant,member');

/*
|--------------------------------------------------------------------------
| Admin Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    // Public route - login for both Admin and Staff
    Route::post('login', [AdminAuthController::class, 'login']);

    // Get dashboard statistics
    Route::get('/dashboard-stats', [DashboardController::class, 'getDashboardStats'])->middleware('auth:admin');
    Route::get('/voucher-purchase-stats', [DashboardController::class, 'getVoucherPurchaseStats'])->middleware('auth:admin');
    Route::get('/real-time-transactions', [DashboardController::class, 'getRealTimeTransactions'])->middleware('auth:admin');

    // Protected routes - require JWT authentication
    Route::middleware('auth:admin')->group(function () {
        Route::post('logout', [AdminAuthController::class, 'logout']);
        Route::post('refresh', [AdminAuthController::class, 'refresh']);
        Route::post('me', [AdminAuthController::class, 'me']);
        Route::post('change-password', [AdminStaffController::class, 'changePassword']);

        // Role Management Routes (Admin only)
        Route::prefix('roles')->group(function () {
            // CRUD Operations
            Route::post('create', [RoleController::class, 'createRole'])
                ->withoutMiddleware(['auth:admin'])
                ->middleware('auth:admin,merchant');
            Route::put('update/{id}', [RoleController::class, 'updateRole'])
                ->withoutMiddleware(['auth:admin'])
                ->middleware('auth:admin,merchant');
            Route::delete('delete/{id}', [RoleController::class, 'deleteRole'])
                ->withoutMiddleware(['auth:admin'])
                ->middleware('auth:admin,merchant');
            Route::get('/', [RoleController::class, 'getAllRoles'])
                ->withoutMiddleware(['auth:admin'])
                ->middleware('auth:admin,merchant');

            // Permissions Management (MUST be before /{id} route)
            Route::get('permissions', [RoleController::class, 'getAllPermissions'])
                ->withoutMiddleware(['auth:admin'])
                ->middleware('auth:admin,merchant');
            Route::post('permissions/create', [RoleController::class, 'createPermission']);
            Route::put('permissions/update/{id}', [RoleController::class, 'updatePermission']);
            Route::delete('permissions/delete/{id}', [RoleController::class, 'deletePermission']);
            Route::post('permissions/clear-cache', [RoleController::class, 'clearPermissionCache']);
            Route::post('user-permissions', [RoleController::class, 'getUserRolesAndPermissions']);

            // Get single role (MUST be after specific routes)
            Route::get('/{id}', [RoleController::class, 'getRole']);

            // Assign/Remove Roles
            Route::post('assign-admin', [RoleController::class, 'assignRoleToAdmin']);
            Route::post('assign-member', [RoleController::class, 'assignRoleToMember']);
            Route::post('remove-admin', [RoleController::class, 'removeRoleFromAdmin']);
            Route::post('remove-merchant', [RoleController::class, 'removeRoleFromMerchant'])
                ->withoutMiddleware(['auth:admin'])
                ->middleware('auth:admin,merchant');
            Route::post('remove-member', [RoleController::class, 'removeRoleFromMember']);

            // Role Permissions
            Route::post('{id}/assign-permissions', [RoleController::class, 'assignPermissionsToRole'])
                ->withoutMiddleware(['auth:admin'])
                ->middleware('auth:admin,merchant');
            Route::post('{id}/remove-permissions', [RoleController::class, 'removePermissionsFromRole']);

             Route::post('assign-merchant', [RoleController::class, 'assignRoleToMerchant'])
                ->withoutMiddleware(['auth:admin'])
                ->middleware('auth:admin,merchant');

            // Direct Permissions (for merchant staff)
            Route::post('/staff/assign-direct-permissions', [RoleController::class, 'assignDirectPermissionsToStaff']);
            Route::post('/staff/remove-direct-permissions', [RoleController::class, 'removeDirectPermissionsFromStaff']);
        });

        Route::post('/status/block-suspend', [MemberController::class, 'statusBlockSuspend'])->middleware('role:admin,staff');
        Route::post('/merchant-suspend', [MerchantController::class, 'suspendMerchant'])->middleware('role:admin,staff');
        Route::post('/merchant-unsuspend', [MerchantController::class, 'unsuspendMerchant'])->middleware('role:admin,staff');
        Route::post('/merchant-rejected', [MerchantController::class, 'rejectMerchant'])->middleware('role:admin,staff');
//
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
            Route::post('/{voucherId}/reject', [AdminVoucherController::class, 'rejectVoucher']);
            Route::post('/{voucherId}/status-change', [AdminVoucherController::class, 'changeVoucherStatus']);
            Route::get('/{voucherId}', [AdminVoucherController::class, 'getVoucher']);
            // Get voucher statistics
            Route::get('/statistics/overview', [AdminVoucherController::class, 'getStatistics']);
            // Get pending vouchers count
            Route::get('/pending/count', [AdminVoucherController::class, 'getPendingCount']);
        });

        // CP Transaction Management (Admin only)
        Route::prefix('cp-transactions')->group(function () {
            Route::get('/', [AdminCpTransactionController::class, 'index']);
            Route::get('/{id}', [AdminCpTransactionController::class, 'show']);
        });

        // Member Community Points Management (Admin only)
        Route::prefix('community-points')->group(function () {
            Route::get('/', [AdminMemberCommunityPointController::class, 'index']);
            Route::get('/member/{memberId}', [AdminMemberCommunityPointController::class, 'getMemberPoints']);
            Route::get('/{id}', [AdminMemberCommunityPointController::class, 'show']);
        });

        // CP Unlock History Management (Admin only)
        Route::prefix('unlock-history')->group(function () {
            Route::get('/', [AdminCpUnlockHistoryController::class, 'index']);
            Route::get('/{id}', [AdminCpUnlockHistoryController::class, 'show']);
        });

        // CP Distribution pool 
        Route::get('get-cp-distribution-pool', [CpDistributionPoolController::class, 'getCpDistributionPool']);
        Route::get('get-single-cp-distribution-pool-{id}', [CpDistributionPoolController::class, 'getSingleCpDistributionPool']);

        Route::get('get-all-merchants-purchases-data', [MerchantController::class, 'getAllMerchantsPurchasesData']);

        // Shipping Zones Management
        Route::get('shipping-zones', [ShippingZoneController::class, 'index']);
        Route::get('shipping-zones/{id}', [ShippingZoneController::class, 'show']);
        Route::post('shipping-zones', [ShippingZoneController::class, 'store']);
        Route::put('shipping-zones/{id}', [ShippingZoneController::class, 'update']);
        Route::delete('shipping-zones/{id}', [ShippingZoneController::class, 'destroy']);
        Route::patch('shipping-zones/{id}/toggle-status', [ShippingZoneController::class, 'toggleStatus']);
        Route::get('shipping-zones/regions', [ShippingZoneController::class, 'getRegions']);

        // Shipping Methods Management
        Route::get('shipping-methods', [ShippingMethodController::class, 'index']);
        Route::get('shipping-methods/{id}', [ShippingMethodController::class, 'show']);
        Route::post('shipping-methods', [ShippingMethodController::class, 'store']);
        Route::put('shipping-methods/{id}', [ShippingMethodController::class, 'update']);
        Route::delete('shipping-methods/{id}', [ShippingMethodController::class, 'destroy']);
        Route::patch('shipping-methods/{id}/toggle-status', [ShippingMethodController::class, 'toggleStatus']);
        Route::post('shipping-methods/reorder', [ShippingMethodController::class, 'reorder']);

        // Get all eligible orders across all merchants
        Route::get('/orders/auto-complete/eligible', [OrderAutoCompleteController::class, 'adminGetAllEligibleOrders']);
    
        // Trigger auto-completion for all merchants
        Route::post('/orders/auto-complete/all', [OrderAutoCompleteController::class, 'adminAutoCompleteAll']);
        
    });

});



// Transaction Management (Admin only)
Route::prefix('transactions')->middleware('auth:admin,member,merchant')->group(function () {
    Route::get('/', [TransactionController::class, 'index'])->middleware('role:admin,staff');
    Route::get('/all', [TransactionController::class, 'getAllTransactions'])->middleware('role:admin,staff');
    Route::get('/{id}', [TransactionController::class, 'show'])->middleware('role:admin,member,staff');
    Route::get('/{id}/member/available/transactions', [TransactionController::class, 'getMemberAvailableTransactions'])->middleware('role:member,admin,merchant,staff');
    Route::get('/{id}/member/refer/transactions', [TransactionController::class, 'getMemberReferTransactions'])->middleware('role:member,admin,merchant,staff');
});

// Notification Management
Route::prefix('notifications')->middleware('auth:admin,member,merchant')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->middleware('role:admin,staff');
    Route::get('/all', [NotificationController::class, 'getAllNotifications'])->middleware('role:admin,staff');
    Route::get('/member/all', [NotificationController::class, 'getMemberNotifications'])->middleware('role:member');
    Route::get('/{id}/read', [NotificationController::class, 'readSingleMemberNotification'])->middleware('role:member,merchant,admin,staff');
    Route::get('/merchant/all', [NotificationController::class, 'getMerchantNotifications'])->middleware('role:merchant,staff');
    // Route::get('/merchant/{id}/read', [NotificationController::class, 'readSingleMerchnatNotification'])->middleware('role:merchant');
    Route::post('/admin/save-count', [NotificationController::class, 'saveAdminNotificationSaveCount'])->middleware('role:admin,staff');
    Route::post('/member/save-count', [NotificationController::class, 'saveMemberNotificationSaveCount'])->middleware('role:member');
    Route::post('/merchant/save-count', [NotificationController::class, 'saveMerchantNotificationSaveCount'])->middleware('role:merchant,staff');
    Route::get('/{id}', [NotificationController::class, 'show'])->middleware('role:admin,member,merchant');
    Route::delete('/{id}', [NotificationController::class, 'destroy'])->middleware('role:admin,staff');
});

// WhatsApp Message Log Management (Admin only)
Route::prefix('whatsapp-logs')->middleware('auth:admin')->group(function () {
    Route::get('/', [WhatsAppLogController::class, 'index']);
    Route::get('/all', [WhatsAppLogController::class, 'getAllLogs']);
    Route::get('/{id}', [WhatsAppLogController::class, 'show']);
    Route::delete('/{id}', [WhatsAppLogController::class, 'destroy']);
});

// Email Message Log Management (Admin only)
Route::prefix('email-logs')->middleware('auth:admin')->group(function () {
    Route::get('/', [EmailLogController::class, 'index']);
    Route::get('/all', [EmailLogController::class, 'getAllLogs']);
    Route::get('/statistics', [EmailLogController::class, 'getStatistics']);
    Route::get('/{id}', [EmailLogController::class, 'show']);
    Route::post('/{id}/retry', [EmailLogController::class, 'retry']);
    Route::delete('/{id}', [EmailLogController::class, 'destroy']);
});



Route::post('/countries/fetch-and-save', [CountryController::class, 'fetchAndSaveCountries']);
Route::get('/countries', [CountryController::class, 'getAllCountries']);


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
Route::prefix('business-types')->middleware('auth:admin,member')->group(function () {
    Route::post('/', [BusinessTypeController::class, 'store'])->middleware('role:admin,staff');
    Route::get('/', [BusinessTypeController::class, 'index'])->middleware('role:admin,member,staff');
    Route::get('/all', [BusinessTypeController::class, 'getAllBusinessTypes'])->middleware('role:admin,member,staff');
    Route::get('/{id}', [BusinessTypeController::class, 'show'])->middleware('role:admin,staff');
    Route::patch('/{id}', [BusinessTypeController::class, 'update'])->middleware('role:admin,staff');
    Route::delete('/{id}', [BusinessTypeController::class, 'destroy'])->middleware('role:admin,staff');
});


/*
|--------------------------------------------------------------------------
| Denomination Routes
|--------------------------------------------------------------------------
*/
Route::prefix('denominations')->middleware('auth:admin,merchant,member')->group(function () {
    Route::post('/', [DenominationController::class, 'store'])->middleware('role:admin,staff');
    Route::get('/', [DenominationController::class, 'index'])->middleware('role:admin,merchant,member,staff');
    Route::get('/all', [DenominationController::class, 'getAllDenominations'])->middleware('role:admin,merchant,member,staff');
    Route::get('/{id}', [DenominationController::class, 'show'])->middleware('role:admin,staff');
    Route::patch('/{id}', [DenominationController::class, 'update'])->middleware('role:admin,staff');
    Route::delete('/{id}', [DenominationController::class, 'destroy'])->middleware('role:admin,staff');
});


/*
|--------------------------------------------------------------------------
| Category Routes
|--------------------------------------------------------------------------
*/
Route::prefix('categories')->middleware('auth:admin,merchant,member')->group(function () {
    Route::post('/', [CategoryController::class, 'store'])->middleware('role:admin,staff');
    Route::get('/', [CategoryController::class, 'index'])->middleware('role:admin,staff,merchant,member');
    Route::get('/all', [CategoryController::class, 'getAllCategories'])->middleware('role:admin,staff,merchant,member');
    Route::get('/{id}', [CategoryController::class, 'show'])->middleware('role:admin,staff');
    Route::post('/{id}', [CategoryController::class, 'update'])->middleware('role:admin,staff');
    Route::delete('/{id}', [CategoryController::class, 'destroy'])->middleware('role:admin,staff');
});


/*
|--------------------------------------------------------------------------
| Sub-Category Routes
|--------------------------------------------------------------------------
*/
Route::prefix('sub-categories')->middleware('auth:admin,merchant')->group(function () {
    Route::post('/', [SubCategoryController::class, 'store'])->middleware('role:admin,staff');
    Route::get('/', [SubCategoryController::class, 'index'])->middleware('role:admin,merchant,member,staff');
    Route::get('/all', [SubCategoryController::class, 'getAllSubCategories'])->middleware('role:admin,merchant,member,staff');
    Route::get('/{id}', [SubCategoryController::class, 'show'])->middleware('role:admin,staff');
    Route::post('/{id}', [SubCategoryController::class, 'update'])->middleware('role:admin,staff');
    Route::delete('/{id}', [SubCategoryController::class, 'destroy'])->middleware('role:admin,staff');
});


/*
|--------------------------------------------------------------------------
| Section Routes
|--------------------------------------------------------------------------
*/
Route::prefix('sections')->middleware('auth:admin')->group(function () {
    Route::post('/', [SectionController::class, 'store'])->middleware('role:admin,staff');
    Route::get('/', [SectionController::class, 'index'])->middleware('role:admin,staff');
    Route::get('/all', [SectionController::class, 'getAllSections'])->middleware('role:admin,staff');
    Route::get('/{id}', [SectionController::class, 'show'])->middleware('role:admin,staff');
    Route::patch('/{id}', [SectionController::class, 'update'])->middleware('role:admin,staff');
    Route::delete('/{id}', [SectionController::class, 'destroy'])->middleware('role:admin,staff');
});


/*
|--------------------------------------------------------------------------
| Action Routes
|--------------------------------------------------------------------------
*/
Route::prefix('actions')->middleware('auth:admin')->group(function () {
    Route::post('/', [ActionController::class, 'store'])->middleware('role:admin,staff');
    Route::get('/', [ActionController::class, 'index'])->middleware('role:admin,staff');
    Route::get('/all', [ActionController::class, 'getAllActions'])->middleware('role:admin,staff');
    Route::get('/{id}', [ActionController::class, 'show'])->middleware('role:admin,staff');
    Route::patch('/{id}', [ActionController::class, 'update'])->middleware('role:admin,staff');
    Route::delete('/{id}', [ActionController::class, 'destroy'])->middleware('role:admin,staff');
});


/*
|--------------------------------------------------------------------------
| Model Routes
|--------------------------------------------------------------------------
*/
// Route::prefix('models')->middleware('auth:admin,merchant')->group(function () {
//     Route::post('/', [ModelController::class, 'store'])->middleware('role:admin');
//     Route::get('/', [ModelController::class, 'index'])->middleware('role:admin,merchant,member');
//     Route::get('/all', [ModelController::class, 'getAllModels'])->middleware('role:admin,merchant,member');
//     Route::get('/{id}', [ModelController::class, 'show'])->middleware('role:admin');
//     Route::post('/{id}', [ModelController::class, 'update'])->middleware('role:admin');
//     Route::delete('/{id}', [ModelController::class, 'destroy'])->middleware('role:admin');
// });

Route::prefix('models')->middleware('auth:admin,merchant')->group(function () {
    Route::post('/', [ModelController::class, 'store'])->middleware('role:admin,staff');
    Route::get('/', [ModelController::class, 'index'])->middleware('role:admin,merchant,member,staff');
    Route::get('/all', [ModelController::class, 'getAllModels'])->middleware('role:admin,merchant,member,staff');
    Route::get('/{id}', [ModelController::class, 'show'])->middleware('role:admin,staff');
    Route::post('/{id}', [ModelController::class, 'update'])->middleware('role:admin,staff');
    Route::delete('/{id}', [ModelController::class, 'destroy'])->middleware('role:admin,staff');
});

/*
| Brand Routes
|--------------------------------------------------------------------------
*/
// Route::prefix('brands')->middleware('auth:admin,merchant')->group(function () {
//     Route::post('/', [BrandController::class, 'store'])->middleware('role:admin');
//     Route::get('/', [BrandController::class, 'index'])->middleware('role:admin,merchant,member');
//     Route::get('/all', [BrandController::class, 'getAllBrands'])->middleware('role:admin,merchant,member');
//     Route::get('/{id}', [BrandController::class, 'show'])->middleware('role:admin');
//     Route::post('/{id}', [BrandController::class, 'update'])->middleware('role:admin');
//     Route::delete('/{id}', [BrandController::class, 'destroy'])->middleware('role:admin');
// });


Route::prefix('brands')->middleware('auth:admin,merchant')->group(function () {
    Route::post('/', [BrandController::class, 'store'])->middleware('role:admin,staff');
    Route::get('/', [BrandController::class, 'index'])->middleware('role:admin,merchant,member,staff');
    Route::get('/all', [BrandController::class, 'getAllBrands'])->middleware('role:admin,merchant,member,staff');
    Route::get('/{id}', [BrandController::class, 'show'])->middleware('role:admin,staff');
    Route::post('/{id}', [BrandController::class, 'update'])->middleware('role:admin,staff');
    Route::delete('/{id}', [BrandController::class, 'destroy'])->middleware('role:admin,staff');
});


/*
|--------------------------------------------------------------------------
| Product Routes (WITH PERMISSION SYSTEM)
|--------------------------------------------------------------------------
*/
// Route::prefix('products')->middleware('auth:admin,merchant,member')->group(function () {
//     // View products - owner, manager, staff, sales can view
//     Route::get('/', [ProductController::class, 'index'])->middleware('permission:product.index');
//     Route::get('/{id}', [ProductController::class, 'show'])->middleware('permission:product.view');

//     // Create product - only owner, manager can create
//     Route::post('/', [ProductController::class, 'store'])->middleware('permission:product.create');

//     // Update product - only owner, manager can edit
//     Route::put('/{id}', [ProductController::class, 'update'])->middleware('permission:product.edit');

//     // Delete product - only owner can delete
//     Route::delete('/{id}', [ProductController::class, 'destroy'])->middleware('permission:product.delete');

//     // Variation Helpers - only owner, manager can use
//     Route::post('generate-variations', [ProductController::class, 'generateVariations'])->middleware('permission:product.create');
//     Route::post('validate-sku', [ProductController::class, 'validateSku'])->middleware('permission:product.create');
// });

Route::prefix('products')->middleware('auth:admin,merchant,member')->group(function () {
    // Variation Helpers
    Route::post('generate-variations', [ProductController::class, 'generateVariations'])->middleware('role:merchant,staff');
    Route::post('validate-sku', [ProductController::class, 'validateSku'])->middleware('role:merchant,staff');
    
    Route::get('/', [ProductController::class, 'index'])->middleware('role:admin,member,staff');
     Route::get('/merchant/{id}', [ProductController::class, 'merchantIndex'])->middleware('role:admin,merchant,member,staff');
    Route::get('/{id}', [ProductController::class, 'show'])->middleware('role:admin,merchant,member,staff');
    Route::post('/', [ProductController::class, 'store'])->middleware('role:merchant,staff');
    Route::post('/{id}', [ProductController::class, 'update'])->middleware('role:merchant,staff');
    Route::delete('/{id}', [ProductController::class, 'destroy'])->middleware('role:merchant,staff');
    Route::patch('/status/update/{id}', [ProductController::class, 'statusUpdate'])->middleware('role:merchant,staff');
});


/*
|--------------------------------------------------------------------------
| Gender Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin/genders')->middleware('auth:admin,merchant')->group(function () {
    Route::post('/', [GenderController::class, 'store']);
    Route::get('/', [GenderController::class, 'index']);
    Route::get('/all', [GenderController::class, 'getAllGenders']);
    Route::get('/{id}', [GenderController::class, 'show']);
    Route::put('/{id}', [GenderController::class, 'update']);
    Route::patch('/{id}', [GenderController::class, 'update']);
    Route::delete('/{id}', [GenderController::class, 'destroy']);
});


/*
|--------------------------------------------------------------------------
| Attribute Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin/attributes')->middleware('auth:admin,merchant')->group(function () {
    Route::post('/', [AttributeController::class, 'store']);
    Route::get('/', [AttributeController::class, 'index']);
    Route::get('/all', [AttributeController::class, 'getAllAttributes']);
    Route::get('/{id}', [AttributeController::class, 'show']);
    Route::put('/{id}', [AttributeController::class, 'update']);
    Route::patch('/{id}', [AttributeController::class, 'update']);
    Route::delete('/{id}', [AttributeController::class, 'destroy']);
});


/*
|--------------------------------------------------------------------------
| Attribute Item Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin/attribute-items')->middleware('auth:admin,merchant')->group(function () {
    Route::post('/', [AttributeItemController::class, 'store']);
    Route::get('/', [AttributeItemController::class, 'index']);
    Route::get('/all', [AttributeItemController::class, 'getAllAttributeItems']);
    Route::get('/{id}', [AttributeItemController::class, 'show']);
    Route::put('/{id}', [AttributeItemController::class, 'update']);
    Route::patch('/{id}', [AttributeItemController::class, 'update']);
    Route::delete('/{id}', [AttributeItemController::class, 'destroy']);
});


/*
|--------------------------------------------------------------------------
| Settings Routes
|--------------------------------------------------------------------------
*/
Route::prefix('settings')->middleware('auth:admin,merchant,member')->group(function () {
    Route::get('/', [SettingController::class, 'getSetting'])->middleware('role:admin,merchant,member,staff');
    Route::post('/', [SettingController::class, 'upsertSetting'])->middleware('role:admin,staff');
    Route::delete('/', [SettingController::class, 'deleteSetting'])->middleware('role:admin,staff');
});


/*
|--------------------------------------------------------------------------
| Cp Level Config Routes
|--------------------------------------------------------------------------
*/ 

Route::prefix('cp-config')->middleware('auth:admin')->group(function () {
    Route::get('/', [CpLevelConfigController::class, 'index']);           
    Route::put('/bulk/update', [CpLevelConfigController::class, 'bulkUpdate']);
    // ✅ New Routes
    Route::get('/summary', [CpLevelConfigController::class, 'summary']);
    Route::post('/calculate', [CpLevelConfigController::class, 'calculateDistribution']);
    Route::get('/level/{level}', [CpLevelConfigController::class, 'getLevelPercentage']);
});


/*
|--------------------------------------------------------------------------
| Merchant Data Routes
|--------------------------------------------------------------------------
*/
Route::prefix('merchants')->middleware('auth:member,merchant,admin')->group(function () {
    // Create new merchant - only admin can create
    Route::post('/', [MerchantController::class, 'store'])->middleware('role:admin,member,staff');

    // Get all merchants - members, merchants, and admins can view
    Route::get('/', [MerchantController::class, 'index'])->middleware('role:member,merchant,admin,staff');

    // Locate merchants by state, town, company_address, business_type_id
    Route::get('/locate-merchants', [MerchantController::class, 'locateMerchants'])->middleware('role:member,merchant,admin,staff');

    // Get single merchant by ID - members, merchants, and admins can view
    Route::get('/{id}', [MerchantController::class, 'show'])->middleware('role:member,merchant,admin,staff');

    // Update merchant - only admin or merchant can update
    Route::post('/{id}', [MerchantController::class, 'update'])->middleware('role:admin,merchant,staff');

    // Delete merchant - only admin can delete
    Route::delete('/{id}', [MerchantController::class, 'destroy'])->middleware('role:admin,staff');

    // Suspend/Activate merchant - only admin can suspend
    // Route::post('/suspend', [MerchantController::class, 'suspendMerchant'])->middleware('role:admin');

    // Get merchant by unique number - members, merchants, and admins can view
    Route::get('/unique/{uniqueNumber}', [MerchantController::class, 'getByUniqueNumber'])->middleware('role:member,merchant,admin,staff');

    // Get all purchases by merchant ID
    Route::get('/{id}/purchases', [MerchantController::class, 'getPurchases'])->middleware('role:merchant,staff');

    // Get all daily purchases by merchant ID
    Route::get('/{id}/purchases/daily', [MerchantController::class, 'getDailyPurchases'])->middleware('role:merchant,staff');

    // Get all pending purchases by merchant ID
    Route::get('/{id}/pending/purchases', [MerchantController::class, 'getPendingPurchases'])->middleware('role:merchant,staff');

    // Approve purchase
    Route::post('/{id}/approve/purchase', [MerchantController::class, 'approvePurchase'])->middleware('role:merchant,staff');

    // Reject purchase
    Route::post('/rejected/purchase', [MerchantController::class, 'rejectedPurchase'])->middleware('role:merchant,staff');

});


/*
|--------------------------------------------------------------------------
| Merchant Staff Routes
|--------------------------------------------------------------------------
*/
Route::prefix('staffs')->middleware('auth:merchant,admin')->group(function () {
    // Create new staff
    Route::post('/', [MerchantStaffController::class, 'store'])->middleware('role:merchant,staff');

    // Get all staffs (with optional filters)
    Route::get('/', [MerchantStaffController::class, 'index'])->middleware('role:merchant,admin,staff');

    // Get single staff by ID
    Route::get('/{id}', [MerchantStaffController::class, 'show'])->middleware('role:merchant,admin,staff');

    // Update staff information
    Route::patch('/{id}', [MerchantStaffController::class, 'update'])->middleware('role:merchant,staff');

    // Delete staff
    Route::delete('/{id}', [MerchantStaffController::class, 'destroy'])->middleware('role:merchant,staff');

    // Get all staffs by merchant ID
    Route::get('/merchant/{merchantId}', [MerchantStaffController::class, 'getByMerchant'])->middleware('role:merchant,admin,staff');
});


/*
|--------------------------------------------------------------------------
| Member Data Routes (Public)
|--------------------------------------------------------------------------
*/
Route::prefix('members')->middleware('auth:member,admin,merchant')->group(function () {
    // Get all members (with optional filters)
    Route::get('/', [MemberController::class, 'index'])->middleware('role:admin,staff');

    // Get only general members
    Route::get('/general', [MemberController::class, 'getGeneralMembers'])->middleware('role:admin,staff');

    // Get only corporate members
    Route::get('/corporate', [MemberController::class, 'getCorporateMembers'])->middleware('role:admin,staff');

    // Get single member by ID
    Route::get('/{id}', [MemberController::class, 'show'])->middleware('role:admin,member,staff');

    // Get single members referrals list
    Route::get('/{id}/referrals', [MemberController::class, 'getReferrals'])->middleware('role:admin,member,merchant,staff');

    // Get single member community tree
    Route::get('/{id}/community-tree', [MemberController::class, 'getCommunityTree'])->middleware('role:admin,member,staff');

    // Get member by username
    Route::get('/username/{username}', [MemberController::class, 'getByUsername'])->middleware('role:admin,member,staff');

    // Get member by referral code
    Route::get('/referral/{referralCode}', [MemberController::class, 'getByReferralCode'])->middleware('role:admin,member,staff');

    // Update member information
    Route::patch('/{id}', [MemberController::class, 'update'])->middleware('role:admin,member,staff');

    // Status update
    Route::post('/status/{id}', [MemberController::class, 'updateStatus'])->middleware('role:admin,staff');

    // Redeem amount
    Route::post('/check-redeem-amount', [MemberController::class, 'checkRedeemAmount'])->middleware('role:member,admin,staff');
    
    Route::get('/bulk/approve-suspend', [MemberController::class, 'bulkApproveSuspend'])->middleware('role:admin,staff');

    // Route::post('/status/block-suspend', [MemberController::class, 'statusBlockSuspend'])->middleware('role:admin');

    // Get all purchases by member ID
    Route::get('/{id}/purchases', [MemberController::class, 'getPurchases'])->middleware('role:member,admin,staff');

    // Make purchase
    Route::post('/make-purchase', [MemberController::class, 'makePurchase'])->middleware('role:member');
});



Route::prefix('member')->middleware(['auth:admin,member,merchant'])->group(function () {

    // Refer new member (Both General & Corporate Members)
    Route::post('/refer-new-member', [ReferralController::class, 'referNewMember']);

    // Get referral tree
    Route::get('/referral-tree', [ReferralController::class, 'getReferralTree'])->middleware('role:member');

    // Get parent node members
    Route::get('/parent-node-members', [ReferralController::class, 'parentNodeMembers'])->middleware('role:member');

    // Get my sponsored members
    Route::get('/sponsored-members', [ReferralController::class, 'getMySponsoredMembers'])->middleware('role:member');

    // Get upline members (up to 30 levels) for a specific member
    Route::get('/{memberId}/upline', [ReferralController::class, 'getUplineMembers'])->middleware('role:admin,staff');

    // Get upline members (up to 30 levels) for a authenticated member
    Route::get('/upline', [ReferralController::class, 'getUplineMembers'])->middleware('role:member');

    /////////////////////////////////////////////////////////////////
    ////////////////////// Member vouchers routes////////////////////
    /////////////////////////////////////////////////////////////////

    // Voucher routes
    Route::post('/voucher/create', [VoucherController::class, 'createVoucher'])->middleware('role:member,merchant,staff');

    // Get member all vouchers
    Route::get('/vouchers', [VoucherController::class, 'getMemberVouchers'])->middleware('role:member,merchant,staff');

    // Get single voucher by ID
    Route::get('/{id}/vouchers', [VoucherController::class, 'getSingleVoucher'])->middleware('role:member,admin,merchant,staff');

    // Verify online payment after Stripe checkout
    Route::post('/verify-payment/voucher', [VoucherController::class, 'verifyPayment']);

    // Cancel payment
    Route::post('/cancel-payment/voucher', [VoucherController::class, 'cancelPayment']);

    // Get payment details by session ID
    Route::get('/vouchers/payment-details/{session_id}', [VoucherController::class, 'getPaymentDetails']);

    // Get voucher statistics
    Route::get('/vouchers/statistics', [VoucherController::class, 'getVoucherStats']);

    // Get maxreward corporate member
    Route::get('/maxreward-corporate-{id}', [MemberController::class, 'getMaxrewardCorporateMember'])->middleware('role:admin,member,staff');
});



/*
|--------------------------------------------------------------------------
| Member Order Routes (Protected)
|--------------------------------------------------------------------------
*/
Route::prefix('member')->middleware('auth:member')->group(function () {
    // Create orders (one per merchant)
    Route::post('orders', [OrderController::class, 'createOrders']);
    
    // Get my orders
    Route::get('orders', [OrderController::class, 'getMyOrders']);
    
    // Get single order details
    Route::get('orders/{orderNumber}', [OrderController::class, 'getOrderDetails']);
    
    // Cancel order
    // Route::post('orders/{orderNumber}/cancel', [OrderController::class, 'cancelOrder']);
    
    // Request return (Member initiates return)
    // Route::post('orders/{orderNumber}/return', [OrderController::class, 'requestReturn']);
});


/*
|--------------------------------------------------------------------------
| Merchant Order Routes (Protected)
|--------------------------------------------------------------------------
*/
Route::prefix('merchant')->middleware('auth:merchant')->group(function () {
    // Get merchant orders
    Route::get('orders', [OrderController::class, 'getMerchantOrders']);

    // Get single order details (Merchant view)
    Route::get('orders/{orderNumber}', [OrderController::class, 'getMerchantOrderDetails']);

    // Ship order
    Route::post('orders/{orderNumber}/ship', [OrderController::class, 'shipOrder']);

    // Cancel order (pending only)
    Route::post('orders/{orderNumber}/cancel', [OrderController::class, 'cancelOrder']);

    
    // Get eligible orders for auto-completion
    Route::get('/orders/auto-complete/eligible', [OrderAutoCompleteController::class, 'getEligibleOrders']);
    
    // Trigger manual auto-completion for merchant's orders
    Route::post('/orders/auto-complete', [OrderAutoCompleteController::class, 'merchantAutoComplete']);
    
    // Complete order
    // Route::post('orders/{orderNumber}/complete', [OrderController::class, 'completeOrder']);
    
    // Accept return (Merchant accepts return)
    // Route::post('orders/{orderNumber}/return', [OrderController::class, 'returnOrder']);

    // Exchange routes
    Route::prefix('exchanges')->group(function () {
        // Get all exchange requests
        Route::get('/', [OrderExchangeController::class, 'getMerchantExchanges']);
        
        // Get single exchange details
        Route::get('/{id}', [OrderExchangeController::class, 'getExchangeDetails']);
        
        // Create exchange request
        Route::post('/', [OrderExchangeController::class, 'createExchange']);
        
        // Approve exchange
        Route::post('/{id}/approve', [OrderExchangeController::class, 'approveExchange']);
        
        // Reject exchange
        Route::post('/{id}/reject', [OrderExchangeController::class, 'rejectExchange']);
        
        // Complete exchange
        Route::post('/{id}/complete', [OrderExchangeController::class, 'completeExchange']);
        
        // Get available variations for exchange
        Route::get('/available-variations/{orderItemId}', [OrderExchangeController::class, 'getAvailableVariations']);
        
        // Get statistics
        Route::get('/statistics', [OrderExchangeController::class, 'getExchangeStatistics']);
    });
});

/*
|--------------------------------------------------------------------------
| Git Webhook Auto Deploy (GET Only)
|--------------------------------------------------------------------------
*/
Route::get('webhook/git-deploy', [GitWebhookController::class, 'autoDeploy'])
    ->name('webhook.git-deploy');
