<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CpTransaction extends Model
{
    use HasFactory;

    protected $table = 'cp_transactions';

    protected $fillable = [
        'purchase_id',
        'source_member_id',
        'receiver_member_id',
        'level',
        'cp_percentage',
        'cp_amount',
        'is_locked',
        'status',
        'transaction_type',
        'released_at',
        'locked_at',
        'cp_distribution_pools_id',
        'total_referrals'
    ];

    protected $casts = [
        'purchase_id' => 'integer',
        'source_member_id' => 'integer',
        'receiver_member_id' => 'integer',
        'level' => 'integer',
        'cp_percentage' => 'decimal:2',
        'cp_amount' => 'double',
        'is_locked' => 'boolean',
        'released_at' => 'datetime',
        'locked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the purchase
     */
    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    /**
     * Get the source member (who made the purchase)
     */
    public function sourceMember()
    {
        return $this->belongsTo(Member::class, 'source_member_id');
    }

    /**
     * Get the receiver member (who receives CP)
     */
    public function receiverMember()
    {
        return $this->belongsTo(Member::class, 'receiver_member_id');
    }

    /**
     * Get the CP distribution pool
     */
    public function cpDistributionPools()
    {
        return $this->belongsTo(CpDistributionPool::class, 'cp_distribution_pools_id');
    }

    /**
     * Create a new CP transaction
     */
    public static function createCpTransaction($data)
    {
        $isLocked = $data['is_locked'] ?? false;
        
        $transaction = self::create([
            'purchase_id' => $data['purchase_id'],
            'source_member_id' => $data['source_member_id'],
            'receiver_member_id' => $data['receiver_member_id'],
            'level' => $data['level'],
            'cp_percentage' => $data['cp_percentage'],
            'cp_amount' => $data['cp_amount'],
            'is_locked' => $isLocked,
            'status' => $isLocked ? 'onhold' : 'available',
            'transaction_type' => 'earned',
            'total_referrals' => $data['total_referrals']
        ]);

        return $transaction;
    }

    /**
     * Release locked CP
     */
    public function releaseCp()
    {
        if ($this->status === 'onhold' && $this->is_locked) {
            $this->status = 'released';
            $this->transaction_type = 'unlocked';
            $this->released_at = Carbon::now();
            $this->save();
            
            return true;
        }
        
        return false;
    }

    /**
     * Scope to get transactions by purchase
     */
    public function scopeByPurchase($query, $purchaseId)
    {
        return $query->where('purchase_id', $purchaseId);
    }

    /**
     * Scope to get transactions by receiver
     */
    public function scopeByReceiver($query, $memberId)
    {
        return $query->where('receiver_member_id', $memberId);
    }

    /**
     * Scope to get transactions by source
     */
    public function scopeBySource($query, $memberId)
    {
        return $query->where('source_member_id', $memberId);
    }

    /**
     * Scope to get transactions by level
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope to get locked transactions
     */
    public function scopeLocked($query)
    {
        return $query->where('status', 'onhold');
    }

    /**
     * Scope to get available transactions
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope to get released transactions
     */
    public function scopeReleased($query)
    {
        return $query->where('status', 'released');
    }

    /**
     * Get total CP amount for a member
     */
    public static function getTotalCpForMember($memberId)
    {
        return self::where('receiver_member_id', $memberId)->sum('cp_amount');
    }

    /**
     * Get available CP amount for a member
     */
    public static function getAvailableCpForMember($memberId)
    {
        return self::where('receiver_member_id', $memberId)
            ->where('status', 'available')
            ->sum('cp_amount');
    }

    /**
     * Get onhold CP amount for a member
     */
    public static function getOnholdCpForMember($memberId)
    {
        return self::where('receiver_member_id', $memberId)
            ->where('status', 'onhold')
            ->sum('cp_amount');
    }

    /**
     * Get released CP amount for a member
     */
    public static function getReleasedCpForMember($memberId)
    {
        return self::where('receiver_member_id', $memberId)
            ->where('status', 'released')
            ->sum('cp_amount');
    }

    /**
     * Get CP transactions breakdown by level
     */
    public static function getCpBreakdownByLevel($memberId)
    {
        return self::where('receiver_member_id', $memberId)
            ->selectRaw('level, 
                SUM(cp_amount) as total_cp,
                SUM(CASE WHEN status = "available" THEN cp_amount ELSE 0 END) as available_cp,
                SUM(CASE WHEN status = "onhold" THEN cp_amount ELSE 0 END) as onhold_cp,
                SUM(CASE WHEN status = "released" THEN cp_amount ELSE 0 END) as released_cp')
            ->groupBy('level')
            ->orderBy('level')
            ->get();
    }

    /**
     * Get CP transaction history for a member
     */
    public static function getMemberHistory($memberId, $limit = 50)
    {
        return self::with(['sourceMember', 'purchase'])
            ->where('receiver_member_id', $memberId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent CP transactions
     */
    public static function getRecentTransactions($days = 7)
    {
        return self::with(['sourceMember', 'receiverMember'])
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Release all locked CP for a member at specific levels
     */
    public static function releaseLockedCpForLevels($memberId, $fromLevel, $toLevel)
    {
        $transactions = self::where('receiver_member_id', $memberId)
            ->where('status', 'onhold')
            ->whereBetween('level', [$fromLevel, $toLevel])
            ->get();

        $totalReleased = 0;

        foreach ($transactions as $transaction) {
            if ($transaction->releaseCp()) {
                $totalReleased += $transaction->cp_amount;
            }
        }

        return $totalReleased;
    }

    /**
     * Get CP statistics for a member
     */
    public static function getCpStatistics($memberId)
    {
        return [
            'total_cp' => self::getTotalCpForMember($memberId),
            'available_cp' => self::getAvailableCpForMember($memberId),
            'onhold_cp' => self::getOnholdCpForMember($memberId),
            'released_cp' => self::getReleasedCpForMember($memberId),
            'total_transactions' => self::where('receiver_member_id', $memberId)->count(),
            'earned_transactions' => self::where('receiver_member_id', $memberId)
                ->where('transaction_type', 'earned')
                ->count(),
            'unlocked_transactions' => self::where('receiver_member_id', $memberId)
                ->where('transaction_type', 'unlocked')
                ->count(),
        ];
    }

    /**
     * Get CP earned from a specific purchase across all levels
     */
    public static function getCpFromPurchase($purchaseId)
    {
        return self::where('purchase_id', $purchaseId)
            ->selectRaw('
                COUNT(*) as total_distributions,
                SUM(cp_amount) as total_cp_distributed,
                SUM(CASE WHEN status = "available" THEN cp_amount ELSE 0 END) as available_cp,
                SUM(CASE WHEN status = "onhold" THEN cp_amount ELSE 0 END) as onhold_cp
            ')
            ->first();
    }

    /**
     * Get top CP earners
     */
    public static function getTopCpEarners($limit = 10)
    {
        return self::selectRaw('receiver_member_id, SUM(cp_amount) as total_cp_earned')
            ->groupBy('receiver_member_id')
            ->orderBy('total_cp_earned', 'desc')
            ->limit($limit)
            ->with('receiverMember')
            ->get();
    }

}
