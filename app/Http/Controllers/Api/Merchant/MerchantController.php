<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Merchant;
use App\Models\Member;
use App\Models\MemberWallet;
use App\Models\MerchantWallet;
use App\Models\MerchantStaff;
use App\Traits\MerchantHelperTrait;
use App\Helpers\CloudinaryHelper;
use App\Models\Purchase;
use App\Traits\PointDistributionTrait; 
use App\Models\Transaction;
use App\Models\Notification;
use App\Models\Referral;
use App\Models\CompanyInfo;
use Illuminate\Support\Facades\Log;
use App\Helpers\CommonFunctionHelper;
use App\Services\CommunityTreeService;

class MerchantController extends Controller
{
    use MerchantHelperTrait, PointDistributionTrait;

    protected $settingAttributes;
    protected $treeService;

    public function __construct(CommonFunctionHelper $commonFunctionHelper, CommunityTreeService $treeService) {
        $this->settingAttributes = $commonFunctionHelper->settingAttributes()['maxreward'];
        $this->treeService = $treeService;
    }

    /**
     * Generate merchant staff username (M1 + 8 digits)
     */
    private function generateMerchantStaffUsername(): string
    {
        do {
            $username = 'M1' . str_pad(rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        } while (MerchantStaff::where('user_name', $username)->exists());

        return $username;
    }

    /**
     * Create a new merchant with corporate member, wallets, and staffs
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            // Merchant Basic Info
            'business_name' => 'required|string|max:255',
            'business_type_id' => 'required|string|max:255',
            'business_description' => 'nullable|string',
            // 'company_address' => 'required|string',
            // 'license_number' => 'required|string|unique:merchants,license_number',

            // Merchant Status
            'status' => 'nullable|in:pending,approved,rejected,suspended',

            // Bank Details
            // 'bank_name' => 'required|string|max:255',
            // 'account_holder_name' => 'required|string|max:255',
            // 'account_number' => 'required|string|max:50',
            'preferred_payment_method' => 'nullable|string',
            'routing_number' => 'nullable|string|max:50',
            'swift_code' => 'nullable|string|max:50',

            // Owner Details
            // 'owner_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|regex:/^01[0-9]{8,9}$/|unique:merchants,phone',
            // 'gender' => 'required|in:male,female,others',
            'address' => 'required|string',
            'email' => 'required|email|max:255|unique:merchants,email',

            // Business Details
            'reward_budget' => 'nullable|numeric|min:0|max:100',
            'annual_sales_turnover' => 'nullable|string',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'products_services' => 'nullable|string',
            'authorized_person_name' => 'nullable|string|max:255',

            // Corporate Member Password
            'corporate_password' => 'nullable|string|min:6',

            // Merchant Staff Password (for merchant type staff)
            'merchant_password' => 'nullable|string|min:6',

            // Business Logo
            'business_logo' => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:5120',

            // Additional Staff Members (optional)
            'staffs' => 'nullable|array',
            'staffs.*.name' => 'required|string|max:255',
            'staffs.*.phone' => 'required|string|max:20',
            'staffs.*.email' => 'required|email|max:255',
            // 'staffs.*.gender_type' => 'required|in:male,female,others',
            'staffs.*.type' => 'required|in:merchant,staff',
            'staffs.*.password' => 'nullable|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Start database transaction
            DB::beginTransaction();

            // Generate unique number for merchant
            $uniqueNumber = $this->generateUniqueNumber();

            // Handle business logo upload to Cloudinary
            $businessLogoUrl = null;
            $logoCloudinaryId = null;

            if ($request->hasFile('business_logo')) {
                $uploadResult = CloudinaryHelper::uploadImage(
                    $request->file('business_logo'),
                    'maxreward/merchants/logos'
                );
                $businessLogoUrl = $uploadResult['url'];
                $logoCloudinaryId = $uploadResult['public_id'];
            }

            // Create Merchant
            $merchant = Merchant::create([
                'business_name' => $request->business_name,
                'business_type_id' => $request->business_type_id,
                'business_description' => $request->business_description,
                'company_address' => $request->company_address,
                'status' => $request->status ?? 'pending',
                'license_number' => $request->license_number,
                'unique_number' => $uniqueNumber,
                'bank_name' => $request->bank_name,
                'account_holder_name' => $request->account_holder_name,
                'account_number' => $request->account_number,
                'preferred_payment_method' => $request->preferred_payment_method ?? 'Bank Transfer',
                'routing_number' => $request->routing_number,
                'swift_code' => $request->swift_code,
                'owner_name' => $request->owner_name,
                'phone' => $request->phone,
                'gender' => $request->gender,
                'address' => $request->address,
                'email' => $request->email,
                'reward_budget' => $request->reward_budget ?? 0,
                'annual_sales_turnover' => $request->annual_sales_turnover ?? null,
                'state' => $request->state,
                'country' => $request->country ?? null,
                'products_services' => $request->products_services,
                'designation' => $request->designation,
                // 'merchant_created_by' => 'api', // or auth()->user()->id if authenticated
                'merchant_created_by' => $request->merchant_created_by,
                'business_logo' => $businessLogoUrl,
                'logo_cloudinary_id' => $logoCloudinaryId,
                'authorized_person_name' => $request->authorized_person_name
            ]);

            // Generate corporate member username
            $corporateUsername = $this->generateCorporateMemberUsername();

            // Create Corporate Member
            $corporateMember = Member::create([
                'user_name' => $corporateUsername,
                'name' => $merchant->business_name,
                'phone' => $request->phone,
                'email' => $request->email,
                'password' => Hash::make($request->corporate_password ?? 'password123'),
                'member_type' => 'corporate',
                'gender_type' => $request->gender,
                'status' => 'active',
                'merchant_id' => $merchant->id,
                'member_created_by' => 'merchant',
                'referral_code' => strtoupper(Str::random(8)),
            ]);

            // Update merchant with corporate_member_id
            $merchant->update(['corporate_member_id' => $corporateMember->id]);

            // Create Member Wallet for Corporate Member
            $memberWallet = MemberWallet::create([
                'member_id' => $corporateMember->id,
                'total_referrals' => 0,
                'unlocked_level' => 5,
                'onhold_points' => 0.00,
                'total_points' => 0.00,
                'available_points' => 0.00,
                'total_rp' => 0.00,
                'total_pp' => 0.00,
                'total_cp' => 0.00,
            ]);

            // Create Merchant Wallet
            $merchantWallet = MerchantWallet::create([
                'merchant_id' => $merchant->id,
                'total_points' => 0.00,
            ]);

            // Create Merchant Staff (automatically from merchant data)
            $merchantStaffUsername = $this->generateMerchantStaffUsername();

            $merchantStaff = MerchantStaff::create([
                'merchant_id' => $merchant->id,
                'user_name' => $merchantStaffUsername,
                'name' => $request->owner_name,
                'phone' => $request->phone,
                'email' => $request->email,
                'password' => Hash::make($request->merchant_password),
                'designation' => $request->designation,
                'type' => 'merchant',
                'status' => 'active',
                'gender_type' => $request->gender,
            ]);

            // Commit transaction
            DB::commit();

            // Load relationships
            $merchant->load(['wallet', 'corporateMember', 'staffs']);

            

            return response()->json([
                'success' => true,
                'message' => 'Merchant created successfully',
                'data' => $merchant
            ], 201);

        } catch (\Illuminate\Database\QueryException $e) {
            // Rollback transaction on error
            DB::rollBack();

            // Handle specific database constraint violations
            if ($e->errorInfo[1] == 1062) { // Duplicate entry error code
                $errorMessage = $e->getMessage();

                if (strpos($errorMessage, 'merchants_phone_unique') !== false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation error',
                        'errors' => [
                            'phone' => ['The phone number has already been taken.']
                        ]
                    ], 422);
                }

                if (strpos($errorMessage, 'merchants_email_unique') !== false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation error',
                        'errors' => [
                            'email' => ['The email has already been taken.']
                        ]
                    ], 422);
                }

                if (strpos($errorMessage, 'merchants_license_number_unique') !== false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation error',
                        'errors' => [
                            'license_number' => ['The license number has already been taken.']
                        ]
                    ], 422);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Database error occurred',
                'error' => $e->getMessage()
            ], 500);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create merchant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all merchants with pagination
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request) 
    {
       
        // This automatically gets the user from whichever guard authenticated them
        // $user = $request->user();
        
        // dd([
        //     'user' => $user,
        //     'user_class' => $user ? get_class($user) : null,
        //     'user_id' => $user?->id ?? null,
        //     'user_name' => $user?->user_name ?? null,
        // ]);

        // dd(auth()->user());

        // dd([
        //     'request_user' => $request->user(),
        //     'auth_user' => auth()->user(),
        //     'bearer_token' => $request->bearerToken(),
        //     'auth_header' => $request->header('Authorization'),
        //     'admin_check' => auth('admin')->check(),
        //     'admin_user' => auth('admin')->user(),
        //     'member_check' => auth('member')->check(),
        //     'member_user' => auth('member')->user(),
        //     'merchant_check' => auth('merchant')->check(),
        //     'merchant_user' => auth('merchant')->user(),
        //     'guards' => config('auth.guards'),
        //     'default_guard' => config('auth.defaults.guard'),
        // ]);

        try {
            // Query builder
            $query = Merchant::query();

            // Filter by status (optional)
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by business type (optional)
            if ($request->has('business_type_id')) {
                $query->where('business_type_id', $request->business_type_id);
            }

            // Search by merchant_id (optional)
            if ($request->has('merchant_id')) {
                $query->where('id', $request->merchant_id);
            }

            // Search by business_name (optional)
            if ($request->has('business_name')) {
                $query->where('business_name', 'LIKE', '%' . $request->business_name . '%');
            }

            // Search by email (optional)
            if ($request->has('email')) {
                $query->where('email', 'LIKE', '%' . $request->email . '%');
            }

            // Search by phone (optional)
            if ($request->has('phone')) {
                $query->where('phone', 'LIKE', '%' . $request->phone . '%');
            }

            // General search by business name, email, phone (optional)
            if ($request->has('search')) {
                $query->where(function($q) use ($request) {
                    $q->where('business_name', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('email', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('phone', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('business_type_id', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('id', $request->search);
                });
            }

            // Get pagination limit (default: 10)
            $perPage = $request->get('per_page', 10);

            // Fetch merchants with relationships
            $merchants = $query->with([
                'wallet',              // Include merchant wallet
                'corporateMember',     // Include linked corporate member
                'staffs' => function($q) {
                    $q->where('status', 'active'); // Only active staffs
                }
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Merchants retrieved successfully',
                'data' => $merchants
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve merchants',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single merchant by ID
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $merchant = Merchant::with([
                'wallet',
                'corporateMember.wallet'
            ])->findOrFail($id);

            // Load statistics from your tree service
            $statistics = $this->treeService->getTreeStatistics($merchant->corporateMember->id);
            $merchant->community_members = $statistics['total_members'];

            $staffs = $merchant->staffs()->paginate(20); 

            $referred_members = CommonFunctionHelper::sponsoredMembers($merchant->corporateMember->id);

            return response()->json([
                'success' => true,
                'message' => 'Merchant retrieved successfully',
                'data' => [
                    'merchant' => $merchant,
                    'staffs' => $staffs,
                    'referred_members' => $referred_members
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Merchant not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve merchant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get merchant by unique number
     *
     * @param string $uniqueNumber
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByUniqueNumber($uniqueNumber)
    {
        try {
            $merchant = Merchant::with([
                'wallet',
                'corporateMember',
                'staffs'
            ])->where(function($query) use ($uniqueNumber) {
                $query->where('unique_number', $uniqueNumber)
                      ->orWhere('business_name', $uniqueNumber);
            })->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Merchant retrieved successfully',
                'data' => $merchant
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Merchant not found with this unique number or phone number'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve merchant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update merchant and staff information
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */


    public function update(Request $request, $id)
    {

        // Validate request
        $validator = Validator::make($request->all(), [
            // Merchant Basic Info
            'business_name' => 'sometimes|required|string|max:255',
            'business_type_id' => 'sometimes|required|string|max:255',
            'business_description' => 'nullable|string',
            // 'company_address' => 'sometimes|required|string',
            // 'license_number' => 'sometimes|required|string|unique:merchants,license_number,' . $id,

            // Merchant Status
            'status' => 'nullable|in:pending,approved,rejected,suspended',

            // Bank Details
            // 'bank_name' => 'sometimes|required|string|max:255',
            // 'account_holder_name' => 'sometimes|required|string|max:255',
            // 'account_number' => 'sometimes|required|string|max:50',
            'preferred_payment_method' => 'nullable|string',
            'routing_number' => 'nullable|string|max:50',
            'swift_code' => 'nullable|string|max:50',

            // Owner Details
            // 'owner_name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20|regex:/^01[0-9]{8,9}$/|unique:merchants,phone,' . $id,
            // 'gender' => 'sometimes|required|in:male,female,others',
            'address' => 'sometimes|required|string',
            'email' => 'sometimes|required|email|max:255|unique:merchants,email,' . $id,

            // Business Details
            'reward_budget' => 'nullable|numeric|min:0|max:100',
            'annual_sales_turnover' => 'nullable|string',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'products_services' => 'nullable|string',
            'authorized_person_name' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:255',

            // Business Logo
            'business_logo' => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:5120',

            // Staff Members (optional) - can update existing or add new
            'staffs' => 'nullable|array',
            'staffs.*.id' => 'nullable|integer|exists:merchant_staffs,id', // If ID exists, update; otherwise create
            'staffs.*.name' => 'required|string|max:255',
            'staffs.*.phone' => 'required|string|max:20',
            'staffs.*.email' => 'required|email|max:255',
            'staffs.*.gender_type' => 'required|in:male,female,others',
            'staffs.*.type' => 'required|in:merchant,staff',
            'staffs.*.status' => 'nullable|in:active,inactive',
            'staffs.*.password' => 'nullable|string|min:6',

            // Staff IDs to delete
            'delete_staff_ids' => 'nullable|array',
            'delete_staff_ids.*' => 'integer|exists:merchant_staffs,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Start database transaction
            DB::beginTransaction();

            // Find merchant
            $merchant = Merchant::findOrFail($id);

            // Handle business logo upload to Cloudinary
            if ($request->hasFile('business_logo')) {
                // Delete old logo from Cloudinary if exists
                if ($merchant->logo_cloudinary_id) {
                    CloudinaryHelper::deleteImage($merchant->logo_cloudinary_id);
                }

                // Upload new logo
                $uploadResult = CloudinaryHelper::uploadImage(
                    $request->file('business_logo'),
                    'maxreward/merchants/logos'
                );

                // Update merchant with new logo data
                $merchant->business_logo = $uploadResult['url'];
                $merchant->logo_cloudinary_id = $uploadResult['public_id'];
                $merchant->save();
            }

            // Update merchant data (only fields that are provided)
            $merchantData = [];

            $updateableFields = [
                'business_name', 'business_type_id', 'business_description',
                'company_address', 'status', 'license_number',
                'bank_name', 'account_holder_name', 'account_number',
                'preferred_payment_method', 'routing_number', 'swift_code',
                'owner_name', 'phone', 'gender', 'address', 'email',
                'reward_budget', 'annual_sales_turnover', 'state',
                'country', 'products_services', 'reward_budget',
                'annual_sales_turnover', 'authorized_person_name', 'designation'
            ];

            foreach ($updateableFields as $field) {
                if ($request->has($field)) {
                    $merchantData[$field] = $request->$field;
                }
            }

            if (!empty($merchantData)) {
                $merchant->update($merchantData);
            }

            // Update Corporate Member if phone or email changed
            if ($merchant->corporateMember && ($request->has('phone') || $request->has('email') || $request->has('business_name'))) {
                $corporateData = [];

                if ($request->has('business_name')) {
                    $corporateData['name'] = $request->business_name;
                }
                if ($request->has('phone')) {
                    $corporateData['phone'] = $request->phone;
                }
                if ($request->has('email')) {
                    $corporateData['email'] = $request->email;
                }
                if ($request->has('gender')) {
                    $corporateData['gender_type'] = $request->gender;
                }

                if (!empty($corporateData)) {
                    $merchant->corporateMember->update($corporateData);
                }
            }

            // Update Merchant Staff (type='merchant') if owner details changed
            $merchantStaff = MerchantStaff::where('merchant_id', $merchant->id)
                ->where('type', 'merchant')
                ->first();

            if ($merchantStaff && ($request->has('phone') || $request->has('email') || $request->has('gender') || $request->has('designation'))) {
                $merchantStaffData = [];

                if ($request->has('phone')) {
                    $merchantStaffData['phone'] = $request->phone;
                }
                if ($request->has('email')) {
                    $merchantStaffData['email'] = $request->email;
                }
                if ($request->has('gender')) {
                    $merchantStaffData['gender_type'] = $request->gender;
                }
                if ($request->has('designation')) {
                    $merchantStaffData['designation'] = $request->designation;
                }

                if (!empty($merchantStaffData)) {
                    $merchantStaff->update($merchantStaffData);
                }
            }

            // Commit transaction
            DB::commit();

            // Refresh merchant instance to get latest data from database
            $merchant = $merchant->fresh(['wallet', 'corporateMember', 'staffs']);

            return response()->json([
                'success' => true,
                'message' => 'Merchant updated successfully',
                'data' => $merchant
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Merchant not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update merchant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete merchant and all related data
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Start database transaction
            DB::beginTransaction();

            // Find merchant
            $merchant = Merchant::findOrFail($id);

            // Store merchant info for response
            $merchantInfo = [
                'id' => $merchant->id,
                'business_name' => $merchant->business_name,
                'unique_number' => $merchant->unique_number,
            ];

            // Delete all related data

            // 1. Delete all merchant staffs
            MerchantStaff::where('merchant_id', $merchant->id)->delete();

            // 2. Delete merchant wallet
            if ($merchant->wallet) {
                $merchant->wallet->delete();
            }

            // 3. Delete corporate member and their wallet
            if ($merchant->corporateMember) {
                $corporateMemberId = $merchant->corporateMember->id;

                // Delete corporate member's wallet
                MemberWallet::where('member_id', $corporateMemberId)->delete();

                // Delete corporate member
                Member::where('id', $corporateMemberId)->delete();
            }

            // 4. Finally delete the merchant
            $merchant->delete();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Merchant and all related data deleted successfully',
                'data' => $merchantInfo
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Merchant not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete merchant',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getPurchases($id) 
    {
        try {
            // Fetch all purchases for this merchant (latest first)
            $purchases = Purchase::where('merchant_id', $id)
                ->with(['member:id,name,email', 'merchant:id,business_name'])
                ->orderBy('created_at', 'desc')
                ->paginate(20); 
    
            return response()->json([
                'success' => true,
                'message' => 'Purchases retrieved successfully',
                'data' => $purchases,
            ]);
    
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Merchant not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch purchases',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getPendingPurchases($id) 
    {
        try {
            // Fetch all pending purchases for this merchant (latest first)
            $purchases = Purchase::where('merchant_id', $id)
                ->where('status', 'pending')
                ->with(['member:id,name,email', 'merchant:id,business_name'])
                ->orderBy('created_at', 'desc')
                ->paginate(20); 
    
            return response()->json([
                'success' => true,
                'message' => 'Pending purchases retrieved successfully',
                'data' => $purchases,
            ]);
    
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Merchant not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending purchases',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function approvePurchase($id)
    {
        DB::beginTransaction();

        try {
            // Step 1: Find the purchase record
            $purchase = Purchase::with(['member.wallet', 'merchant.wallet'])->find($id);
            // dd($purchase->merchant->corporateMember->id);
            if (!$purchase) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase not found.'
                ], 404);
            }

            // Step 2: Ensure it's pending before approving
            if ($purchase->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending purchases can be approved.'
                ], 400);
            }

            $rewardBudget = $purchase->merchant?->reward_budget;

            $totalPoints = ($purchase->transaction_amount * $rewardBudget) / 100;

            if ($purchase->merchant->wallet->total_points < $totalPoints) {
                if ($purchase->merchant->corporateMember->wallet->available_points < $totalPoints) {
                    return response()->json(['error' => 'Insufficient points for reward budget points. Please purchase vouchers.'], 400);
                }
            }

            // Step 3: Update purchase status to approved
            $purchase->update(['status' => 'approved']);

            // Step 4: Deduct points from member wallet
            if ($purchase->member && $purchase->member->wallet) {
                $purchase->member->wallet->decrement('available_points', $purchase->redeem_amount);
            }

            // Step 5: Transaction history - Member points deducted
            Transaction::create([
                'member_id' => $purchase->member_id,
                'merchant_id' => null,
                'transaction_points' => $purchase->redeem_amount,
                'transaction_type' => Transaction::TYPE_DP, // Deducted Points
                'points_type' => Transaction::POINTS_DEBITED,
                'transaction_reason' => "Points redeemed for purchase at {$purchase->merchant->business_name}. Purchase ID: {$purchase->id}"
            ]);

            // Step 6: Notification for member - Points redeemed
            Notification::create([
                'member_id' => $purchase->member_id,
                'type' => 'purchase_approved',
                'title' => 'Purchase Approved!',
                'message' => "Your purchase has been approved. {$purchase->redeem_amount} points redeemed at {$purchase->merchant->business_name}.",
                'data' => [
                    'purchase_id' => $purchase->id,
                    'redeem_amount' => $purchase->redeem_amount,
                    'transaction_amount' => $purchase->transaction_amount,
                    'merchant_name' => $purchase->merchant->business_name,
                    'approved_at' => now()->toDateTimeString()
                ],
                'status' => 'unread',
                'is_read' => false
            ]);

            // Step 7: Add redeem amount to merchant wallet
            if ($purchase->merchant && $purchase->merchant->wallet) {
                $purchase->merchant->wallet->increment('total_points', $purchase->redeem_amount);
            }

            // Step 8: Transaction history - Merchant points credited
            Transaction::create([
                'member_id' => null,
                'merchant_id' => $purchase->merchant_id,
                'transaction_points' => $purchase->redeem_amount,
                'transaction_type' => Transaction::TYPE_AP, // Added Points
                'points_type' => Transaction::POINTS_CREDITED,
                'transaction_reason' => "Purchase approved from member {$purchase->member->name}. Purchase ID: {$purchase->id}"
            ]);

            // Step 9: Notification for merchant - Purchase approved
            Notification::create([
                'merchant_id' => $purchase->merchant_id,
                'type' => 'purchase_approved',
                'title' => 'Purchase Transaction Completed',
                'message' => "Purchase from {$purchase->member->name} has been completed. Points {$purchase->redeem_amount} credited to your wallet.",
                'data' => [
                    'purchase_id' => $purchase->id,
                    'member_name' => $purchase->member->name,
                    'member_phone' => $purchase->member->phone,
                    'redeem_amount' => $purchase->redeem_amount,
                    'transaction_amount' => $purchase->transaction_amount,
                    'approved_at' => now()->toDateTimeString()
                ],
                'status' => 'unread',
                'is_read' => false
            ]);

            // Step 10: Add redeem amount to merchant corporate member wallet
            if ($purchase->merchant && $purchase->merchant->corporateMember->wallet) {
                $purchase->merchant->corporateMember->wallet->increment('available_points', $purchase->redeem_amount);
                $purchase->merchant->corporateMember->wallet->increment('total_points', $purchase->redeem_amount);
            }

            // Step 11: Transaction history - Merchant corporate member points credited
            Transaction::create([
                'member_id' => $purchase->merchant->corporateMember->id,
                'merchant_id' => null,
                'transaction_points' => $purchase->redeem_amount,
                'transaction_type' => Transaction::TYPE_AP, // Added Points
                'points_type' => Transaction::POINTS_CREDITED,
                'transaction_reason' => "Purchase approved from member {$purchase->member->name}. Purchase ID: {$purchase->id}"
            ]);

            // Step 12: Notification for merchant corporate member - Purchase approved
            Notification::create([
                'member_id' => $purchase->merchant->corporateMember->id,
                'type' => 'purchase_approved',
                'title' => 'Purchase Transaction Completed',
                'message' => "Purchase from {$purchase->member->name} has been completed. Points {$purchase->redeem_amount} credited to your wallet.",
                'data' => [
                    'purchase_id' => $purchase->id,
                    'member_name' => $purchase->member->name,
                    'member_phone' => $purchase->member->phone,
                    'redeem_amount' => $purchase->redeem_amount,
                    'transaction_amount' => $purchase->transaction_amount,
                    'approved_at' => now()->toDateTimeString()
                ]
            ]);

            // Step 13: Deduct total points from Merchant wallet
            if ($purchase->merchant && $purchase->merchant->wallet) {
                $purchase->merchant->wallet->decrement('total_points', $totalPoints);
            }

            // Step 14: Deduct total points from Merchant wallet transaction
            Transaction::create([
                'member_id' => null,
                'merchant_id' => $purchase->merchant_id,
                'transaction_points' => $totalPoints,
                'transaction_type' => Transaction::TYPE_DP, // Deducted Points
                'points_type' => Transaction::POINTS_DEBITED,
                'transaction_reason' => "Points deducted for purchase from {$purchase->member->name}. Points: {$totalPoints}. Reward Budget: {$rewardBudget}%"
            ]);

            // Step 15: Notification for deduct total points from Merchant wallet
            Notification::create([
                'merchant_id' => $purchase->merchant_id,
                'type' => 'redemption',
                'title' => 'Points Deducted',
                'message' => "Points deducted for purchase from {$purchase->member->name}. Points: {$totalPoints}. Reward Budget: {$rewardBudget}%",
                'data' => [
                    'purchase_id' => $purchase->id,
                    'member_name' => $purchase->member->name,
                    'member_phone' => $purchase->member->phone,
                    'deducted_amount' => $totalPoints,
                    'transaction_amount' => $purchase->transaction_amount,
                    'approved_at' => now()->toDateTimeString()
                ]
            ]);

            // Step 16: Deduct total points from Merchant corporate member wallet
            if ($purchase->merchant && $purchase->merchant->corporateMember->wallet) {
                $purchase->merchant->corporateMember->wallet->decrement('available_points', $totalPoints);
            }

            // Step 17: Transaction history - Merchant corporate member points debited
            Transaction::create([
                'member_id' => $purchase->merchant->corporateMember->id,
                'merchant_id' => null,
                'transaction_points' => $totalPoints,
                'transaction_type' => Transaction::TYPE_DP, // Deducted Points
                'points_type' => Transaction::POINTS_DEBITED,
                'transaction_reason' => "Points deducted for purchase from {$purchase->member->name}. Points: {$totalPoints}. Reward Budget: {$rewardBudget}%"
            ]);

            // Step 18: Notification for merchant corporate member - Points Deducted
            Notification::create([
                'member_id' => $purchase->merchant->corporateMember->id,
                'type' => 'redemption',
                'title' => 'Points Deducted',
                'message' => "Points deducted for purchase from {$purchase->member->name}. Points: {$totalPoints}. Reward Budget: {$rewardBudget}%",
                'data' => [
                    'purchase_id' => $purchase->id,
                    'member_name' => $purchase->member->name,
                    'member_phone' => $purchase->member->phone,
                    'deducted_amount' => $totalPoints,
                    'transaction_amount' => $purchase->transaction_amount,
                    'approved_at' => now()->toDateTimeString()
                ]
            ]);

            Log::info('1️ PP: total pp points add to PURCHASE MEMBER');

            // 1️ PP: total pp points add to PURCHASE MEMBER
            $ppAmount = $totalPoints * ($this->settingAttributes['pp_points']/100); 
            $purchaseMemberWallet = $purchase->member->wallet;
            $purchaseMemberWallet->total_pp += $ppAmount;
            $purchaseMemberWallet->available_points += $ppAmount;
            $purchaseMemberWallet->total_points += $ppAmount;
            $purchaseMemberWallet->save();

            Log::info('transaction_reason: Personal Points from purchase approval');

            Transaction::createTransaction([
                'member_id' => $purchase->member_id,
                'transaction_points' => $ppAmount,
                'transaction_type' => Transaction::TYPE_PP,
                'points_type' => Transaction::POINTS_CREDITED,
                'transaction_reason' => 'Personal Points from purchase approval',
            ]);

            Log::info('2️ RP: total rp points add to who Directly sponsored');

            // 2️ RP: total rp points add to who Directly sponsored
            $rpAmount = $totalPoints * ($this->settingAttributes['rp_points']/100); 
            $sponsor = Referral::where('child_member_id', $purchase->member_id)->first();

            if ($sponsor) {

                Log::info('Step :: sponsor');

                $sponsorId = $sponsor->sponsor_member_id;

                $sponsorMemberWallet = Member::find($sponsorId)->wallet;
                // $sponsorMemberWallet->total_rp += $rpAmount;
                $sponsorMemberWallet->available_points += $rpAmount;
                $sponsorMemberWallet->total_points += $rpAmount;
                $sponsorMemberWallet->save();

                Log::info('Step :: createTransaction for sponsor');

                Transaction::createTransaction([
                    'member_id' => $sponsorId,
                    'transaction_points' => $rpAmount,
                    'transaction_type' => Transaction::TYPE_RP,
                    'points_type' => Transaction::POINTS_CREDITED,
                    'transaction_reason' => "Reward Points from {$purchase->member->name}'s purchase approval",
                ]);

                Log::info('Step :: Reward points earned notification');

                Notification::create([
                    'member_id' => $sponsorId,
                    'type' => 'purchase_approved',
                    'title' => 'Reward Points Earned',
                    'message' => "You have earned {$rpAmount} Reward Points from {$purchase->member->name}'s purchase approval.",
                    'data' => [
                        'member_name' => $purchase->member->name,
                        'member_phone' => $purchase->member->phone,
                        'redeem_amount' => $purchase->redeem_amount,
                        'transaction_amount' => $purchase->transaction_amount,
                        'approved_at' => now()->toDateTimeString()
                    ]
                ]);
            }

            Log::info('3️ CP: CP points add to distributed across 30-level community tree');

            // 3️ CP: CP points add to distributed across 30-level community tree
            $cpAmount = $totalPoints * ($this->settingAttributes['cp_points']/100); 
            // ✅ For purchase, source and new member are the same for distributeCommunityPoints
            $this->distributeCommunityPoints($purchase->member_id, $purchase->member_id, $cpAmount, 'purchase', $purchase->id);

            Log::info('4️ CR: CR points add to Company Reserve');

            // 4️ CR: CR points add to Company Reserve
            $crAmount = $totalPoints * ($this->settingAttributes['cr_points']/100); 
            $company = CompanyInfo::getCompany();
            $company->incrementCrPoint($crAmount);

            Log::info('Company Reserve from '.$purchase->member->name.'s purchase approval');

            Transaction::createTransaction([
                'member_id' => null,
                'transaction_points' => $crAmount,
                'transaction_type' => Transaction::TYPE_CR,
                'points_type' => Transaction::POINTS_CREDITED,
                'transaction_reason' => "Company Reserve from {$purchase->member->name}'s purchase approval",
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Purchase approved successfully.',
                'data' => [
                    'purchase_id' => $purchase->id,
                    'status' => 'approved',
                    'approved_at' => now()->toDateTimeString(),
                    'member' => [
                        'id' => $purchase->member_id,
                        'name' => $purchase->member->name,
                        'points_deducted' => $purchase->redeem_amount,
                        'pp_points_earned' => $ppAmount
                    ],
                    'merchant' => [
                        'id' => $purchase->merchant_id,
                        'name' => $purchase->merchant->business_name,
                        'amount_credited' => $purchase->transaction_amount
                    ],
                    'points_distribution' => [
                        'total_points' => $totalPoints,
                        'pp_points' => $ppAmount,
                        'rp_points' => $rpAmount,
                        'cp_points' => $cpAmount,
                        'cr_points' => $crAmount
                    ],
                    'notifications' => [
                        'member' => true,
                        'merchant' => true,
                        'sponsor' => $sponsor ? true : false
                    ],
                    'transactions' => [
                        'member_deduction' => true,
                        'merchant_credit' => true,
                        'pp_transaction' => true,
                        'rp_transaction' => $sponsor ? true : false,
                        'cp_distribution' => true,
                        'cr_transaction' => true
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
    
            Log::error('Purchase approval failed: ' . $e->getMessage(), [
                'purchase_id' => $id,
                'exception' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve purchase. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                'data' => [
                    'purchase_id' => $id
                ]
            ], 500);
        }
    }

    /**
     * Suspend merchant (Admin only)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function suspendMerchant(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'merchant_id' => 'required|exists:merchants,id',
                'status' => 'required|in:suspended,active',
                'suspended_reason' => 'required_if:status,suspended|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find merchant
            $merchant = Merchant::findOrFail($request->merchant_id);

            // Update merchant status and reason
            $merchant->status = $request->status;

            if ($request->status == 'suspended') {
                $merchant->suspended_reason = $request->suspended_reason;
                $merchant->suspended_by = auth()->id(); // Store admin who suspended
            } else {
                // If activating, clear suspension reason
                $merchant->suspended_reason = null;
                $merchant->suspended_by = null;
            }

            $merchant->save();

            // Load relationships
            $merchant->load(['wallet', 'corporateMember', 'staffs']);

            return response()->json([
                'success' => true,
                'message' => $request->status == 'suspended'
                    ? 'Merchant suspended successfully'
                    : 'Merchant activated successfully',
                'data' => $merchant
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Merchant not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update merchant status',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function rejectedPurchase(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'purchase_id' => 'required|exists:purchases,id',
            'status' => 'required|in:rejected',
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Find the purchase record
            $purchase = Purchase::with(['member', 'merchant'])->find($request->purchase_id);

            if (!$purchase) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase not found.'
                ], 404);
            }

            // Ensure it's pending before rejecting
            if ($purchase->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending purchases can be rejected.'
                ], 400);
            }

            // Update purchase status to rejected
            $purchase->update([
                'status' => $request->status,
                'rejected_reason' => $request->reason,
                'rejected_by' => auth('merchant')->id()
            ]);

             // 'rejected_by' => auth()->id() ?? auth('merchant')->id() ?? auth('admin')->id()

            // Refund points to member wallet (since purchase was rejected)
            // if ($purchase->member && $purchase->member->wallet) {
            //     $purchase->member->wallet->increment('available_points', $purchase->redeem_amount);
            // }

            // Transaction history - Member points refunded
            // Transaction::create([
            //     'member_id' => $purchase->member_id,
            //     'merchant_id' => $purchase->merchant_id,
            //     'transaction_points' => $purchase->redeem_amount,
            //     'transaction_type' => Transaction::TYPE_AP, // Added Points (refund)
            //     'points_type' => Transaction::POINTS_CREDITED,
            //     'transaction_reason' => "Purchase rejected at {$purchase->merchant->business_name}. Points refunded. Reason: {$request->reason}"
            // ]);

            // Notification for member - Purchase rejected
            // Notification::create([
            //     'member_id' => $purchase->member_id,
            //     'type' => 'purchase_rejected',
            //     'title' => 'Purchase Rejected',
            //     'message' => "Your purchase at {$purchase->merchant->business_name} has been rejected. {$purchase->redeem_amount} points have been refunded. Reason: {$request->reason}",
            //     'data' => [
            //         'purchase_id' => $purchase->id,
            //         'redeem_amount' => $purchase->redeem_amount,
            //         'transaction_amount' => $purchase->transaction_amount,
            //         'merchant_name' => $purchase->merchant->business_name,
            //         'rejected_reason' => $request->reason,
            //         'rejected_at' => now()->toDateTimeString()
            //     ],
            //     'status' => 'unread',
            //     'is_read' => false
            // ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Purchase rejected successfully.',
                'data' => $purchase
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject purchase. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

  
    public function changePassword(Request $request)
    {
        try {
            $merchantStaff = auth()->user();

            // Validate request
            $validator = Validator::make($request->all(), [
                'password' => 'required|string',
                'new_password' => 'required|string|min:6|max:255',
                'confirmation_password' => 'required|string|same:new_password',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if current password is correct
            if (!Hash::check($request->password, $merchantStaff->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 401);
            }

            // Check if new password is same as current password
            if (Hash::check($request->new_password, $merchantStaff->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'New password cannot be the same as current password'
                ], 400);
            }

            // Update password
            $merchantStaff->password = Hash::make($request->new_password);
            $merchantStaff->save();

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}