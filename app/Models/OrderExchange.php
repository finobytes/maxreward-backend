<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderExchange extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'order_exchanges';

    protected $fillable = [
        'order_id',
        'order_item_id',
        'merchant_id',
        'member_id',
        'original_product_variation_id',
        'original_variant_name',
        'exchange_product_variation_id',
        'exchange_variant_name',
        'quantity',
        'reason',
        'status',
        'rejection_reason',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'processed_at' => 'datetime',
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

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function originalVariation()
    {
        return $this->belongsTo(ProductVariation::class, 'original_product_variation_id');
    }

    public function exchangeVariation()
    {
        return $this->belongsTo(ProductVariation::class, 'exchange_product_variation_id');
    }

    /**
     * Approve exchange and update stock
     */
    public function approve($processedBy = null)
    {
        if ($this->status !== 'pending') {
            return false;
        }

        // Return original variant to stock
        $originalVariation = $this->originalVariation;
        if ($originalVariation) {
            $originalVariation->actual_quantity += $this->quantity;
            $originalVariation->save();
        }

        // Deduct exchange variant from stock
        $exchangeVariation = $this->exchangeVariation;
        if ($exchangeVariation) {
            if ($exchangeVariation->actual_quantity < $this->quantity) {
                return false; // Insufficient stock
            }
            $exchangeVariation->actual_quantity -= $this->quantity;
            $exchangeVariation->save();
        }

        // Update exchange record
        $this->status = 'approved';
        $this->processed_by = $processedBy;
        $this->processed_at = Carbon::now();
        $this->save();

        return true;
    }

    /**
     * Reject exchange
     */
    public function reject($reason, $processedBy = null)
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $this->status = 'rejected';
        $this->rejection_reason = $reason;
        $this->processed_by = $processedBy;
        $this->processed_at = Carbon::now();
        $this->save();

        return true;
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted()
    {
        if ($this->status !== 'approved') {
            return false;
        }

        $this->status = 'completed';
        $this->save();

        return true;
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
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
     * Get original variation name (dynamic if not stored)
     */
    public function getOriginalVariantNameAttribute($value)
    {
        // If value exists in database, return it
        if (!empty($value)) {
            return $value;
        }
        
        // Otherwise generate dynamically
        if ($this->relationLoaded('originalVariation') && $this->originalVariation) {
            return $this->originalVariation->getVariationNameAttribute();
        }
        
        return $value;
    }

    /**
     * Get exchange variation name (dynamic if not stored)
     */
    public function getExchangeVariantNameAttribute($value)
    {
        // If value exists in database, return it
        if (!empty($value)) {
            return $value;
        }
        
        // Otherwise generate dynamically
        if ($this->relationLoaded('exchangeVariation') && $this->exchangeVariation) {
            return $this->exchangeVariation->getVariationNameAttribute();
        }
        
        return $value;
    }
}