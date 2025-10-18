<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notification extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'notifications';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'member_id',
        'merchant_id',
        'type',
        'title',
        'message',
        'data',
        'status',
        'is_count_read',
        'is_read',
        'read_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'member_id' => 'integer',
        'merchant_id' => 'integer',
        'data' => 'array',
        'is_count_read' => 'boolean',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
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
     * Scope to get unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('status', 'unread');
    }

    /**
     * Scope to get read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('status', 'read');
    }

    /**
     * Scope to get by member
     */
    public function scopeForMember($query, $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Scope to get by merchant
     */
    public function scopeForMerchant($query, $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * Scope to get by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get recent notifications
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Mark as read
     */
    public function markAsRead()
    {
        $this->status = 'read';
        $this->is_read = true;
        $this->read_at = Carbon::now();
        $this->save();

        return $this;
    }

    /**
     * Mark as unread
     */
    public function markAsUnread()
    {
        $this->status = 'unread';
        $this->is_read = false;
        $this->read_at = null;
        $this->save();

        return $this;
    }

    /**
     * Create notification for member
     * 
     * @param array $data Notification data
     * @return Notification
     */
    public static function createForMember($data)
    {
        return self::create([
            'member_id' => $data['member_id'],
            'type' => $data['type'],
            'title' => $data['title'] ?? null,
            'message' => $data['message'],
            'data' => $data['data'] ?? null,
            'status' => 'unread',
            'is_read' => false,
        ]);
    }

    /**
     * Create notification for merchant
     * 
     * @param array $data Notification data
     * @return Notification
     */
    public static function createForMerchant($data)
    {
        return self::create([
            'merchant_id' => $data['merchant_id'],
            'type' => $data['type'],
            'title' => $data['title'] ?? null,
            'message' => $data['message'],
            'data' => $data['data'] ?? null,
            'status' => 'unread',
            'is_read' => false,
        ]);
    }

    /**
     * Get unread count for member
     * 
     * @param int $memberId Member ID
     * @return int Count
     */
    public static function getUnreadCountForMember($memberId)
    {
        return self::where('member_id', $memberId)
            ->where('status', 'unread')
            ->count();
    }

    /**
     * Get unread count for merchant
     * 
     * @param int $merchantId Merchant ID
     * @return int Count
     */
    public static function getUnreadCountForMerchant($merchantId)
    {
        return self::where('merchant_id', $merchantId)
            ->where('status', 'unread')
            ->count();
    }

    /**
     * Mark all as read for member
     * 
     * @param int $memberId Member ID
     * @return int Number of updated records
     */
    public static function markAllAsReadForMember($memberId)
    {
        return self::where('member_id', $memberId)
            ->where('status', 'unread')
            ->update([
                'status' => 'read',
                'is_read' => true,
                'read_at' => Carbon::now(),
            ]);
    }

    /**
     * Mark all as read for merchant
     * 
     * @param int $merchantId Merchant ID
     * @return int Number of updated records
     */
    public static function markAllAsReadForMerchant($merchantId)
    {
        return self::where('merchant_id', $merchantId)
            ->where('status', 'unread')
            ->update([
                'status' => 'read',
                'is_read' => true,
                'read_at' => Carbon::now(),
            ]);
    }

    /**
     * Get notifications for member
     * 
     * @param int $memberId Member ID
     * @param int $limit Limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getForMember($memberId, $limit = 50)
    {
        return self::where('member_id', $memberId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get notifications for merchant
     * 
     * @param int $merchantId Merchant ID
     * @param int $limit Limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getForMerchant($merchantId, $limit = 50)
    {
        return self::where('merchant_id', $merchantId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Delete old notifications (older than X days)
     * 
     * @param int $days Days to keep
     * @return int Number of deleted records
     */
    public static function deleteOldNotifications($days = 90)
    {
        return self::where('created_at', '<', Carbon::now()->subDays($days))
            ->delete();
    }

    /**
     * Get notification statistics for member
     * 
     * @param int $memberId Member ID
     * @return array Statistics
     */
    public static function getMemberStatistics($memberId)
    {
        return [
            'total' => self::where('member_id', $memberId)->count(),
            'unread' => self::where('member_id', $memberId)->where('status', 'unread')->count(),
            'read' => self::where('member_id', $memberId)->where('status', 'read')->count(),
            'by_type' => self::where('member_id', $memberId)
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
        ];
    }

    /**
     * Get notification statistics for merchant
     * 
     * @param int $merchantId Merchant ID
     * @return array Statistics
     */
    public static function getMerchantStatistics($merchantId)
    {
        return [
            'total' => self::where('merchant_id', $merchantId)->count(),
            'unread' => self::where('merchant_id', $merchantId)->where('status', 'unread')->count(),
            'read' => self::where('merchant_id', $merchantId)->where('status', 'read')->count(),
            'by_type' => self::where('merchant_id', $merchantId)
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
        ];
    }

    /**
     * Notify purchase approved
     * 
     * @param int $memberId Member ID
     * @param array $purchaseData Purchase data
     * @return Notification
     */
    public static function notifyPurchaseApproved($memberId, $purchaseData)
    {
        return self::createForMember([
            'member_id' => $memberId,
            'type' => 'purchase_approved',
            'title' => 'Purchase Approved!',
            'message' => "Your purchase of RM {$purchaseData['amount']} has been approved. You've earned {$purchaseData['points']} points!",
            'data' => $purchaseData,
        ]);
    }

    /**
     * Notify purchase rejected
     * 
     * @param int $memberId Member ID
     * @param array $purchaseData Purchase data
     * @return Notification
     */
    public static function notifyPurchaseRejected($memberId, $purchaseData)
    {
        return self::createForMember([
            'member_id' => $memberId,
            'type' => 'purchase_rejected',
            'title' => 'Purchase Rejected',
            'message' => "Your purchase of RM {$purchaseData['amount']} has been rejected. Reason: {$purchaseData['reason']}",
            'data' => $purchaseData,
        ]);
    }

    /**
     * Notify CP unlock
     * 
     * @param int $memberId Member ID
     * @param array $unlockData Unlock data
     * @return Notification
     */
    public static function notifyCpUnlock($memberId, $unlockData)
    {
        return self::createForMember([
            'member_id' => $memberId,
            'type' => 'cp_unlock',
            'title' => 'New Levels Unlocked!',
            'message' => "Congratulations! You've unlocked levels {$unlockData['from_level']}-{$unlockData['to_level']} and released {$unlockData['released_cp']} CP!",
            'data' => $unlockData,
        ]);
    }

    /**
     * Notify referral invite
     * 
     * @param int $memberId Member ID
     * @param array $referralData Referral data
     * @return Notification
     */
    public static function notifyReferralInvite($memberId, $referralData)
    {
        return self::createForMember([
            'member_id' => $memberId,
            'type' => 'referral_invite',
            'title' => 'New Referral!',
            'message' => "{$referralData['new_member_name']} joined using your referral code!",
            'data' => $referralData,
        ]);
    }

    /**
     * Notify merchant purchase request
     * 
     * @param int $merchantId Merchant ID
     * @param array $purchaseData Purchase data
     * @return Notification
     */
    public static function notifyMerchantPurchaseRequest($merchantId, $purchaseData)
    {
        return self::createForMerchant([
            'merchant_id' => $merchantId,
            'type' => 'point_approval',
            'title' => 'New Purchase Request',
            'message' => "New purchase request of RM {$purchaseData['amount']} from {$purchaseData['member_name']}. Please review.",
            'data' => $purchaseData,
        ]);
    }

    /**
     * Check if notification is read
     * 
     * @return bool
     */
    public function isRead()
    {
        return $this->status === 'read' && $this->is_read;
    }

    /**
     * Check if notification is unread
     * 
     * @return bool
     */
    public function isUnread()
    {
        return $this->status === 'unread' && !$this->is_read;
    }

    /**
     * Get time ago string
     * 
     * @return string
     */
    public function getTimeAgoAttribute()
    {
        return $this->created_at->diffForHumans();
    }
}
