<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberWallet;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\CpTransaction;
use App\Models\MemberCommunityPoint;
use App\Models\CompanyInfo;
use App\Models\CpLevelConfig;
use App\Models\Notification;
use App\Models\CpUnlockHistory;
use App\Services\CommunityTreeService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Traits\MemberHelperTrait;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use App\Helpers\CommonFunctionHelper;
use App\Models\Merchant;
use App\Traits\PointDistributionTrait; 

class ReferralController extends Controller
{
    use MemberHelperTrait, PointDistributionTrait;

    protected $treeService;
    protected $whatsappService;
    protected $settingAttributes;

    public function __construct(CommunityTreeService $treeService, WhatsAppService $whatsappService, CommonFunctionHelper $commonFunctionHelper) {
        $this->treeService = $treeService;
        $this->whatsappService = $whatsappService;
        $this->settingAttributes = $commonFunctionHelper->settingAttributes()['maxreward'];
    }

    /**
     * Refer a new member (Both General & Corporate Members can use this)
     * 
     * POST /api/refer-new-member
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function referNewMember(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:11|unique:members,phone',
            'email' => 'nullable|email|unique:members,email',
            'gender_type' => 'nullable|in:male,female,others',
            'address' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {

            DB::beginTransaction();

            // Get authenticated (referrer)

            $referrer = '';
            $auth = auth()->user(); // Can be general or corporate member
            // dd($referrer);
            if ($auth->member_type == "general" || $auth->member_type == "corporate") {
                $referrer = $auth;
            }
            
            if ($auth->type == "merchant" || $auth->type == "staff") {
                $merchant_info = Merchant::where("id", $auth->merchant_id)->first();
                $member_info = Member::where("merchant_id", $merchant_info->id)->first();
                $referrer = $member_info;
            }

            if ($auth->type == "admin" || $auth->type == "staff") {
                if ($request->has('member_id')) {
                    $referrer = Member::where("id", $request->member_id)->first();
                }
                
            }

            // dd("ok", $referrer);

            $referrerWallet = $referrer->wallet;

            Log::info('Step 1: Check if referrer has sufficient referral balance (>= 100)');

            // Step 1: Check if referrer has sufficient referral balance (>= 100)
            if ($referrerWallet->total_rp < $this->settingAttributes['deductable_points']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient referral balance. You need at least 100 RP to refer a new member.',
                    'current_balance' => $referrerWallet->total_rp,
                    'required_balance' => $this->settingAttributes['deductable_points'],
                ], 400);
            }

            Log::info('Step 2: Generate credentials for new member');

            // Step 2: Generate credentials for new member
            // $password = Str::random(8); // Random password
            $referralCode = $this->generateUniqueReferralCode(); // this function coming from MemberHelperTrait
            $userName = $this->formatPhoneNumber($request->phone); // this function coming from MemberHelperTrait
            $lastSix = substr($userName, -6);
            // $prefix = Str::upper(Str::random(2));
            // $password = $prefix . $lastSix;
            $password = $lastSix;

            Log::info('Step 3: Create new member');

            // Step 3: Create new member
            $newMember = Member::create([
                'user_name' => $userName,
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'password' => Hash::make($password),
                'address' => $request->address,
                'member_type' => 'general', // New members are always general
                'gender_type' => $request->gender_type,
                'status' => 'active',
                'merchant_id' => null,
                'member_created_by' => $referrer->member_type, // 'general' or 'corporate'
                'referral_code' => $referralCode,
            ]);

            Log::info('Step 4: Create wallet for new member');

            // Step 4: Create wallet for new member
            $newMemberWallet = MemberWallet::create([
                'member_id' => $newMember->id,
                'total_referrals' => 0,
                'unlocked_level' => 5, // Default: levels 1-5 unlocked
                'onhold_points' => 0,
                'total_points' => 0,
                'available_points' => 0,
                'total_rp' => 0,
                'total_pp' => 0,
                'total_cp' => 0,
            ]);

            Log::info('Step 5: Deduct 100 RP from referrer');

            // Step 5: Deduct 100 RP from referrer
            $referrerWallet->total_rp -= $this->settingAttributes['deductable_points'];
            $referrerWallet->save();

            Log::info('createTransaction for referrer ID');

            Transaction::createTransaction([
                'member_id' => $referrer->id,
                'transaction_points' => 100,
                'transaction_type' => Transaction::TYPE_RP,
                'points_type' => Transaction::POINTS_DEBITED,
                'transaction_reason' => "Referred new member: {$newMember->name}",
            ]);

            Log::info('Step 6: Distribute 100 points (PP:10, RP:20, CP:50, CR:20)');

            // Step 6: Distribute 100 points (PP:10, RP:20, CP:50, CR:20)
            $this->distributeReferralPoints($referrer, $newMember, $this->settingAttributes['deductable_points']);

            Log::info('Step 7: Place new member in community tree');

            // Step 7: Place new member in community tree
            $placement = $this->treeService->placeInCommunityTree($referrer->id, $newMember->id);

            if (!$placement['success']) {
                throw new \Exception('Failed to place member in community tree');
            }

            Log::info('Step 8: Update referrers referral count');

            // Step 8: Update referrer's referral count
            $referrerWallet->increment('total_referrals');

            Log::info('Step 9: Check and unlock CP levels if needed');

            // Step 9: Check and unlock CP levels if needed
            $this->checkAndUnlockCpLevels($referrer->id);

            Log::info('Step 10: Send WhatsApp message to new member');

            // Step 10: Send WhatsApp message to new member
            $this->whatsappService->sendWelcomeMessage([
                'member_id' => $newMember->id,
                'referrer_id' => $referrer->id,
                'name' => $newMember->name,
                'phone' => $newMember->phone,
                'user_name' => $userName,
                'password' => $password, // Send plain text password for WhatsApp message
                'login_url' => env('APP_URL') . '/login',
            ]);

            Log::info('Step 11: Create notifications');

            // Step 11: Create notifications
            Notification::notifyReferralInvite($referrer->id, [
                'new_member_name' => $newMember->name,
                'new_member_phone' => $newMember->phone,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'New member registered successfully!',
                'data' => [
                    'new_member' => [
                        'id' => $newMember->id,
                        'name' => $newMember->name,
                        'phone' => $newMember->phone,
                        'user_name' => $userName,
                        'referral_code' => $referralCode,
                    ],
                    'referrer' => [
                        'id' => $referrer->id,
                        'name' => $referrer->name,
                        'remaining_rp_balance' => $referrerWallet->total_rp,
                        'total_referrals' => $referrerWallet->total_referrals,
                    ],
                    'placement' => [
                        'level' => $placement['level'],
                        'parent_id' => $placement['placement_parent_id'],
                    ],
                    'credentials' => [
                        'user_name' => $userName,
                        'password' => $password,
                        'message' => 'Login credentials sent via WhatsApp',
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to register new member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Distribute 100 referral points:
     * PP: 10 (to new member)
     * RP: 20 (to who sponsored)
     * CP: 50 (distributed across 30 levels)
     * CR: 20 (to company reserve)
     */
    private function distributeReferralPoints($referrer, $newMember, $totalPoints = 100)
    {
        Log::info('1️ PP: 10 points to NEW MEMBER');

        // 1️ PP: 10 points to NEW MEMBER
        $ppAmount = $totalPoints * ($this->settingAttributes['pp_points']/100); // 10 points
        $newMemberWallet = $newMember->wallet;
        $newMemberWallet->total_pp += $ppAmount;
        $newMemberWallet->available_points += $ppAmount;
        $newMemberWallet->total_points += $ppAmount;
        $newMemberWallet->save();

        Log::info('transaction_reason: Personal Points from registration');

        Transaction::createTransaction([
            'member_id' => $newMember->id,
            'transaction_points' => $ppAmount,
            'transaction_type' => Transaction::TYPE_PP,
            'points_type' => Transaction::POINTS_CREDITED,
            'transaction_reason' => 'Personal Points from registration',
        ]);

        Log::info('2️ RP: 20 points to who Directly sponsored');

        // 2️ RP: 20 points to who Directly sponsored
        $rpAmount = $totalPoints * ($this->settingAttributes['rp_points']/100); // 20 points
        $sponsor = Member::where('id', $referrer->id)->first();
        
        if ($sponsor) {

            Log::info('Step :: sponsor');

            $sponsorWallet = $sponsor->wallet;
            // $sponsorWallet->total_rp += $rpAmount;
            $sponsorWallet->available_points += $rpAmount;
            $sponsorWallet->total_points += $rpAmount;
            $sponsorWallet->save();

            Log::info('Step :: createTransaction for sponsor');

            Transaction::createTransaction([
                'member_id' => $sponsor->id,
                'referral_member_id' => $newMember->id,
                'transaction_points' => $rpAmount,
                'transaction_type' => Transaction::TYPE_RP,
                'points_type' => Transaction::POINTS_CREDITED,
                'transaction_reason' => "Referral Points from {$newMember->name}'s registration",
            ]);

            Log::info('Step :: Referral points earned notification');

            Notification::createForMember([
                'member_id' => $sponsor->id,
                'type' => 'referral_points_earned',
                'title' => 'Referral Points Earned!',
                'message' => "You earned {$rpAmount} RP from {$newMember->name}'s registration.",
            ]);
        }

        Log::info('3️ CP: 50 points distributed across 30-level community tree');

        // 3️ CP: 50 points distributed across 30-level community tree
        $cpAmount = $totalPoints * ($this->settingAttributes['cp_points']/100); // 50 points
        $this->distributeCommunityPoints($referrer->id, $newMember->id, $cpAmount, 'registration');

        Log::info('4️ CR: 20 points to Company Reserve');

        // 4️ CR: 20 points to Company Reserve
        $crAmount = $totalPoints * ($this->settingAttributes['cr_points']/100); // 20 points
        $company = CompanyInfo::getCompany();
        $company->incrementCrPoint($crAmount);

        Log::info('Company Reserve from '.$newMember->name.'s registration');
        
        Transaction::createTransaction([
            'member_id' => null,
            'transaction_points' => $crAmount,
            'transaction_type' => Transaction::TYPE_CR,
            'points_type' => Transaction::POINTS_CREDITED,
            'transaction_reason' => "Company Reserve from {$newMember->name}'s registration",
        ]);
    }

