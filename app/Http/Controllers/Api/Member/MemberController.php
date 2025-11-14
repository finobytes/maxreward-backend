<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Member;
use App\Models\Voucher;
use App\Models\MemberWallet;
use App\Helpers\CloudinaryHelper;
use App\Models\Setting;
use App\Models\Purchase;
use App\Models\TransactionCounter;
use App\Helpers\CommonFunctionHelper;
use App\Services\CommunityTreeService;

class MemberController extends Controller
{

    protected $treeService;

    public function __construct(CommunityTreeService $treeService) {
        $this->treeService = $treeService;
    }

    /**
     * Get all members with pagination
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Query builder
            $query = Member::query();

            // Filter by member type (optional)
            if ($request->has('member_type')) {
                $query->where('member_type', $request->member_type);
            }

            // Filter by status (optional)
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by gender (optional)
            if ($request->has('gender_type')) {
                $query->where('gender_type', $request->gender_type);
            }

            // Filter by merchant (for corporate members)
            if ($request->has('merchant_id')) {
                $query->where('merchant_id', $request->merchant_id);
            }

            // Search by name (optional)
            if ($request->has('name')) {
                $query->where('name', 'LIKE', '%' . $request->name . '%');
            }

            // Search by phone (optional)
            if ($request->has('phone')) {
                $query->where('phone', 'LIKE', '%' . $request->phone . '%');
            }

            // Search by email (optional)
            if ($request->has('email')) {
                $query->where('email', 'LIKE', '%' . $request->email . '%');
            }

            // Search by address (optional)
            if ($request->has('address')) {
                $query->where('address', 'LIKE', '%' . $request->address . '%');
            }

            // General search by name, phone, email, user_name, address (optional)
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', '%' . $search . '%')
                      ->orWhere('phone', 'LIKE', '%' . $search . '%')
                      ->orWhere('user_name', 'LIKE', '%' . $search . '%')
                      ->orWhere('email', 'LIKE', '%' . $search . '%')
                      ->orWhere('address', 'LIKE', '%' . $search . '%')
                      ->orWhere('id', $search);
                });
            }

            // Get pagination limit (default: 10)
            $perPage = $request->get('per_page', 10);

            // Fetch members with relationships
            $members = $query->with([
                'wallet',     // Include member wallet
                'merchant'    // Include linked merchant (for corporate members)
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Members retrieved successfully',
                'data' => $members
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve members',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single member by ID
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $member = Member::with([
                'wallet',
                'merchant.wallet'  // Include merchant and its wallet
            ])->findOrFail($id);

            // Calculate lifetime purchase (sum of approved transaction amounts)
            $member->lifetime_purchase = Purchase::where('member_id', $member->id)
            ->where('status', 'approved') 
            ->sum('transaction_amount');

            $member->active_referrals = CommonFunctionHelper::sponsoredMembers($member->id);

            // Load statistics from your tree service
            $statistics = $this->treeService->getTreeStatistics($member->id);
            $member->community_members = $statistics['total_members'];

            return response()->json([
                'success' => true,
                'message' => 'Member retrieved successfully',
                'data' => $member
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve member',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getReferrals($id)
    {
        try{
            $member_referrals = CommonFunctionHelper::sponsoredMembers($id);

            return response()->json([
                'success' => true,
                'message' => 'Member referrals retrieved successfully',
                'data' => $member_referrals
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve member referrals',
                'error' => $e->getMessage()
            ]);
        }
    }


    public function getCommunityTree($id)
    {
        try{
            $member = Member::find($id);
            $data = CommonFunctionHelper::getMemberCommunityTree($member->id, app(CommunityTreeService::class));
            // dd($data['tree'], $data['statistics']);
            return response()->json([
                'success' => true,
                'message' => 'Member tree with structure retrieved successfully',
                'data' => [
                    'root_member' => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'user_name' => $member->user_name,
                        'referral_code' => $member->referral_code,
                        'image' => $member->image,
                        'phone' => $member->phone
                    ],
                    'statistics' => [
                        'total_members' => $data['statistics']['total_members'],
                        'deepest_level' => $data['statistics']['deepest_level'],
                        'left_leg_count' => $data['statistics']['left_leg_count'],
                        'right_leg_count' => $data['statistics']['right_leg_count'],
                    ],
                    'tree_structure' => $data['tree'],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve member referrals',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get member by username (phone or corporate ID)
     * 
     * @param string $username
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByUsername($username)
    {
        try {
            $member = Member::with([
                'wallet',
                'merchant'
            ])->where('user_name', $username)->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Member retrieved successfully',
                'data' => $member
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found with this username'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get member by referral code (searches in both referral_code and phone columns)
     *
     * @param string $referralCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByReferralCode($referralCode)
    {
        try {
            $member = Member::with([
                'wallet',
                'merchant',
                'sponsoredMemberInfo' => function($query) {
                    $query->with('sponsorMember');
                }
            ])->where(function($query) use ($referralCode) {
                $query->where('referral_code', $referralCode)
                      ->orWhere('phone', $referralCode);
            })->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Member retrieved successfully',
                'data' => $member
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found with this referral code or phone'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get general members only
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGeneralMembers(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);

            $members = Member::with('wallet')
                ->where('member_type', 'general')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'General members retrieved successfully',
                'data' => $members
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve general members',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get corporate members only
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCorporateMembers(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);

            $members = Member::with(['wallet', 'merchant'])
                ->where('member_type', 'corporate')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Corporate members retrieved successfully',
                'data' => $members
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve corporate members',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update member information
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            // Start database transaction
            DB::beginTransaction();

            // Find the member
            $member = Member::findOrFail($id);

            // Validate request
            $validatedData = $request->validate([
                'name' => 'sometimes|string|max:255',
                'phone' => 'sometimes|string|max:20|unique:members,phone,' . $id,
                'address' => 'sometimes|string|max:500',
                'email' => 'sometimes|email|max:255|unique:members,email,' . $id,
                'status' => 'sometimes|in:active,inactive,suspended',
                'image' => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:5120',
            ]);

            // Handle image upload to Cloudinary
            if ($request->hasFile('image')) {
                // Delete old image from Cloudinary if exists
                if ($member->image_cloudinary_id) {
                    CloudinaryHelper::deleteImage($member->image_cloudinary_id);
                }

                // Upload new image
                $uploadResult = CloudinaryHelper::uploadImage(
                    $request->file('image'),
                    'maxreward/members/images'
                );

                // Add image data to validated data
                $validatedData['image'] = $uploadResult['url'];
                $validatedData['image_cloudinary_id'] = $uploadResult['public_id'];
            }

            // Update only the fields that are present in the request
            $member->update($validatedData);

            // Commit transaction
            DB::commit();

            // Reload member with relationships
            $member->load(['wallet', 'merchant']);

            return response()->json([
                'success' => true,
                'message' => 'Member updated successfully',
                'data' => $member
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Member not found'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update member',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function updateStatus(Request $request, $id){
        try {
            $member = Member::findOrFail($id);
            $member->status = $request->status;
            $member->save();
            return response()->json([
                'success' => true,
                'message' => 'Member status updated successfully',
                'data' => $member
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found'
            ], 404);
        }
    }


     public function checkRedeemAmount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'redeem_amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {

            $id = auth()->user()->id;

            $memberWallet = MemberWallet::where('member_id', $id)->firstOrFail();

            if ($memberWallet->available_points < $request->redeem_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient points for redemption'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sufficient points for redemption',
                'data' => $memberWallet->available_points
            ], 200);




        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Merchant not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check redeem amount',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get member dashboard statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardStats()
    {
        try {
            // Get total members count
            $totalMembers = Member::count();

            // Get total merchants count
            $totalMerchants = \App\Models\Merchant::count();

            // Get total transactions count
            $totalTransactions = \App\Models\Transaction::count();

            // Get total merchant approvals (merchants with 'approved' status)
            $totalMerchantApprovals = \App\Models\Merchant::where('status', 'approved')->count();

            return response()->json([
                'success' => true,
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => [
                    'total_members' => $totalMembers,
                    'total_merchants' => $totalMerchants,
                    'total_transactions' => $totalTransactions,
                    'total_merchant_approvals' => $totalMerchantApprovals
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function makePurchase(Request $request)
    {
        try {
            // 1. validation
            $validatedData = $request->validate([
                'merchant_id'        => 'required|exists:merchants,id',
                'transaction_amount' => 'required|numeric|min:0.01',
                'redeem_amount'      => 'required|numeric|min:0',
                'cash_redeem_amount' => 'nullable|numeric|min:0',
                'payment_method'     => 'required|in:online,offline',
                'status'             => 'required|in:pending,approved,rejected',
                'merchant_selection_type' => 'required|in:qrcode,unique_number,merchant_name',
            ]);

            // 2. member & wallet check
            $memberId = auth()->user()->id;
            $memberWallet = MemberWallet::where('member_id', $memberId)->firstOrFail();

            if ($memberWallet->available_points < $validatedData['redeem_amount']) {
                return response()->json(['success'=>false,'message'=>'Insufficient points for redemption'], 400);
            }

            // 3. setting check
            $settingInfo = Setting::first()?->setting_attribute ?? [];
            if (!$settingInfo) {
                return response()->json(['success'=>false,'message'=>'Setting info not found'], 400);
            }

            $cashRedeemAmount = $validatedData['transaction_amount'] - $validatedData['redeem_amount'];
            $cashRedeemAmount = $cashRedeemAmount * $settingInfo['maxreward']['rm_points'];
            if ($validatedData['cash_redeem_amount'] != $cashRedeemAmount) {
                return response()->json(['success'=>false,'message'=>'Cash redeem amount does not match']);
            }

            // 4. Generate transaction id safely using TransactionCounter
            // Use DB transaction to keep purchase creation atomic (good practice)
            $purchase = DB::transaction(function () use ($validatedData, $memberId) {
                // Insert counter row and get id (DB handles concurrency)
                $counterId = DB::table('transaction_counters')->insertGetId([
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Create TXN starting from 2001: counterId=1 -> 2001
                $transactionNumber = $counterId + 2000; // 1 -> 2001
                $transaction_id = 'TXN-' . str_pad($transactionNumber, 4, '0', STR_PAD_LEFT);

                // Create purchase record
                $purchase = Purchase::create([
                    'member_id'          => $memberId,
                    'merchant_id'        => $validatedData['merchant_id'],
                    'transaction_id'     => $transaction_id,
                    'transaction_amount' => $validatedData['transaction_amount'],
                    'redeem_amount'      => $validatedData['redeem_amount'],
                    'cash_redeem_amount' => $validatedData['cash_redeem_amount'] ?? 0,
                    'payment_method'     => $validatedData['payment_method'],
                    'status'             => $validatedData['status'] ?? 'pending',
                    'merchant_selection_type' => $validatedData['merchant_selection_type'],
                ]);

                return $purchase;
            }, 5); // optional 5 attempts for transaction deadlock retry

            return response()->json([
                'success' => true,
                'message' => 'Purchase successful',
                'data'    => $purchase
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success'=>false,'message'=>'Validation failed','errors'=>$e->errors()], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success'=>false,'message'=>'Member wallet or merchant not found'], 404);

        } catch (\Exception $e) {
            return response()->json(['success'=>false,'message'=>'Failed to make purchase','error'=>$e->getMessage()], 500);
        }
    }


    public function MemberController(Request $request)
    {
        $memberIds = $request->member_ids; 

        if($memberIds.length > 0){
            foreach($memberIds as $id){
                try {
                    $member = Member::findOrFail($id);
                    $member->status = $request->status;
                    $member->save();
                } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                    return response()->json([
                        'success' => false,
                        'message' => "Member with ID {$id} not found"
                    ], 404);
                }
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No member IDs provided'
            ], 400);
        }
    }


    public function statusBlockSuspend(Request $request){
        try {
            $member = Member::findOrFail($request->member_id);
            $member->status = $request->status;
            if($request->status == 'blocked'){
                $member->block_reason = $request->reason;
            }
            if($request->status == 'suspended'){
                $member->suspended_reason = $request->reason;
            }
            $member->save();
            return response()->json([
                'success' => true,
                'message' => 'Member status updated successfully',
                'data' => $member
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found'
            ], 404);
        }
    }
    

    public function getSingleVoucher(Request $request){
        try {
            $voucher = Voucher::with('merchant')->findOrFail($request->id);
            return response()->json([
                'success' => true,
                'message' => 'Voucher retrieved successfully',
                'data' => $voucher
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found'
            ], 404);
        }
    }

    /**
     * Update member profile (for authenticated member)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        try {
            // Start database transaction
            DB::beginTransaction();

            // Get authenticated member
            $member = auth()->user();

            // Validate request
            $validatedData = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|max:255|unique:members,email,' . $member->id,
                'address' => 'sometimes|string|max:500',
                'image' => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:5120',
            ]);

            // Handle image upload to Cloudinary
            if ($request->hasFile('image')) {
                // Delete old image from Cloudinary if exists
                if ($member->image_cloudinary_id) {
                    CloudinaryHelper::deleteImage($member->image_cloudinary_id);
                }

                // Upload new image
                $uploadResult = CloudinaryHelper::uploadImage(
                    $request->file('image'),
                    'maxreward/members/images'
                );

                // Add image data to validated data
                $validatedData['image'] = $uploadResult['url'];
                $validatedData['image_cloudinary_id'] = $uploadResult['public_id'];
            }

            // Update only the fields that are present in the request
            $member->update($validatedData);

            // Commit transaction
            DB::commit();

            // Reload member with relationships
            $member->load(['wallet', 'merchant']);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $member
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

 
    public function changePassword(Request $request)
    {
        try {
            $member = auth()->user();

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
            if (!Hash::check($request->password, $member->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 401);
            }

            // Check if new password is same as current password
            if (Hash::check($request->new_password, $member->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'New password cannot be the same as current password'
                ], 400);
            }

            // Update password
            $member->password = Hash::make($request->new_password);
            $member->save();

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