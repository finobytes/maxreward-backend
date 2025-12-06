<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CpDistributionPool extends Model
{
    protected $table = 'cp_distribution_pools';

    protected $fillable = [
        'transaction_id',
        'source_member_id',
        'total_cp_amount',
        'total_cp_distributed',
        'total_transaction_amount',
        'phone',
        'total_referrals',
        'unlocked_level',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class, 'source_member_id');
    }

    /**
     * Get the CP transactions
     */
    public function cpTransactions()
    {
        return $this->hasMany(CpTransaction::class, 'cp_distribution_pools_id');
    }

}
