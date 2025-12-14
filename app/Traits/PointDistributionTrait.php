<?php

namespace App\Traits;

use App\Models\Referral;
use App\Models\MemberWallet;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\CpTransaction;
use App\Models\MemberCommunityPoint;
use App\Models\CpLevelConfig;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use App\Models\CpDistributionPool;

trait PointDistributionTrait
{
    /**
     * Distribute Community Points (CP) across 30 levels
     * Based on CpLevelConfig distribution rules
     * ✅ SHARED TRAIT FOR BOTH CONTROLLERS 1. ReferralController 2. MerchantController
     */
    private function distributeCommunityPoints($sourceMember, $sourceMemberId, $newMemberId, $totalCp, $reason, $purchase_id = null, $transaction_id = null, $transaction_amount)
    {
        Log::info('Start :: Distribute Community Points (CP) across 30 levels for: ' . $reason);

        // Get upline path from NEW MEMBER up to 30 levels
        $uplinePath = Referral::getReferralPath($sourceMemberId, 30);

        Log::info("Upline path for CP distribution", [
            'source_member_id' => $sourceMemberId,
            'new_member_id' => $newMemberId,
            'upline_path' => count($uplinePath),
            'total_cp' => $totalCp,
            'reason' => $reason
        ]);

        $total_cp_distributed = 0;
        $cpTransactionIds = [];

        foreach ($uplinePath as $node) {
            $level = $node['level'];
            $receiverMemberId = $node['member_id'];

            // Skip if receiver is the new member itself
            if ($receiverMemberId == $newMemberId) {
                continue;
            }

            // Get CP percentage for this level
            $cpPercentage = CpLevelConfig::getCpPercentageForLevel($level);
            $cpAmount = ($totalCp * $cpPercentage) / 100;

            if ($cpAmount <= 0) {
                continue;
            }

            // Get receiver's wallet
            $receiverWallet = MemberWallet::where('member_id', $receiverMemberId)->first();
            
            if (!$receiverWallet) {
                Log::warning("Receiver wallet not found", ['member_id' => $receiverMemberId]);
                continue;
            }

            // Check if this level is locked for the receiver
            $isLocked = $level > $receiverWallet->unlocked_level;

            Log::info("Distributing CP to upline member", [
                'level' => $level,
                'receiver_id' => $receiverMemberId,
                'cp_percentage' => $cpPercentage,
                'cp_amount' => $cpAmount,
                'is_locked' => $isLocked,
                'reason' => $reason
            ]);

            // Create CP transaction record
            $cpTransaction = CpTransaction::createCpTransaction([
                'purchase_id' => $purchase_id ?? null,
                'source_member_id' => $sourceMemberId,
                'receiver_member_id' => $receiverMemberId,
                'level' => $level,
                'cp_percentage' => $cpPercentage,
                'cp_amount' => $cpAmount,
                'is_locked' => $isLocked,
                'total_referrals' => $receiverWallet->total_referrals
            ]);

            $cpTransactionIds[] = $cpTransaction->id;

            // Update member's CP wallet
            $mcp = MemberCommunityPoint::getOrCreateForLevel($receiverMemberId, $level, $isLocked);
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
            $transactionReason = $reason === 'purchase' 
                ? "Community Points (Level {$level}) from purchase" 
                : "Community Points (Level {$level}) from new member registration";

            Transaction::createTransaction([
                'member_id' => $receiverMemberId,
                'referral_member_id' => $newMemberId,
                'transaction_points' => $cpAmount,
                'transaction_type' => Transaction::TYPE_CP,
                'points_type' => Transaction::POINTS_CREDITED,
                'transaction_reason' => $transactionReason . ($isLocked ? ' [ON HOLD]' : ''),
                'bap' => $receiverWallet->available_points,
                'brp' => $receiverWallet->total_rp,
                'bop' => $receiverWallet->onhold_points
            ]);

            // Notification
            $message = $reason === 'purchase'
                ? "You earned {$cpAmount} CP at Level {$level} from purchase"
                : "You earned {$cpAmount} CP at Level {$level} from new member";

            Notification::createForMember([
                'member_id' => $receiverMemberId,
                'type' => 'community_points_earned',
                'title' => 'Community Points Earned!',
                'message' => $message . ($isLocked ? ' (On Hold - unlock more levels to access)' : ''),
            ]);

            $total_cp_distributed += $cpAmount;
        }

        Log::info('total_distributed_cp: ' . $total_cp_distributed);
        $new_member = Member::findOrFail($newMemberId);

        $cpDistributionPool = CpDistributionPool::create([
            'transaction_id' => $transaction_id ?? 'Ref-new-' . $new_member->phone,
            'source_member_id' => $sourceMemberId,
            'total_cp_distributed' => $total_cp_distributed,
            'total_cp_amount' => $totalCp,
            'total_transaction_amount' => $transaction_amount,
            'phone' => $sourceMember->phone,
            'total_referrals' => $sourceMember->wallet->total_referrals,
            'unlocked_level' => $sourceMember->wallet->unlocked_level
        ]);

        // Update all related transactions with the pool ID
        CpTransaction::whereIn('id', $cpTransactionIds)->update(['cp_distribution_pools_id' => $cpDistributionPool->id]);

        // session(['cp_distribution_pool_id' => $cpDistributionPool->id]);

        Log::info("CP distribution completed");
    }
}