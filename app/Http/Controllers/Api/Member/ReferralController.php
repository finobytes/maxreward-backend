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

class ReferralController extends Controller
{
    use MemberHelperTrait;

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

            // Get authenticated member (referrer)
            $referrer = auth()->user(); // Can be general or corporate member
            $referrerWallet = $referrer->wallet;

            Log::info('Referrer ID: ' . $referrer->id);

            // ✅ Step 1: Check if referrer has sufficient referral balance (>= 100)
            if ($referrerWallet->total_rp < $this->settingAttributes['deductable_points']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient referral balance. You need at least 100 RP to refer a new member.',
                    'current_balance' => $referrerWallet->total_rp,
                    'required_balance' => $this->settingAttributes['deductable_points'],
                ], 400);
            }
            Log::info('Referrer ID 2: ' . $referrer->id);

            // ✅ Step 2: Generate credentials for new member
            $password = Str::random(8); // Random password
            $referralCode = $this->generateUniqueReferralCode(); // this function coming from MemberHelperTrait
            $userName = $this->formatPhoneNumber($request->phone);

            Log::info('Referrer ID 3: ' . $referrer->id);

            // ✅ Step 3: Create new member
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

            Log::info('Referrer ID 4: ' . $referrer->id);

            // ✅ Step 4: Create wallet for new member
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

            // ✅ Step 5: Deduct 100 RP from referrer
            $referrerWallet->total_rp -= $this->settingAttributes['deductable_points'];
            $referrerWallet->save();

            Log::info('Referrer ID 5: ' . $referrer->id);

            Transaction::createTransaction([
                'member_id' => $referrer->id,
                'transaction_points' => 100,
                'transaction_type' => Transaction::TYPE_RP,
                'points_type' => Transaction::POINTS_DEBITED,
                'transaction_reason' => "Referred new member: {$newMember->name}",
            ]);

            Log::info('Referrer ID 6: ' . $referrer->id);

            // ✅ Step 6: Distribute 100 points (PP:10, RP:20, CP:50, CR:20)
            $this->distributeReferralPoints($referrer, $newMember, $this->settingAttributes['deductable_points']);

            Log::info('Referrer ID 7: ' . $referrer->id);

            // ✅ Step 7: Place new member in community tree
            $placement = $this->treeService->placeInCommunityTree($referrer->id, $newMember->id);

            Log::info('Referrer ID 8: ' . $referrer->id);

            if (!$placement['success']) {
                throw new \Exception('Failed to place member in community tree');
            }

            Log::info('Referrer ID 8: ' . $referrer->id);

            // ✅ Step 8: Update referrer's referral count
            $referrerWallet->increment('total_referrals');

            // ✅ Step 9: Check and unlock CP levels if needed
            $this->checkAndUnlockCpLevels($referrer->id);

            // ✅ Step 10: Send WhatsApp message to new member
            $this->whatsappService->sendWelcomeMessage([
                'member_id' => $newMember->id,
                'referrer_id' => $referrer->id,
                'name' => $newMember->name,
                'phone' => $newMember->phone,
                'user_name' => $userName,
                'password' => Hash::make($password),
                'login_url' => env('APP_URL') . '/login',
            ]);

            // ✅ Step 11: Create notifications
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
     * RP: 20 (to referrer's direct upline)
     * CP: 50 (distributed across 30 levels)
     * CR: 20 (to company reserve)
     */
    private function distributeReferralPoints($referrer, $newMember, $totalPoints = 100)
    {
        // 1️⃣ PP: 10 points to NEW MEMBER
        $ppAmount = $totalPoints * ($this->settingAttributes['pp_points']/100); // 10 points
        $newMemberWallet = $newMember->wallet;
        $newMemberWallet->total_pp += $ppAmount;
        $newMemberWallet->available_points += $ppAmount;
        $newMemberWallet->total_points += $ppAmount;
        $newMemberWallet->save();

        Log::info('Referrer ID 9: ' . $referrer->id);

        Transaction::createTransaction([
            'member_id' => $newMember->id,
            'transaction_points' => $ppAmount,
            'transaction_type' => Transaction::TYPE_PP,
            'points_type' => Transaction::POINTS_CREDITED,
            'transaction_reason' => 'Personal Points from registration',
        ]);

        Log::info('Referrer ID 10: ' . $referrer->id);

        // 2️⃣ RP: 20 points to REFERRER'S DIRECT UPLINE
        $rpAmount = $totalPoints * ($this->settingAttributes['rp_points']/100); // 20 points
        $referrerUpline = Referral::where('child_member_id', $referrer->id)->first();
        
        if ($referrerUpline && $referrerUpline->parentMember) {
            Log::info('Referrer ID 11: ' . $referrer->id);
            $uplineWallet = $referrerUpline->parentMember->wallet;
            $uplineWallet->total_rp += $rpAmount;
            $uplineWallet->available_points += $rpAmount;
            $uplineWallet->total_points += $rpAmount;
            $uplineWallet->save();
            Log::info('Referrer ID 12: ' . $referrer->id);
            Transaction::createTransaction([
                'member_id' => $referrerUpline->parent_member_id,
                'referral_member_id' => $newMember->id,
                'transaction_points' => $rpAmount,
                'transaction_type' => Transaction::TYPE_RP,
                'points_type' => Transaction::POINTS_CREDITED,
                'transaction_reason' => "Referral Points from {$newMember->name}'s registration",
            ]);
            Log::info('Referrer ID 13: ' . $referrer->id);
            Notification::createForMember([
                'member_id' => $referrerUpline->parent_member_id,
                'type' => 'referral_points_earned',
                'title' => 'Referral Points Earned!',
                'message' => "You earned {$rpAmount} RP from {$newMember->name}'s registration.",
            ]);
        }
        Log::info('distributeCommunityPoints');
        // 3️⃣ CP: 50 points distributed across 30-level community tree
        $cpAmount = $totalPoints * ($this->settingAttributes['cp_points']/100); // 50 points
        $this->distributeCommunityPoints($referrer->id, $newMember->id, $cpAmount);
        Log::info('Transaction');
        // 4️⃣ CR: 20 points to Company Reserve
        $crAmount = $totalPoints * ($this->settingAttributes['cr_points']/100); // 20 points
        $company = CompanyInfo::getCompany();
        $company->incrementCrPoint($crAmount);
        Log::info('createTransaction');
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
    private function distributeCommunityPoints($sourceMemberId, $newMemberId, $totalCp)
    {
        // Get upline path (30 levels max)
        $uplinePath = Referral::getReferralPath($sourceMemberId, 30);

        foreach ($uplinePath as $node) {
            $level = $node['level'];
            $receiverMemberId = $node['member_id'];

            // Get CP percentage for this level
            $cpPercentage = CpLevelConfig::getCpPercentageForLevel($level);
            $cpAmount = ($totalCp * $cpPercentage) / 100;

            if ($cpAmount <= 0) {
                continue;
            }

            // Get receiver's wallet
            $receiverWallet = MemberWallet::where('member_id', $receiverMemberId)->first();
            
            if (!$receiverWallet) {
                continue;
            }

            // Check if this level is locked for the receiver
            $isLocked = $level > $receiverWallet->unlocked_level;

            Log::info('createCpTransaction');

            // Create CP transaction record
            CpTransaction::createCpTransaction([
                'purchase_id' => null, // This is for referral, not purchase
                'source_member_id' => $sourceMemberId,
                'receiver_member_id' => $receiverMemberId,
                'level' => $level,
                'cp_percentage' => $cpPercentage,
                'cp_amount' => $cpAmount,
                'is_locked' => $isLocked,
            ]);

            // Update member's CP wallet
            $mcp = MemberCommunityPoint::getOrCreateForLevel($receiverMemberId, $level);
            $mcp->addCp($cpAmount, $isLocked);

            // Update main wallet
            $receiverWallet->total_cp += $cpAmount;
            $receiverWallet->total_points += $cpAmount;

            if ($isLocked) {
                $receiverWallet->onhold_points += $cpAmount;
            } else {
                $receiverWallet->available_points += $cpAmount;
            }

            $receiverWallet->save();

            // Create transaction record
            Transaction::createTransaction([
                'member_id' => $receiverMemberId,
                'referral_member_id' => $newMemberId,
                'transaction_points' => $cpAmount,
                'transaction_type' => Transaction::TYPE_CP,
                'points_type' => Transaction::POINTS_CREDITED,
                'transaction_reason' => "Community Points (Level {$level}) from {$newMemberId}'s registration" . ($isLocked ? ' [ON HOLD]' : ''),
            ]);

            // Notification
            Notification::createForMember([
                'member_id' => $receiverMemberId,
                'type' => 'community_points_earned',
                'title' => 'Community Points Earned!',
                'message' => "You earned {$cpAmount} CP at Level {$level}" . ($isLocked ? ' (On Hold - unlock more levels to access)' : ''),
            ]);
        }
    }

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

    // /**
    //  * Generate unique 8-character referral code
    //  */
    // private function generateUniqueReferralCode()
    // {
    //     do {
    //         $code = strtoupper(Str::random(8));
    //     } while (Member::where('referral_code', $code)->exists());

    //     return $code;
    // }

    /**
     * Format phone number as user_name
     */
    /**
     * Validate and format Malaysian phone number (must start with 01 and be 10–11 digits, no hyphens)
     */
    private function formatPhoneNumber($phone)
    {
        // Remove any non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Validate: must start with 01 and have 10 or 11 digits
        if (!preg_match('/^01\d{8,9}$/', $cleaned)) {
            throw new \InvalidArgumentException('Invalid Malaysian phone number. Must start with 01 and be 10–11 digits.');
        }

        return $cleaned;
    }


    /**
     * Get referral tree for authenticated member
     * 
     * GET /api/referral-tree
     */
    public function getReferralTree(Request $request)
    {
        try {
            $member = auth()->user();
            $tree = Referral::getReferralTree($member->id, 30);
            $statistics = $this->treeService->getTreeStatistics($member->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'tree' => $tree,
                    'statistics' => $statistics,
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
     * Get list of directly referred members
     * 
     * GET /api/referred-members
     */
    public function getReferredMembers(Request $request)
    {
        try {
            $member = auth()->user();
            
            $referrals = Referral::with(['childMember.wallet'])
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
}