    /**
     * Distribute Community Points (CP) across 30 levels
     * Based on CpLevelConfig distribution rules
     */
    // private function OLD_distributeCommunityPoints($sourceMemberId, $newMemberId, $totalCp)
    // {
    //     Log::info('Start :: Distribute Community Points (CP) across 30 levels');
    //     // Get upline path (30 levels max)
    //     $uplinePath = Referral::getReferralPath($sourceMemberId, 30);

    //     foreach ($uplinePath as $node) {
    //         $level = $node['level'];
    //         $receiverMemberId = $node['member_id'];

    //         // Get CP percentage for this level
    //         $cpPercentage = CpLevelConfig::getCpPercentageForLevel($level);
    //         $cpAmount = ($totalCp * $cpPercentage) / 100;

    //         if ($cpAmount <= 0) {
    //             continue;
    //         }

    //         // Get receiver's wallet
    //         $receiverWallet = MemberWallet::where('member_id', $receiverMemberId)->first();
            
    //         if (!$receiverWallet) {
    //             continue;
    //         }

    //         // Check if this level is locked for the receiver
    //         $isLocked = $level > $receiverWallet->unlocked_level;

    //         Log::info('Create CP transaction record');

    //         // Create CP transaction record
    //         CpTransaction::createCpTransaction([
    //             'purchase_id' => null, // This is for referral, not purchase
    //             'source_member_id' => $sourceMemberId,
    //             'receiver_member_id' => $receiverMemberId,
    //             'level' => $level,
    //             'cp_percentage' => $cpPercentage,
    //             'cp_amount' => $cpAmount,
    //             'is_locked' => $isLocked,
    //         ]);

