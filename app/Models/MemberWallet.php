<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberWallet extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'member_wallets';

    protected $fillable = [
        'member_id',
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
     * Get the member that owns this wallet
     */
    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

}
