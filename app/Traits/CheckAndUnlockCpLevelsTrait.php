<?php

namespace App\Traits;

use App\Models\Member;
use App\Models\MemberWallet;
use App\Models\MemberCommunityPoint;
use App\Models\CpUnlockHistory;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

trait CheckAndUnlockCpLevelsTrait
{
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

        Log::info('End :: Check and unlock CP levels based on referral count');
    }
}