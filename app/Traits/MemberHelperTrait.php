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

    public function formatPhoneNumber($phone)
    {
        // Remove any non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Validate: must start with 01 and have 10 or 11 digits
        if (!preg_match('/^01\d{8,9}$/', $cleaned)) {
            throw new \InvalidArgumentException('Invalid Malaysian phone number. Must start with 01 and be 10–11 digits.');
        }

        return $cleaned;
    }

}