    //         // Update member's CP wallet
    //         $mcp = MemberCommunityPoint::getOrCreateForLevel($receiverMemberId, $level, $isLocked);
    //         $mcp->addCp($cpAmount, $isLocked);

    //         // Update main wallet
    //         $receiverWallet->total_cp += $cpAmount;
    //         $receiverWallet->total_points += $cpAmount;

    //         if ($isLocked) {
    //             $receiverWallet->onhold_points += $cpAmount;
    //         } else {
    //             $receiverWallet->available_points += $cpAmount;
    //         }

    //         $receiverWallet->save();

    //         // Create transaction record
    //         Transaction::createTransaction([
    //             'member_id' => $receiverMemberId,
    //             'referral_member_id' => $newMemberId,
    //             'transaction_points' => $cpAmount,
    //             'transaction_type' => Transaction::TYPE_CP,
    //             'points_type' => Transaction::POINTS_CREDITED,
    //             'transaction_reason' => "Community Points (Level {$level}) from {$newMemberId}'s registration" . ($isLocked ? ' [ON HOLD]' : ''),
    //         ]);

    //         // Notification
    //         Notification::createForMember([
    //             'member_id' => $receiverMemberId,
    //             'type' => 'community_points_earned',
    //             'title' => 'Community Points Earned!',
    //             'message' => "You earned {$cpAmount} CP at Level {$level}" . ($isLocked ? ' (On Hold - unlock more levels to access)' : ''),
    //         ]);
    //     }
    // }



