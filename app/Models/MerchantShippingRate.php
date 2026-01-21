<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantShippingRate extends Model
{
    protected $fillable = [
        'merchant_id', 'zone_id', 'method_id',
        'weight_from', 'weight_to',
        'base_points', 'per_kg_points',
        'free_shipping_min_order', 'is_active'
    ];

    protected $casts = [
        'weight_from' => 'decimal:2',
        'weight_to' => 'decimal:2',
        'base_points' => 'double',
        'per_kg_points' => 'double',
        'free_shipping_min_order' => 'double',
        'is_active' => 'boolean',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function zone()
    {
        return $this->belongsTo(ShippingZone::class);
    }

    public function method()
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public static function calculateShipping($merchantId, $zoneId, $methodId, $totalWeight, $orderTotal = 0)
    {
        $rate = self::where('merchant_id', $merchantId)
            ->where('zone_id', $zoneId)
            ->where('method_id', $methodId)
            ->where('weight_from', '<=', $totalWeight)
            ->where('weight_to', '>=', $totalWeight)
            ->where('is_active', true)
            ->first();

        if (!$rate) {
            return null;
        }

        // Check free shipping
        if ($rate->free_shipping_min_order && $orderTotal >= $rate->free_shipping_min_order) {
            return [
                'shipping_points' => 0,
                'is_free_shipping' => true,
                'rate' => $rate,
            ];
        }

        // Calculate shipping cost
        $weightInKg = $totalWeight / 1000;
        $shippingPoints = $rate->base_points + ($weightInKg * $rate->per_kg_points);

        return [
            'shipping_points' => round($shippingPoints, 2),
            'is_free_shipping' => false,
            'rate' => $rate,
        ];
    }
}
