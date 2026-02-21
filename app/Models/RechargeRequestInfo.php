<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RechargeRequestInfo extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'recharge_request_infos';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'member_id',
        'merchant_id',
        'type',
        'payload',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'member_id' => 'integer',
        'merchant_id' => 'integer',
        'payload' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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
     * Create before recharge request log
     * 
     * @param int|null $memberId Member ID
     * @param int|null $merchantId Merchant ID
     * @param array $payload Request data
     * @return RechargeRequestInfo
     */
    public static function logBeforeRequest($memberId, $merchantId, array $payload)
    {
        return self::create([
            'member_id' => $memberId,
            'merchant_id' => $merchantId,
            'type' => 'before_recharge_request',
            'payload' => $payload,
        ]);
    }

    /**
     * Create after recharge request log
     * 
     * @param int|null $memberId Member ID
     * @param int|null $merchantId Merchant ID
     * @param array $payload Response data
     * @return RechargeRequestInfo
     */
    public static function logAfterRequest($memberId, $merchantId, array $payload)
    {
        return self::create([
            'member_id' => $memberId,
            'merchant_id' => $merchantId,
            'type' => 'after_recharge_request',
            'payload' => $payload,
        ]);
    }

    /**
     * Scope to get before requests
     */
    public function scopeBeforeRequests($query)
    {
        return $query->where('type', 'before_recharge_request');
    }

    /**
     * Scope to get after requests
     */
    public function scopeAfterRequests($query)
    {
        return $query->where('type', 'after_recharge_request');
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
     * Scope to get recent logs
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get all recharge logs for a member
     * 
     * @param int $memberId Member ID
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getMemberLogs($memberId)
    {
        return self::where('member_id', $memberId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get all recharge logs for a merchant
     * 
     * @param int $merchantId Merchant ID
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getMerchantLogs($merchantId)
    {
        return self::where('merchant_id', $merchantId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get before/after pair for a recharge session
     * 
     * @param int|null $memberId Member ID
     * @param int|null $merchantId Merchant ID
     * @param string $sessionId Session identifier from payload
     * @return array
     */
    public static function getSessionPair($memberId, $merchantId, $sessionId)
    {
        $logs = self::where('member_id', $memberId)
            ->orWhere('merchant_id', $merchantId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $before = $logs->where('type', 'before_recharge_request')
            ->where('payload.session_id', $sessionId)
            ->first();

        $after = $logs->where('type', 'after_recharge_request')
            ->where('payload.session_id', $sessionId)
            ->first();

        return [
            'before' => $before,
            'after' => $after,
            'has_pair' => $before && $after,
        ];
    }

    /**
     * Get recent recharge requests with response status
     * 
     * @param int $days Number of days
     * @return \Illuminate\Support\Collection
     */
    public static function getRecentRequests($days = 7)
    {
        return self::with(['member', 'merchant'])
            ->recent($days)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($log) {
                return [
                    'id' => $log->id,
                    'user_type' => $log->member_id ? 'member' : 'merchant',
                    'user_id' => $log->member_id ?? $log->merchant_id,
                    'user_name' => $log->member?->name ?? $log->merchant?->business_name,
                    'type' => $log->type,
                    'payload' => $log->payload,
                    'created_at' => $log->created_at,
                ];
            });
    }

    /**
     * Get payload field value
     * 
     * @param string $key Payload key
     * @param mixed $default Default value
     * @return mixed
     */
    public function getPayloadValue($key, $default = null)
    {
        return data_get($this->payload, $key, $default);
    }

    /**
     * Check if request was successful (from payload)
     * 
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->getPayloadValue('status') === 'success' ||
               $this->getPayloadValue('success') === true;
    }

    /**
     * Get amount from payload
     * 
     * @return float|null
     */
    public function getAmount()
    {
        return $this->getPayloadValue('amount') ?? 
               $this->getPayloadValue('total_amount');
    }

    /**
     * Get voucher type from payload
     * 
     * @return string|null
     */
    public function getVoucherType()
    {
        return $this->getPayloadValue('voucher_type');
    }

    /**
     * Get payment method from payload
     * 
     * @return string|null
     */
    public function getPaymentMethod()
    {
        return $this->getPayloadValue('payment_method') ?? 
               $this->getPayloadValue('recharge_type');
    }

    /**
     * Get recharge statistics
     * 
     * @return array
     */
    public static function getRechargeStatistics()
    {
        $beforeCount = self::where('type', 'before_recharge_request')->count();
        $afterCount = self::where('type', 'after_recharge_request')->count();

        return [
            'total_requests' => $beforeCount,
            'total_responses' => $afterCount,
            'completion_rate' => $beforeCount > 0 ? round(($afterCount / $beforeCount) * 100, 2) : 0,
            'recent_7_days' => self::recent(7)->count(),
            'recent_30_days' => self::recent(30)->count(),
        ];
    }

    /**
     * Get failed recharge attempts (before with no after)
     * 
     * @param int $days Number of days to check
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getFailedAttempts($days = 7)
    {
        // Get all "before" requests from last X days
        $beforeRequests = self::beforeRequests()
            ->recent($days)
            ->get();

        // Filter those without corresponding "after" requests
        return $beforeRequests->filter(function($before) {
            $sessionId = $before->getPayloadValue('session_id');
            if (!$sessionId) return false;

            $after = self::afterRequests()
                ->where('member_id', $before->member_id)
                ->orWhere('merchant_id', $before->merchant_id)
                ->where('created_at', '>=', $before->created_at)
                ->get()
                ->where('payload.session_id', $sessionId)
                ->first();

            return !$after;
        });
    }

    /**
     * Get recharge volume by day
     * 
     * @param int $days Number of days
     * @return array
     */
    public static function getVolumeByDay($days = 30)
    {
        return self::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->mapWithKeys(function($item) {
                return [$item->date => $item->count];
            })
            ->toArray();
    }

    /**
     * Get recharge breakdown by voucher type
     * 
     * @return \Illuminate\Support\Collection
     */
    public static function getBreakdownByVoucherType()
    {
        return self::afterRequests()
            ->get()
            ->groupBy(function($log) {
                return $log->getVoucherType() ?? 'unknown';
            })
            ->map(function($group) {
                return [
                    'count' => $group->count(),
                    'total_amount' => $group->sum(function($log) {
                        return $log->getAmount() ?? 0;
                    }),
                ];
            });
    }

    /**
     * Get recharge breakdown by payment method
     * 
     * @return \Illuminate\Support\Collection
     */
    public static function getBreakdownByPaymentMethod()
    {
        return self::afterRequests()
            ->get()
            ->groupBy(function($log) {
                return $log->getPaymentMethod() ?? 'unknown';
            })
            ->map(function($group) {
                return [
                    'count' => $group->count(),
                    'total_amount' => $group->sum(function($log) {
                        return $log->getAmount() ?? 0;
                    }),
                ];
            });
    }

    /**
     * Clean up old logs (optional - for maintenance)
     * 
     * @param int $days Keep logs newer than X days
     * @return int Number of deleted records
     */
    public static function cleanupOldLogs($days = 90)
    {
        return self::where('created_at', '<', now()->subDays($days))->delete();
    }
}