    /**
     * Check and unlock CP levels based on referral count
     * 
     * Unlock Logic:
     * - 0 referrals: Level 1-5
     * - 1 referral: Level 1-10
     * - 2 referrals: Level 1-15
     * - 3 referrals: Level 1-20
     * - 4 referrals: Level 1-25
     * - 5+ referrals: Level 1-30
     */
    private function checkAndUnlockCpLevels($memberId)
    {
        Log::info('Start :: Check and unlock CP levels based on referral count');

        $wallet = MemberWallet::where('member_id', $memberId)->first();
        
        if (!$wallet) {
            return;
        }

        $totalReferrals = $wallet->total_referrals;
        $currentUnlockedLevel = $wallet->unlocked_level;

        // Determine new unlock level
        $newUnlockedLevel = match(true) {
            $totalReferrals >= 5 => 30,
            $totalReferrals == 4 => 25,
            $totalReferrals == 3 => 20,
            $totalReferrals == 2 => 15,
            $totalReferrals == 1 => 10,
            default => 5,
        };

        // If new level is higher, unlock
        if ($newUnlockedLevel > $currentUnlockedLevel) {
            $previousLevel = $currentUnlockedLevel;
            $wallet->unlocked_level = $newUnlockedLevel;
            $wallet->save();

            // Release locked CP for newly unlocked levels
            $releasedCp = MemberCommunityPoint::unlockLevels(
                $memberId,
                $previousLevel + 1,
                $newUnlockedLevel
            );

            // Update wallet: move from onhold to available
            if ($releasedCp > 0) {
                $wallet->onhold_points -= $releasedCp;
                $wallet->available_points += $releasedCp;
                $wallet->save();

                // Create unlock history
                CpUnlockHistory::createUnlockRecord([
                    'member_id' => $memberId,
                    'previous_referrals' => $totalReferrals - 1,
                    'new_referrals' => $totalReferrals,
                    'previous_unlocked_level' => $previousLevel,
                    'new_unlocked_level' => $newUnlockedLevel,
                    'released_cp_amount' => $releasedCp,
                ]);

                // Notification
                Notification::notifyCpUnlock($memberId, [
                    'from_level' => $previousLevel + 1,
                    'to_level' => $newUnlockedLevel,
                    'released_cp' => $releasedCp,
                ]);
            }
        }
    }


    /**
     * Format phone number as user_name
     */
    /**
     * Validate and format Malaysian phone number (must start with 01 and be 10–11 digits, no hyphens)
     */
    // private function formatPhoneNumber($phone)
    // {
    //     // Remove any non-digit characters
    //     $cleaned = preg_replace('/[^0-9]/', '', $phone);

    //     // Validate: must start with 01 and have 10 or 11 digits
    //     if (!preg_match('/^01\d{8,9}$/', $cleaned)) {
    //         throw new \InvalidArgumentException('Invalid Malaysian phone number. Must start with 01 and be 10–11 digits.');
    //     }

    //     return $cleaned;
    // }


