<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Voucher extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'vouchers';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'member_id',
        'merchant_id',
        'voucher_type',
        'denomination_id',
        'quantity',
        'payment_method',
        'total_amount',
        'manual_payment_docs_url',
        'manual_payment_docs_cloudinary_id',
        'status',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'member_id' => 'integer',
        'merchant_id' => 'integer',
        'denomination_id' => 'integer',
        'quantity' => 'integer',
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the member who purchased the voucher
     */
    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * Get the merchant who purchased the voucher
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * Get the denomination
     */
    public function denomination()
    {
        return $this->belongsTo(Denomination::class, 'denomination_id');
    }

    /**
     * Scope to filter by member
     */
    public function scopeByMember($query, $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Scope to filter by merchant
     */
    public function scopeByMerchant($query, $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * Scope to filter by voucher type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('voucher_type', $type);
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get successful vouchers
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope to get pending vouchers
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get failed vouchers
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get online payment vouchers
     */
    public function scopeOnlinePayment($query)
    {
        return $query->where('payment_method', 'online');
    }

    /**
     * Scope to get manual payment vouchers
     */
    public function scopeManualPayment($query)
    {
        return $query->where('payment_method', 'manual');
    }

    /**
     * Mark voucher as successful
     */
    public function markAsSuccessful()
    {
        $this->status = 'success';
        $this->save();
        
        return $this;
    }

    /**
     * Mark voucher as failed
     */
    public function markAsFailed()
    {
        $this->status = 'failed';
        $this->save();
        
        return $this;
    }

    /**
     * Check if voucher is successful
     */
    public function isSuccessful()
    {
        return $this->status === 'success';
    }

    /**
     * Check if voucher is pending
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if voucher is failed
     */
    public function isFailed()
    {
        return $this->status === 'failed';
    }

    /**
     * Check if voucher is for Max type (available points)
     */
    public function isMaxVoucher()
    {
        return $this->voucher_type === 'max';
    }

    /**
     * Check if voucher is for Refer type (referral points)
     */
    public function isReferVoucher()
    {
        return $this->voucher_type === 'refer';
    }

    /**
     * Get total points equivalent
     */
    public function getTotalPointsAttribute()
    {
        return $this->total_amount; // 1 RM = 1 Point
    }

    /**
     * Get voucher details with denomination info
     */
    public function getVoucherDetailsAttribute()
    {
        $denomination = $this->denomination;
        
        return [
            'voucher_id' => $this->id,
            'type' => $this->voucher_type,
            'denomination' => $denomination ? $denomination->title : 'N/A',
            'value_per_voucher' => $denomination ? $denomination->value : 0,
            'quantity' => $this->quantity,
            'total_amount' => $this->total_amount,
            'total_points' => $this->total_points,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
        ];
    }

    /**
     * Create a new voucher purchase
     */
    public static function createVoucher($data)
    {
        // Get denomination
        $denomination = Denomination::find($data['denomination_id']);
        
        if (!$denomination) {
            return null;
        }

        // Calculate total amount
        $totalAmount = $denomination->value * $data['quantity'];

        return self::create([
            'member_id' => $data['member_id'] ?? null,
            'merchant_id' => $data['merchant_id'] ?? null,
            'voucher_type' => $data['voucher_type'],
            'denomination_id' => $data['denomination_id'],
            'quantity' => $data['quantity'],
            'payment_method' => $data['payment_method'],
            'total_amount' => $totalAmount,
            'status' => 'pending',
        ]);
    }

    /**
     * Get voucher purchase history for member
     */
    public static function getMemberVoucherHistory($memberId, $limit = 50)
    {
        return self::with('denomination')
            ->where('member_id', $memberId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get voucher purchase history for merchant
     */
    public static function getMerchantVoucherHistory($merchantId, $limit = 50)
    {
        return self::with('denomination')
            ->where('merchant_id', $merchantId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get total voucher purchases by member
     */
    public static function getMemberTotalPurchases($memberId)
    {
        return self::where('member_id', $memberId)
            ->where('status', 'success')
            ->sum('total_amount');
    }

    /**
     * Get total voucher purchases by merchant
     */
    public static function getMerchantTotalPurchases($merchantId)
    {
        return self::where('merchant_id', $merchantId)
            ->where('status', 'success')
            ->sum('total_amount');
    }

    /**
     * Get voucher statistics for member
     */
    public static function getMemberVoucherStats($memberId)
    {
        $vouchers = self::where('member_id', $memberId);

        return [
            'total_vouchers' => $vouchers->count(),
            'successful_vouchers' => $vouchers->where('status', 'success')->count(),
            'pending_vouchers' => $vouchers->where('status', 'pending')->count(),
            'failed_vouchers' => $vouchers->where('status', 'failed')->count(),
            'total_amount_spent' => $vouchers->where('status', 'success')->sum('total_amount'),
            'max_vouchers' => $vouchers->where('voucher_type', 'max')->where('status', 'success')->count(),
            'refer_vouchers' => $vouchers->where('voucher_type', 'refer')->where('status', 'success')->count(),
        ];
    }

    /**
     * Get voucher statistics for merchant
     */
    public static function getMerchantVoucherStats($merchantId)
    {
        $vouchers = self::where('merchant_id', $merchantId);

        return [
            'total_vouchers' => $vouchers->count(),
            'successful_vouchers' => $vouchers->where('status', 'success')->count(),
            'pending_vouchers' => $vouchers->where('status', 'pending')->count(),
            'failed_vouchers' => $vouchers->where('status', 'failed')->count(),
            'total_amount_spent' => $vouchers->where('status', 'success')->sum('total_amount'),
            'max_vouchers' => $vouchers->where('voucher_type', 'max')->where('status', 'success')->count(),
            'refer_vouchers' => $vouchers->where('voucher_type', 'refer')->where('status', 'success')->count(),
        ];
    }

    /**
     * Get system-wide voucher statistics
     */
    public static function getSystemVoucherStats()
    {
        return [
            'total_vouchers' => self::count(),
            'successful_vouchers' => self::where('status', 'success')->count(),
            'pending_vouchers' => self::where('status', 'pending')->count(),
            'failed_vouchers' => self::where('status', 'failed')->count(),
            'total_revenue' => self::where('status', 'success')->sum('total_amount'),
            'max_vouchers_sold' => self::where('voucher_type', 'max')->where('status', 'success')->count(),
            'refer_vouchers_sold' => self::where('voucher_type', 'refer')->where('status', 'success')->count(),
            'online_payments' => self::where('payment_method', 'online')->where('status', 'success')->count(),
            'manual_payments' => self::where('payment_method', 'manual')->where('status', 'success')->count(),
        ];
    }

    /**
     * Get recent voucher purchases
     */
    public static function getRecentPurchases($days = 7, $limit = 50)
    {
        return self::with(['member', 'merchant', 'denomination'])
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get top voucher purchasers
     */
    public static function getTopPurchasers($limit = 10)
    {
        return self::selectRaw('
                member_id,
                COUNT(*) as total_vouchers,
                SUM(total_amount) as total_spent
            ')
            ->where('status', 'success')
            ->whereNotNull('member_id')
            ->groupBy('member_id')
            ->orderBy('total_spent', 'desc')
            ->limit($limit)
            ->with('member')
            ->get();
    }

    /**
     * Get voucher sales by denomination
     */
    public static function getSalesByDenomination()
    {
        return self::selectRaw('
                denomination_id,
                COUNT(*) as total_sales,
                SUM(quantity) as total_quantity,
                SUM(total_amount) as total_revenue
            ')
            ->where('status', 'success')
            ->groupBy('denomination_id')
            ->with('denomination')
            ->get();
    }

    /**
     * Get voucher sales by type
     */
    public static function getSalesByType()
    {
        return self::selectRaw('
                voucher_type,
                COUNT(*) as total_sales,
                SUM(total_amount) as total_revenue
            ')
            ->where('status', 'success')
            ->groupBy('voucher_type')
            ->get();
    }
}
