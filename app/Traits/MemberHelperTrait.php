<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Member;

trait MemberHelperTrait
{
     /**
     * Generate unique 8-character referral code
     */
    public function generateUniqueReferralCode()
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Member::where('referral_code', $code)->exists());

        return $code;
    }
}