    /**
     * Get referral tree for authenticated members tree (30 levels)
     * 
     * GET /api/referral-tree
     */
    // public function OLD_getReferralTree(Request $request)
    // {
    //     try {
    //         $member = auth()->user();
    //         $tree = Referral::getReferralTree($member->id, 30);
    //         $statistics = $this->treeService->getTreeStatistics($member->id);

    //         // Format tree with member details
    //         $formattedTree = [];
    //         foreach ($tree as $level => $memberIds) {

    //             $levelData = [
    //                 'level' => $level,
    //                 'member_count' => count($memberIds),
    //                 'members' => []
    //             ];

    //             // $members = Member::with('wallet')->whereIn('id', $memberIds)->get();
    //             $members = Member::whereIn('id', $memberIds)->get();
                
    //             $levelData['members'] = $members->map(function($m) use ($level) {
    //                 return [
    //                     'id' => $m->id,
    //                     'name' => $m->name,
    //                     'user_name' => $m->user_name,
    //                     'phone' => $m->phone,
    //                     'member_type' => $m->member_type,
    //                     'status' => $m->status,
    //                     'referral_code' => $m->referral_code,
    //                     // 'wallet' => [
    //                     //     'total_points' => round($m->wallet->total_points, 2),
    //                     //     'available_points' => round($m->wallet->available_points, 2),
    //                     //     'onhold_points' => round($m->wallet->onhold_points, 2),
    //                     //     'total_referrals' => $m->wallet->total_referrals,
    //                     // ],
    //                     'level_in_tree' => $level,
    //                 ];
    //             });

