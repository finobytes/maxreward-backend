<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Recharge extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'recharge';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'member_id',
        'merchant_id',
        'voucher_type',
        'total_amount',
        'recharge_type',
        'manual_payment_docs_url',
        'manual_payment_docs_cloudinary_id',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'member_id' => 'integer',
        'merchant_id' => 'integer',
        'total_amount' => 'double',
        'approved_by' => 'integer',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the member who made the recharge
     */
    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * Get the merchant who made the recharge
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * Get the admin who approved
     */
    public function approvedBy()
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    /**
     * Scope to get pending recharges
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get approved recharges
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to get rejected recharges
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope to get by member
     */
    public function scopeByMember($query, $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Scope to get by merchant
     */
    public function scopeByMerchant($query, $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * Scope to get online recharges
     */
    public function scopeOnline($query)
    {
        return $query->where('recharge_type', 'online');
    }

    /**
     * Scope to get manual recharges
     */
    public function scopeManual($query)
    {
        return $query->where('recharge_type', 'manual');
    }

    /**
     * Scope to get by voucher type
     */
    public function scopeByVoucherType($query, $type)
    {
        return $query->where('voucher_type', $type);
    }

    /**
     * Check if recharge is pending
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if recharge is approved
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }

    /**
     * Check if recharge is rejected
     */
    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if recharge is online
     */
    public function isOnline()
    {
        return $this->recharge_type === 'online';
    }

    /**
     * Check if recharge is manual
     */
    public function isManual()
    {
        return $this->recharge_type === 'manual';
    }

    /**
     * Approve recharge
     */
    public function approve($adminId)
    {
        $this->status = 'approved';
        $this->approved_by = $adminId;
        $this->approved_at = Carbon::now();
        $this->save();

        // Credit points to member or merchant
        $this->creditPoints();

        return $this;
    }

    /**
     * Reject recharge
     */
    public function reject($adminId, $reason = null)
    {
        $this->status = 'rejected';
        $this->approved_by = $adminId;
        $this->approved_at = Carbon::now();
        $this->rejection_reason = $reason;
        $this->save();

        return $this;
    }

    /**
     * Credit points after approval
     */
    protected function creditPoints()
    {
        if ($this->member_id) {
            // Credit to member
            $wallet = MemberWallet::where('member_id', $this->member_id)->first();
            
            if ($wallet) {
                if ($this->voucher_type === 'max') {
                    $wallet->available_points += $this->total_amount;
                } else { // refer
                    $wallet->total_rp += $this->total_amount;
                }
                $wallet->save();

                // Log transaction
                Transaction::create([
                    'member_id' => $this->member_id,
                    'transaction_points' => $this->total_amount,
                    'transaction_type' => $this->voucher_type === 'max' ? 'vap' : 'vrp',
                    'points_type' => 'credited',
                    'transaction_reason' => 'Recharge voucher purchase',
                    'bap' => $wallet->available_points,
                    'brp' => $wallet->total_rp,
                    'bop' => $wallet->onhold_points
                ]);
            }
        } elseif ($this->merchant_id) {
            // Credit to merchant
            $wallet = MerchantWallet::where('merchant_id', $this->merchant_id)->first();
            
            if ($wallet) {
                if ($this->voucher_type === 'max') {
                    $wallet->available_points += $this->total_amount;
                } else { // refer
                    $wallet->total_rp += $this->total_amount;
                }
                $wallet->save();

                // Log transaction
                Transaction::create([
                    'merchant_id' => $this->merchant_id,
                    'transaction_points' => $this->total_amount,
                    'transaction_type' => $this->voucher_type === 'max' ? 'vap' : 'vrp',
                    'points_type' => 'credited',
                    'transaction_reason' => 'Recharge voucher purchase',
                    'bap' => $wallet->available_points,
                    'brp' => $wallet->total_rp,
                    'bop' => $wallet->onhold_points
                ]);
            }
        }
    }

    /**
     * Get pending recharges count
     */
    public static function getPendingCount()
    {
        return self::where('status', 'pending')->count();
    }

    /**
     * Get total recharge amount
     */
    public static function getTotalRechargeAmount($status = null)
    {
        $query = self::query();
        
        if ($status) {
            $query->where('status', $status);
        }
        
        return $query->sum('total_amount');
    }

    /**
     * Get recharges by date range
     */
    public static function getByDateRange($startDate, $endDate, $status = null)
    {
        $query = self::whereBetween('created_at', [$startDate, $endDate]);
        
        if ($status) {
            $query->where('status', $status);
        }
        
        return $query->get();
    }

    /**
     * Get recent recharges
     */
    public static function getRecent($limit = 10)
    {
        return self::with(['member', 'merchant', 'approvedBy'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recharge statistics
     */
    public static function getStatistics()
    {
        return [
            'total_recharges' => self::count(),
            'pending_recharges' => self::where('status', 'pending')->count(),
            'approved_recharges' => self::where('status', 'approved')->count(),
            'rejected_recharges' => self::where('status', 'rejected')->count(),
            'total_approved_amount' => self::where('status', 'approved')->sum('total_amount'),
            'online_recharges' => self::where('recharge_type', 'online')->count(),
            'manual_recharges' => self::where('recharge_type', 'manual')->count(),
            'max_voucher_count' => self::where('voucher_type', 'max')->count(),
            'refer_voucher_count' => self::where('voucher_type', 'refer')->count(),
        ];
    }

    /**
     * Get member recharge history
     */
    public static function getMemberHistory($memberId, $limit = 50)
    {
        return self::where('member_id', $memberId)
            ->with('approvedBy')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get merchant recharge history
     */
    public static function getMerchantHistory($merchantId, $limit = 50)
    {
        return self::where('merchant_id', $merchantId)
            ->with('approvedBy')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recharges grouped by status
     */
    public static function getGroupedByStatus()
    {
        return self::selectRaw('
                status,
                COUNT(*) as count,
                SUM(total_amount) as total_amount
            ')
            ->groupBy('status')
            ->get();
    }

    /**
     * Get recharges grouped by voucher type
     */
    public static function getGroupedByVoucherType()
    {
        return self::selectRaw('
                voucher_type,
                COUNT(*) as count,
                SUM(total_amount) as total_amount
            ')
            ->groupBy('voucher_type')
            ->get();
    }

    /**
     * Get monthly recharge report
     */
    public static function getMonthlyReport($year, $month)
    {
        return self::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as count,
                SUM(total_amount) as total_amount,
                SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = "approved" THEN total_amount ELSE 0 END) as approved_amount
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * Get top rechargers
     */
    public static function getTopRechargers($limit = 10)
    {
        return self::selectRaw('
                member_id,
                merchant_id,
                COUNT(*) as recharge_count,
                SUM(total_amount) as total_amount
            ')
            ->where('status', 'approved')
            ->groupBy('member_id', 'merchant_id')
            ->orderBy('total_amount', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get average recharge amount
     */
    public static function getAverageAmount($status = 'approved')
    {
        return self::where('status', $status)->avg('total_amount');
    }

    /**
     * Create recharge request
     */
    public static function createRecharge($data)
    {
        return self::create([
            'member_id' => $data['member_id'] ?? null,
            'merchant_id' => $data['merchant_id'] ?? null,
            'voucher_type' => $data['voucher_type'],
            'total_amount' => $data['total_amount'],
            'recharge_type' => $data['recharge_type'],
            'manual_payment_docs_url' => $data['manual_payment_docs_url'] ?? null,
            'manual_payment_docs_cloudinary_id' => $data['manual_payment_docs_cloudinary_id'] ?? null,
            'status' => $data['recharge_type'] === 'online' ? 'approved' : 'pending',
            'approved_at' => $data['recharge_type'] === 'online' ? Carbon::now() : null,
        ]);
    }
}
