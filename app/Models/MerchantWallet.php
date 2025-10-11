<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MerchantWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'total_referrals',
        'unlocked_level',
        'onhold_points',
        'total_points',
        'available_points',
        'total_rp',
        'total_pp',
        'total_cp',
    ];

    protected $casts = [
        'total_referrals' => 'integer',
        'unlocked_level' => 'integer',
        'onhold_points' => 'double',
        'total_points' => 'double',
        'available_points' => 'double',
        'total_rp' => 'double',
        'total_pp' => 'double',
        'total_cp' => 'double',
    ];

    /**
     * Get the merchant that owns this wallet
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }
}