    //             $formattedTree[] = $levelData;
    //         }
    //         // return response()->json([
    //         //     'success' => true,
    //         //     'data' => [
    //         //         'tree' => $tree,
    //         //         'statistics' => $statistics,
    //         //     ]
    //         // ]);
    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Member tree retrieved successfully',
    //             'data' => [
    //                 'root_member' => [
    //                     'id' => $member->id,
    //                     'name' => $member->name,
    //                     'user_name' => $member->user_name,
    //                     'referral_code' => $member->referral_code,
    //                 ],
    //                 'statistics' => [
    //                     'total_members' => $statistics['total_members'],
    //                     'deepest_level' => $statistics['deepest_level'],
    //                     // 'by_level' => $statistics['by_level'],
    //                     // 'width_at_each_level' => $statistics['width_at_each_level'],
    //                 ],
    //                 'tree' => $formattedTree,
    //             ]
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to retrieve referral tree',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    /**
     * Get referral tree for authenticated members tree (30 levels) with complete structure
     * 
     * GET /api/referral-tree
     */
    public function getReferralTree(Request $request)
    {
        try {
            $member = auth()->user();
            
            // // Get tree structure with positions
            // $treeStructure = Referral::getBinaryTreeStructure($member->id, 30);
            // $statistics = $this->treeService->getTreeStatistics($member->id);

            // // Format tree with member details and positions
            // $formattedTree = [];
            
            // foreach ($treeStructure as $level => $levelNodes) {
            //     $levelData = [
            //         'level' => $level,
            //         'node_count' => count($levelNodes),
            //         'nodes' => []
            //     ];

            //     foreach ($levelNodes as $node) {
            //         $parentMember = Member::find($node['parent_id']);
            //         $leftChildMember = $node['left_child'] ? Member::find($node['left_child']) : null;
            //         $rightChildMember = $node['right_child'] ? Member::find($node['right_child']) : null;
                    
            //         $nodeInfo = [
            //             'parent' => [
            //                 'id' => $parentMember->id,
            //                 'name' => $parentMember->name,
            //                 'user_name' => $parentMember->user_name,
            //                 'phone' => $parentMember->phone,
            //                 'referral_code' => $parentMember->referral_code,
            //                 'image' => $parentMember->image,
            //             ],
            //             'left_child' => $leftChildMember ? [
            //                 'id' => $leftChildMember->id,
            //                 'name' => $leftChildMember->name,
            //                 'user_name' => $leftChildMember->user_name,
            //                 'phone' => $leftChildMember->phone,
            //                 'referral_code' => $leftChildMember->referral_code,
            //                 'image' => $leftChildMember->image,
            //                 'position' => 'left'
            //             ] : null,
            //             'right_child' => $rightChildMember ? [
            //                 'id' => $rightChildMember->id,
            //                 'name' => $rightChildMember->name,
            //                 'user_name' => $rightChildMember->user_name,
            //                 'phone' => $rightChildMember->phone,
            //                 'referral_code' => $rightChildMember->referral_code,
            //                 'image' => $rightChildMember->image,
            //                 'position' => 'right'
            //             ] : null
            //         ];
                    
            //         $levelData['nodes'][] = $nodeInfo;
            //     }

            //     $formattedTree[] = $levelData;
            // }

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
                'message' => 'Failed to retrieve referral tree',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get list of parent node members
     * 
     * GET /api/parent-node-members
     */
    public function parentNodeMembers(Request $request)
    {
        try {
            $member = auth()->user();
            
            // $referrals = Referral::with(['childMember.wallet'])
            $referrals = Referral::with(['childMember'])
                ->where('parent_member_id', $member->id)
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $referrals
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve referred members',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get members I personally sponsored (not just tree children)
     */
    public function getMySponsoredMembers(Request $request)
    {
        try {

            $member = auth()->user();

            $sponsored = CommonFunctionHelper::sponsoredMembers($member->id);

            return response()->json([
                'success' => true,
                'message' => 'Members you personally sponsored',
                'data' => [ "sponsored" => $sponsored ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sponsored members',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get upline members (up to 30 levels) for a specific member
     * 
     * GET /api/member/{memberId}/upline
     * or
     * GET /api/member/upline?level=30
     */

    public function getUplineMembers(Request $request, $memberId = null)
    {
        try {
            // If memberId not provided in URL, use authenticated user
            $targetMemberId = $memberId ?? auth()->user()->id;
 
            // Get level limit from request (default: 30)
            $levelLimit = $request->get('level', 30);

            $levelLimit = min($levelLimit, 30); // Max 30 levels

            Log::info("Getting upline members for member", [
                'member_id' => $targetMemberId,
                'level_limit' => $levelLimit
            ]);
    
            // Get upline path using the same method as distributeCommunityPoints
            $uplinePath = Referral::getReferralPath($targetMemberId, $levelLimit);
    
            Log::info("Upline path retrieved", [
                'member_id' => $targetMemberId,
                'upline_levels_count' => count($uplinePath)
            ]);
            // dd(count($uplinePath));
            // Format response with member details
            $formattedUpline = [];
            $totalUplineMembers = 0;
    
            foreach ($uplinePath as $node) {
                $level = $node['level'];
                $uplineMemberId = $node['member_id'];
    
                // Get member details
                $uplineMember = Member::find($uplineMemberId);
                
                if (!$uplineMember) {
                    continue;
                }
    
                $formattedUpline[] = [
                    'level' => $level,
                    'member' => [
                        'id' => $uplineMember->id,
                        'name' => $uplineMember->name,
                        'user_name' => $uplineMember->user_name,
                        'phone' => $uplineMember->phone,
                        'email' => $uplineMember->email,
                        'member_type' => $uplineMember->member_type,
                        'referral_code' => $uplineMember->referral_code,
                        'status' => $uplineMember->status
                    ],
                    'distance_from_target' => $level . ' level' . ($level > 1 ? 's' : '') . ' up'
                ];
    
                $totalUplineMembers++;
            }
    
            // Sort by level (closest first)
            usort($formattedUpline, function($a, $b) {
                return $a['level'] <=> $b['level'];
            });
    
            return response()->json([
                'success' => true,
                'message' => 'Upline members retrieved successfully',
                'data' => [
                    'target_member' => [
                        'id' => $targetMemberId,
                        'name' => Member::find($targetMemberId)->name ?? 'Unknown',
                    ],
                    'upline_statistics' => [
                        'total_upline_members' => $totalUplineMembers,
                        'levels_retrieved' => count($uplinePath),
                        'max_levels_possible' => 30,
                    ],
                    'upline_members' => $formattedUpline
                ]
            ]);
    
        } catch (\Exception $e) {
            Log::error('Failed to retrieve upline members: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve upline members',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}