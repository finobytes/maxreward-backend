<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Merchant;
use App\Models\Member;

trait MerchantHelperTrait
{
    /**
     * Generate 8 character unique number for merchant
     */
    public function generateUniqueNumber(): string
    {
        do {
            $uniqueNumber = strtoupper(Str::random(8));
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
