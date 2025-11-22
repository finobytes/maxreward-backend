<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'transactions';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'member_id',
        'merchant_id',
        'referral_member_id',
        'transaction_points',
        'transaction_type',
        'points_type',
        'transaction_reason',
        'balance',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'member_id' => 'integer',
        'merchant_id' => 'integer',
        'referral_member_id' => 'integer',
        'transaction_points' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Transaction type constants
     */
    const TYPE_PP = 'pp';   // Personal Points
    const TYPE_RP = 'rp';   // Referral Points
    const TYPE_CP = 'cp';   // Community Points
    const TYPE_CR = 'cr';   // Company Reserve
    const TYPE_DP = 'dp';   // Deducted Points
    const TYPE_AP = 'ap';   // Added Points
    const TYPE_VRP = 'vrp'; // Voucher Referral Points
    const TYPE_VAP = 'vap'; // Voucher Available Points

    /**
     * Points type constants
     */
    const POINTS_DEBITED = 'debited';
    const POINTS_CREDITED = 'credited';

    /**
     * Get the member
     */
    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * Get the merchant
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * Get the referral member
     */
    public function referralMember()
    {
        return $this->belongsTo(Member::class, 'referral_member_id');
    }

    /**
     * Scope to get credited transactions
     */
    public function scopeCredited($query)
    {
        return $query->where('points_type', self::POINTS_CREDITED);
    }

    /**
     * Scope to get debited transactions
     */
    public function scopeDebited($query)
    {
        return $query->where('points_type', self::POINTS_DEBITED);
    }

    /**
     * Scope to get transactions by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Scope to get transactions by member
     */
    public function scopeByMember($query, $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Scope to get transactions by merchant
     */
    public function scopeByMerchant($query, $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * Scope to get transactions in date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Create a transaction record
     */
    public static function createTransaction($data)
    {
        return self::create([
            'member_id' => $data['member_id'] ?? null,
            'merchant_id' => $data['merchant_id'] ?? null,
            'referral_member_id' => $data['referral_member_id'] ?? null,
            'transaction_points' => $data['transaction_points'],
            'transaction_type' => $data['transaction_type'],
            'points_type' => $data['points_type'],
            'transaction_reason' => $data['transaction_reason'] ?? null,
            'balance' => $data['balance'] ?? null,
        ]);
    }

    /**
     * Get member's transaction history
     */
    public static function getMemberHistory($memberId, $limit = 50)
    {
        return self::where('member_id', $memberId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get merchant's transaction history
     */
    public static function getMerchantHistory($merchantId, $limit = 50)
    {
        return self::where('merchant_id', $merchantId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get total points credited to member
     */
    public static function getTotalCreditedForMember($memberId)
    {
        return self::where('member_id', $memberId)
            ->where('points_type', self::POINTS_CREDITED)
            ->sum('transaction_points');
    }

    /**
     * Get total points debited from member
     */
    public static function getTotalDebitedForMember($memberId)
    {
        return self::where('member_id', $memberId)
            ->where('points_type', self::POINTS_DEBITED)
            ->sum('transaction_points');
    }

    /**
     * Get transaction summary by type for member
     */
    public static function getMemberTransactionSummary($memberId)
    {
        return self::where('member_id', $memberId)
            ->selectRaw('
                transaction_type,
                points_type,
                COUNT(*) as count,
                SUM(transaction_points) as total_points
            ')
            ->groupBy('transaction_type', 'points_type')
            ->get();
    }

    /**
     * Get recent transactions
     */
    public static function getRecentTransactions($limit = 20)
    {
        return self::with(['member', 'merchant'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get transactions by type and date range
     */
    public static function getByTypeAndDateRange($type, $startDate, $endDate)
    {
        return self::where('transaction_type', $type)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get total points by transaction type
     */
    public static function getTotalByType($type)
    {
        return self::where('transaction_type', $type)->sum('transaction_points');
    }

    /**
     * Get transaction statistics
     */
    public static function getTransactionStatistics()
    {
        return [
            'total_transactions' => self::count(),
            'total_credited' => self::where('points_type', self::POINTS_CREDITED)->sum('transaction_points'),
            'total_debited' => self::where('points_type', self::POINTS_DEBITED)->sum('transaction_points'),
            'pp_total' => self::where('transaction_type', self::TYPE_PP)->sum('transaction_points'),
            'rp_total' => self::where('transaction_type', self::TYPE_RP)->sum('transaction_points'),
            'cp_total' => self::where('transaction_type', self::TYPE_CP)->sum('transaction_points'),
            'cr_total' => self::where('transaction_type', self::TYPE_CR)->sum('transaction_points'),
        ];
    }

    /**
     * Get top earners
     */
    public static function getTopEarners($limit = 10)
    {
        return self::selectRaw('member_id, SUM(transaction_points) as total_earned')
            ->where('points_type', self::POINTS_CREDITED)
            ->groupBy('member_id')
            ->orderBy('total_earned', 'desc')
            ->limit($limit)
            ->with('member')
            ->get();
    }

    /**
     * Get transaction breakdown for member
     */
    public static function getMemberBreakdown($memberId)
    {
        $summary = self::where('member_id', $memberId)
            ->selectRaw('
                transaction_type,
                SUM(CASE WHEN points_type = "credited" THEN transaction_points ELSE 0 END) as credited,
                SUM(CASE WHEN points_type = "debited" THEN transaction_points ELSE 0 END) as debited
            ')
            ->groupBy('transaction_type')
            ->get();

        $breakdown = [];
        foreach ($summary as $item) {
            $breakdown[$item->transaction_type] = [
                'credited' => (float) $item->credited,
                'debited' => (float) $item->debited,
                'net' => (float) ($item->credited - $item->debited),
            ];
        }

        return $breakdown;
    }
}
