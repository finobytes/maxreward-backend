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

class MerchantController extends Controller
{
    /**
     * Generate 8 character unique number for merchant
     */
    private function generateUniqueNumber(): string
    {
        do {
            $uniqueNumber = strtoupper(Str::random(8));
        } while (Merchant::where('unique_number', $uniqueNumber)->exists());

        return $uniqueNumber;
    }

    /**
     * Generate corporate member username (C + 8 digits)
     */
    private function generateCorporateMemberUsername(): string
    {
        do {
            $username = 'C' . str_pad(rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        } while (Member::where('user_name', $username)->exists());

        return $username;
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
        // dd($request->all());
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
            'phone' => 'required|string|max:20|unique:merchants,phone',
            'gender' => 'required|in:male,female,other',
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

            // Staff Members (optional)
            'staffs' => 'nullable|array',
            'staffs.*.name' => 'required|string|max:255',
            'staffs.*.phone' => 'required|string|max:20',
            'staffs.*.email' => 'required|email|max:255',
            'staffs.*.gender_type' => 'required|in:male,female,other',
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
                'merchant_created_by' => 'api', // or auth()->user()->id if authenticated
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
            MemberWallet::create([
                'member_id' => $corporateMember->id,
                'total_referrals' => 0,
                'unlocked_level' => 0,
                'onhold_points' => 0.00,
                'total_points' => 0.00,
                'available_points' => 0.00,
                'total_rp' => 0.00,
                'total_pp' => 0.00,
                'total_cp' => 0.00,
            ]);

            // Create Merchant Wallet
            MerchantWallet::create([
                'merchant_id' => $merchant->id,
                'total_referrals' => 0,
                'unlocked_level' => 0,
                'onhold_points' => 0.00,
                'total_points' => 0.00,
                'available_points' => 0.00,
                'total_rp' => 0.00,
                'total_pp' => 0.00,
                'total_cp' => 0.00,
            ]);

            // Create Staff Members (if provided)
            $createdStaffs = [];
            if ($request->has('staffs') && is_array($request->staffs)) {
                foreach ($request->staffs as $staffData) {
                    $staffUsername = $this->generateMerchantStaffUsername();

                    $staff = MerchantStaff::create([
                        'merchant_id' => $merchant->id,
                        'user_name' => $staffUsername,
                        'name' => $staffData['name'],
                        'phone' => $staffData['phone'],
                        'email' => $staffData['email'],
                        'password' => Hash::make($staffData['password'] ?? 'staff123'),
                        'type' => $staffData['type'],
                        'status' => 'active',
                        'gender_type' => $staffData['gender_type'],
                    ]);

                    $createdStaffs[] = [
                        'id' => $staff->id,
                        'user_name' => $staff->user_name,
                        'name' => $staff->name,
                        'email' => $staff->email,
                        'type' => $staff->type,
                    ];
                }
            }

            // Commit transaction
            DB::commit();

            // Load relationships
            $merchant->load(['wallet', 'corporateMember', 'staffs']);

            return response()->json([
                'success' => true,
                'message' => 'Merchant created successfully',
                'data' => [
                    'merchant' => $merchant,
                    'corporate_member' => [
                        'id' => $corporateMember->id,
                        'user_name' => $corporateMember->user_name,
                        'name' => $corporateMember->name,
                        'email' => $corporateMember->email,
                    ],
                    'staffs' => $createdStaffs,
                    'credentials' => [
                        'merchant_unique_number' => $uniqueNumber,
                        'corporate_username' => $corporateUsername,
                        'corporate_password' => $request->corporate_password ?? 'password123',
                    ]
                ]
            ], 201);

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
        try {
            // Query builder
            $query = Merchant::query();

            // Filter by status (optional)
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by business type (optional)
            if ($request->has('business_type')) {
                $query->where('business_type', $request->business_type);
            }

            // Search by business name (optional)
            if ($request->has('search')) {
                $query->where('business_name', 'LIKE', '%' . $request->search . '%');
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
}