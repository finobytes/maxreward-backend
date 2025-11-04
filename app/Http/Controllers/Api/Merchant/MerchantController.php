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

class MerchantController extends Controller
{
    use MerchantHelperTrait;

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
            'business_type' => 'required|string|max:255',
            'business_description' => 'nullable|string',
            'company_address' => 'required|string',
            'license_number' => 'required|string|unique:merchants,license_number',

            // Merchant Status
            'status' => 'nullable|in:pending,approved,rejected,suspended',

            // Bank Details
            'bank_name' => 'required|string|max:255',
            'account_holder_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:50',
            'preferred_payment_method' => 'nullable|string',
            'routing_number' => 'nullable|string|max:50',
            'swift_code' => 'nullable|string|max:50',

            // Owner Details
            'owner_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|regex:/^01[0-9]{8,9}$/|unique:merchants,phone',
            'gender' => 'required|in:male,female,others',
            'address' => 'required|string',
            'email' => 'required|email|max:255|unique:merchants,email',

            // Business Details
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'settlement_period' => 'nullable|in:daily,weekly,monthly',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'products_services' => 'nullable|string',

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
            'staffs.*.gender_type' => 'required|in:male,female,others',
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
                'business_type' => $request->business_type,
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
                'commission_rate' => $request->commission_rate ?? 0.00,
                'settlement_period' => $request->settlement_period ?? 'monthly',
                'state' => $request->state,
                'country' => $request->country ?? 'Bangladesh',
                'products_services' => $request->products_services,
                // 'merchant_created_by' => 'api', // or auth()->user()->id if authenticated
                'merchant_created_by' => $request->merchant_created_by,
                'business_logo' => $businessLogoUrl,
                'logo_cloudinary_id' => $logoCloudinaryId,
            ]);

            // Generate corporate member username
            $corporateUsername = $this->generateCorporateMemberUsername();

            // Create Corporate Member
            $corporateMember = Member::create([
                'user_name' => $corporateUsername,
                'name' => $request->business_name,
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
                $query->where('business_type_id', $request->business_type);
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
                'corporateMember.wallet',
                'staffs'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Merchant retrieved successfully',
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
            ])->where('unique_number', $uniqueNumber)->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Merchant retrieved successfully',
                'data' => $merchant
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Merchant not found with this unique number'
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
            'business_type' => 'sometimes|required|string|max:255',
            'business_description' => 'nullable|string',
            'company_address' => 'sometimes|required|string',
            'license_number' => 'sometimes|required|string|unique:merchants,license_number,' . $id,

            // Merchant Status
            'status' => 'nullable|in:pending,approved,rejected,suspended',

            // Bank Details
            'bank_name' => 'sometimes|required|string|max:255',
            'account_holder_name' => 'sometimes|required|string|max:255',
            'account_number' => 'sometimes|required|string|max:50',
            'preferred_payment_method' => 'nullable|string',
            'routing_number' => 'nullable|string|max:50',
            'swift_code' => 'nullable|string|max:50',

            // Owner Details
            'owner_name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20|regex:/^01[0-9]{8,9}$/|unique:merchants,phone,' . $id,
            'gender' => 'sometimes|required|in:male,female,others',
            'address' => 'sometimes|required|string',
            'email' => 'sometimes|required|email|max:255|unique:merchants,email,' . $id,

            // Business Details
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'settlement_period' => 'nullable|in:daily,weekly,monthly',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'products_services' => 'nullable|string',

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
                'business_name', 'business_type', 'business_description',
                'company_address', 'status', 'license_number',
                'bank_name', 'account_holder_name', 'account_number',
                'preferred_payment_method', 'routing_number', 'swift_code',
                'owner_name', 'phone', 'gender', 'address', 'email',
                'commission_rate', 'settlement_period', 'state',
                'country', 'products_services'
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

            if ($merchantStaff && ($request->has('phone') || $request->has('email') || $request->has('gender'))) {
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
}