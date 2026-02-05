<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderOnholdPoint extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'order_onhold_points';

    protected $fillable = [
        'order_id',
        'merchant_id',
        'member_id',
        'total_points',
        'shipping_points',
        'items_points',
        'status',
        'shipped_at',
        'auto_release_at',
        'released_at',
        'refunded_at',
        'refund_reason',
    ];

    protected $casts = [
        'total_points' => 'double',
        'shipping_points' => 'double',
        'items_points' => 'double',
        'shipped_at' => 'datetime',
        'auto_release_at' => 'datetime',
        'released_at' => 'datetime',
        'refunded_at' => 'datetime',
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

    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * Create onhold points record when order is placed
     */
    public static function createFromOrder(Order $order)
    {
        return self::create([
            'order_id' => $order->id,
            'merchant_id' => $order->merchant_id,
            'member_id' => $order->member_id,
            'total_points' => $order->total_points,
            'shipping_points' => $order->shipping_points,
            'items_points' => $order->total_points - $order->shipping_points,
            'status' => 'onhold',
        ]);
    }

    /**
     * Mark as shipped and set auto-release date
     */
    public function markAsShipped($autoReleaseDays = 5)
    {
        $this->shipped_at = Carbon::now();
        $this->auto_release_at = Carbon::now()->addDays($autoReleaseDays);
        $this->save();

        return $this;
    }

    /**
     * Release points (distribute to merchant, PP, RP, CP, CR)
     */
    public function releasePoints()
    {
        if ($this->status !== 'onhold') {
            return false;
        }

        $this->status = 'released';
        $this->released_at = Carbon::now();
        $this->save();

        return true;
    }

    /**
     * Refund points back to member
     */
    public function refundPoints($reason = null)
    {
        if ($this->status !== 'onhold') {
            return false;
        }

        $this->status = 'refunded';
        $this->refunded_at = Carbon::now();
        $this->refund_reason = $reason;
        $this->save();

        return true;
    }

    /**
     * Scopes
     */
    public function scopeOnhold($query)
    {
        return $query->where('status', 'onhold');
    }

    public function scopeReleased($query)
    {
        return $query->where('status', 'released');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    public function scopeReadyForRelease($query)
    {
        return $query->where('status', 'onhold')
            ->whereNotNull('auto_release_at')
            ->where('auto_release_at', '<=', Carbon::now());
    }

    /**
     * Check if ready for auto-release
     */
    public function isReadyForRelease()
    {
        return $this->status === 'onhold' 
            && $this->auto_release_at 
            && Carbon::now()->greaterThanOrEqualTo($this->auto_release_at);
    }
}