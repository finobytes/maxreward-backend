<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantWallet extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'merchant_wallets';

    protected $fillable = [
        'merchant_id',
        'total_points',
    ];

    protected $casts = [
        'total_points' => 'double',
    ];

    /**
     * Get the merchant that owns this wallet
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }
}
