<?php

namespace App\Traits;

use App\Models\Transaction;
use App\Models\Member;
use Illuminate\Support\Facades\Log;
use App\Models\Notification;
use App\Traits\PointDistributionTrait;
use App\Models\CompanyInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\CommonFunctionHelper;

trait DistributeReferralPointsTrait
{
    use PointDistributionTrait;

    protected $settingAttributes;

    public function __construct(CommonFunctionHelper $commonFunctionHelper) {
        $this->settingAttributes = $commonFunctionHelper->settingAttributes()['maxreward'];
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
        Log::info('🎯 Distributing Referral Points');

        Log::info('Total Points: ' . $totalPoints);

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
            'bap' => $newMemberWallet->available_points
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
                'bap' => $sponsorWallet->available_points
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
            'cr_balance' => $company->cr_points
        ]);

        Log::info('🎯 Referral Points Distributed');
    }

}
