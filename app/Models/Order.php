<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'orders';

    protected $fillable = [
        'merchant_id',
        'member_id',
        'order_number',
        'status',
        'shipping_points',
        'total_points',
        'customer_full_name',
        'customer_email',
        'customer_phone',
        'customer_address',
        'customer_postcode',
        'customer_city',
        'customer_state',
        'customer_country',
        'tracking_number',
        'total_weight',
        'completed_at',
        'cancelled_by',
        'cancelled_reason',
        'deleted_by',
    ];

    protected $casts = [
        'shipping_points' => 'double',
        'total_points' => 'double',
        'total_weight' => 'decimal:2',
        'completed_at' => 'datetime',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function cancelReason()
    {
        return $this->hasOne(OrderCancelReason::class, 'order_id');
    }

    /**
     * Generate unique order number: YYYYMMDDHMS-8chars
     * Example: 20250118143025-AB3XY9ZQ
     */
    public static function generateOrderNumber()
    {
        do {
            $timestamp = Carbon::now()->format('YmdHis'); // YYYYMMDDHMS
            $random = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
            $orderNumber = $timestamp . '-' . $random;
        } while (self::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    /**
     * Mark order as completed
     */
    public function markAsCompleted()
    {
        $this->status = 'completed';
        $this->completed_at = Carbon::now();
        $this->save();

        return $this;
    }

    /**
     * Cancel order with reason
     */
    public function cancelOrder($cancelledByType, $cancelledById, $reasonType, $reasonDetails = null)
    {
        $this->status = 'cancelled';
        $this->cancelled_by = $cancelledById;
        $this->save();

        // Create cancel reason record
        OrderCancelReason::create([
            'order_id' => $this->id,
            'cancelled_by_type' => $cancelledByType,
            'cancelled_by_id' => $cancelledById,
            'reason_type' => $reasonType,
            'reason_details' => $reasonDetails,
        ]);

        return $this;
    }

    /**
     * Mark order as returned
     */
    public function markAsReturned()
    {
        $this->status = 'returned';
        $this->save();

        return $this;
    }

    /**
     * Update tracking number
     */
    public function updateTracking($trackingNumber)
    {
        $this->tracking_number = $trackingNumber;
        $this->save();

        return $this;
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeReturned($query)
    {
        return $query->where('status', 'returned');
    }

    public function scopeByMerchant($query, $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    public function scopeByMember($query, $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Get order summary
     */
    public function getSummary()
    {
        return [
            'order_number' => $this->order_number,
            'status' => $this->status,
            'total_points' => $this->total_points,
            'items_count' => $this->items->count(),
            'merchant' => $this->merchant->business_name,
            'customer' => $this->customer_full_name,
            'created_at' => $this->created_at,
        ];
    }
}
