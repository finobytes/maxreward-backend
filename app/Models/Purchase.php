<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Purchase extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'purchases';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'merchant_id',
        'member_id',
        'transaction_id',
        'merchant_selection_type',
        'transaction_amount',
        'redeem_amount',
        'cash_redeem_amount',
        'payment_method',
        'rejected_by',
        'rejected_reason',
        'status',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'merchant_id' => 'integer',
        'member_id' => 'integer',
        'transaction_amount' => 'decimal:2',
        'redeem_amount' => 'decimal:2',
        'cash_redeem_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the merchant
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * Get the member
     */
    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * Get CP transactions for this purchase
     */
    public function cpTransactions()
    {
        return $this->hasMany(CpTransaction::class, 'purchase_id');
    }

    /**
     * Scope to get pending purchases pending()
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get approved purchases approved()
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to get rejected purchases
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope to get by merchant
     */
    public function scopeByMerchant($query, $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * Scope to get by member
     */
    public function scopeByMember($query, $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Check if purchase is pending
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if purchase is approved
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }

    /**
     * Check if purchase is rejected
     */
    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    /**
     * Approve purchase
     */
    public function approve()
    {
        $this->status = 'approved';
        $this->save();
        
        return $this;
    }

    /**
     * Reject purchase
     */
    public function reject()
    {
        $this->status = 'rejected';
        $this->save();
        
        return $this;
    }

    /**
     * Calculate reward points based on merchant commission rate
     */
    public function calculateRewardPoints()
    {
        $commissionRate = $this->merchant->commission_rate ?? 0;
        return ($this->transaction_amount * $commissionRate) / 100;
    }

    /**
     * Get net amount (transaction - redeemed)
     */
    public function getNetAmount()
    {
        return $this->transaction_amount - $this->cash_redeem_amount;
    }

    /**
     * Calculate point distribution (PP, RP, CP, CR)
     */
    public function calculatePointDistribution()
    {
        $rewardPoints = $this->calculateRewardPoints();
        
        return [
            'total_reward_points' => $rewardPoints,
            'pp' => $rewardPoints * 0.10, // 10% Personal Points
            'rp' => $rewardPoints * 0.20, // 20% Referral Points
            'cp' => $rewardPoints * 0.50, // 50% Community Points
            'cr' => $rewardPoints * 0.20, // 20% Company Reserve
        ];
    }

    /**
     * Get formatted transaction amount
     */
    public function getFormattedTransactionAmountAttribute()
    {
        return 'RM ' . number_format($this->transaction_amount, 2);
    }

    /**
     * Get formatted redeem amount
     */
    public function getFormattedRedeemAmountAttribute()
    {
        return number_format($this->redeem_amount, 2);
    }

    /**
     * Get purchase statistics for a member
     */
    public static function getMemberStatistics($memberId)
    {
        return [
            'total_purchases' => self::where('member_id', $memberId)->count(),
            'approved_purchases' => self::where('member_id', $memberId)->approved()->count(),
            'pending_purchases' => self::where('member_id', $memberId)->pending()->count(),
            'rejected_purchases' => self::where('member_id', $memberId)->rejected()->count(),
            'total_spent' => self::where('member_id', $memberId)
                ->approved()
                ->sum('transaction_amount'),
            'total_redeemed' => self::where('member_id', $memberId)
                ->approved()
                ->sum('redeem_amount'),
        ];
    }

    /**
     * Get purchase statistics for a merchant
     */
    public static function getMerchantStatistics($merchantId)
    {
        return [
            'total_purchases' => self::where('merchant_id', $merchantId)->count(),
            'approved_purchases' => self::where('merchant_id', $merchantId)->approved()->count(),
            'pending_purchases' => self::where('merchant_id', $merchantId)->pending()->count(),
            'rejected_purchases' => self::where('merchant_id', $merchantId)->rejected()->count(),
            'total_revenue' => self::where('merchant_id', $merchantId)
                ->approved()
                ->sum('transaction_amount'),
            'total_points_redeemed' => self::where('merchant_id', $merchantId)
                ->approved()
                ->sum('redeem_amount'),
        ];
    }

    /**
     * Get recent purchases
     */
    public static function getRecentPurchases($limit = 10)
    {
        return self::with(['member', 'merchant'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get pending purchases for approval
     */
    public static function getPendingPurchasesForMerchant($merchantId)
    {
        return self::with('member')
            ->where('merchant_id', $merchantId)
            ->pending()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get top spenders
     */
    public static function getTopSpenders($limit = 10)
    {
        return self::selectRaw('member_id, SUM(transaction_amount) as total_spent')
            ->approved()
            ->groupBy('member_id')
            ->orderBy('total_spent', 'desc')
            ->limit($limit)
            ->with('member')
            ->get();
    }

    /**
     * Get purchase summary by status
     */
    public static function getPurchaseSummaryByStatus()
    {
        return self::selectRaw('
                status,
                COUNT(*) as count,
                SUM(transaction_amount) as total_amount,
                AVG(transaction_amount) as avg_amount
            ')
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->toArray();
    }

    /**
     * Get daily purchase statistics
     */
    public static function getDailyStatistics($days = 30)
    {
        return self::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as purchase_count,
                SUM(transaction_amount) as total_amount,
                AVG(transaction_amount) as avg_amount
            ')
            ->where('created_at', '>=', now()->subDays($days))
            ->approved()
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();
    }

    /**
     * Get purchases by payment method
     */
    public static function getPurchasesByPaymentMethod()
    {
        return self::selectRaw('
                payment_method,
                COUNT(*) as count,
                SUM(transaction_amount) as total_amount
            ')
            ->approved()
            ->groupBy('payment_method')
            ->get();
    }

    /**
     * Get merchant rankings by revenue
     */
    public static function getMerchantRankings($limit = 10)
    {
        return self::selectRaw('
                merchant_id,
                COUNT(*) as total_purchases,
                SUM(transaction_amount) as total_revenue
            ')
            ->approved()
            ->groupBy('merchant_id')
            ->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->with('merchant')
            ->get();
    }

    /**
     * Check if member can make purchase (has active status)
     */
    public static function canMemberPurchase($memberId)
    {
        $member = Member::find($memberId);
        
        if (!$member) {
            return ['can_purchase' => false, 'reason' => 'Member not found'];
        }

        if ($member->status !== 'active') {
            return ['can_purchase' => false, 'reason' => 'Member account is ' . $member->status];
        }

        return ['can_purchase' => true];
    }

    /**
     * Get total reward points generated from this purchase
     */
    public function getTotalPointsGenerated()
    {
        return $this->cpTransactions()->sum('cp_amount');
    }

    /**
     * Get purchase with full details
     */
    public static function getPurchaseDetails($purchaseId)
    {
        return self::with(['member', 'merchant', 'cpTransactions.receiverMember'])
            ->find($purchaseId);
    }
}
