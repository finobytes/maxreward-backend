<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderCancelReason extends Model
{
    use HasFactory;

    protected $table = 'order_cancel_reasons';

    protected $fillable = [
        'order_id',
        'cancelled_by_type',
        'cancelled_by_id',
        'reason_type',
        'reason_details',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Get cancellation reason types
     */
    public static function getReasonTypes()
    {
        return [
            'out_of_stock' => 'Product out of stock',
            'customer_request' => 'Customer requested cancellation',
            'wrong_order' => 'Wrong order placed',
            'payment_issue' => 'Payment issue',
            'delivery_issue' => 'Delivery not possible',
            'merchant_unavailable' => 'Merchant temporarily unavailable',
            'duplicate_order' => 'Duplicate order',
            'other' => 'Other reason',
        ];
    }

    /**
     * Scope by cancelled type
     */
    public function scopeByCancelledType($query, $type)
    {
        return $query->where('cancelled_by_type', $type);
    }
}
