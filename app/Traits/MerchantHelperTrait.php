<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Merchant;
use App\Models\Member;

trait MerchantHelperTrait
{
    /**
     * Generate 8 digit unique number for merchant
     */
    public function generateUniqueNumber(): string
    {
        do {
            $uniqueNumber = str_pad(rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        } while (Merchant::where('unique_number', $uniqueNumber)->exists());

        return $uniqueNumber;
    }

    /**
     * Generate corporate member username (C + 8 digits)
     */
    public function generateCorporateMemberUsername(): string
    {
        do {
            $username = 'C' . str_pad(rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        } while (Member::where('user_name', $username)->exists());

        return $username;
    }
}
