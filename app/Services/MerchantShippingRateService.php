<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\MerchantShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingMethod;
use Illuminate\Support\Facades\Log;

class MerchantShippingRateService
{
    /**
     * Create default shipping rates for a merchant
     * This will create rates for all Malaysian zones with all 3 methods
     * 
     * @param Merchant $merchant
     * @return bool
     */
    public function createDefaultRates(Merchant $merchant)
    {
        try {
            // Get all active Malaysian zones
            $zones = ShippingZone::where('is_active', true)->get();
            
            // Get all active shipping methods
            $methods = ShippingMethod::where('is_active', true)->orderBy('sort_order')->get();
            
            if ($zones->isEmpty() || $methods->isEmpty()) {
                Log::warning("Cannot create default shipping rates: zones or methods not found", [
                    'merchant_id' => $merchant->id,
                    'zones_count' => $zones->count(),
                    'methods_count' => $methods->count()
                ]);
                return false;
            }
            
            // Define default weight ranges and their corresponding rates
            $weightRanges = [
                [
                    'weight_from' => 0.00,
                    'weight_to' => 1000.00,
                    'base_points' => 50,
                    'per_kg_points' => 10
                ],
                [
                    'weight_from' => 1001.00,
                    'weight_to' => 3000.00,
                    'base_points' => 80,
                    'per_kg_points' => 15
                ],
                [
                    'weight_from' => 3001.00,
                    'weight_to' => 100000.00,
                    'base_points' => 150,
                    'per_kg_points' => 20
                ]
            ];
            
            // Method-specific adjustments
            $methodMultipliers = [
                'ECONOMY' => 0.8,    // 20% cheaper
                'STANDARD' => 1.0,   // Base price
                'EXPRESS' => 1.5     // 50% more expensive
            ];
            
            // Zone-specific adjustments (East Malaysia is more expensive)
            $zoneMultipliers = [
                'EM_SABAH' => 1.3,      // 30% more for Sabah
                'EM_SARAWAK' => 1.3,    // 30% more for Sarawak
            ];
            
            $createdCount = 0;
            
            // Create rates for each zone, method, and weight range combination
            foreach ($zones as $zone) {
                foreach ($methods as $method) {
                    foreach ($weightRanges as $range) {
                        
                        // Calculate adjusted rates based on zone and method
                        $zoneMultiplier = $zoneMultipliers[$zone->zone_code] ?? 1.0;
                        $methodMultiplier = $methodMultipliers[$method->code] ?? 1.0;
                        
                        $basePoints = $range['base_points'] * $zoneMultiplier * $methodMultiplier;
                        $perKgPoints = $range['per_kg_points'] * $zoneMultiplier * $methodMultiplier;
                        
                        MerchantShippingRate::create([
                            'merchant_id' => $merchant->id,
                            'zone_id' => $zone->id,
                            'method_id' => $method->id,
                            'weight_from' => $range['weight_from'],
                            'weight_to' => $range['weight_to'],
                            'base_points' => round($basePoints, 2),
                            'per_kg_points' => round($perKgPoints, 2),
                            'free_shipping_min_order' => null, // Merchant can set this later
                            'is_active' => true
                        ]);
                        
                        $createdCount++;
                    }
                }
            }
            
            Log::info("Default shipping rates created successfully", [
                'merchant_id' => $merchant->id,
                'rates_created' => $createdCount
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Failed to create default shipping rates", [
                'merchant_id' => $merchant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }
    
    /**
     * Check if merchant already has shipping rates
     * 
     * @param Merchant $merchant
     * @return bool
     */
    public function hasShippingRates(Merchant $merchant)
    {
        return MerchantShippingRate::where('merchant_id', $merchant->id)->exists();
    }
    
    /**
     * Delete all shipping rates for a merchant
     * 
     * @param Merchant $merchant
     * @return bool
     */
    public function deleteAllRates(Merchant $merchant)
    {
        try {
            MerchantShippingRate::where('merchant_id', $merchant->id)->delete();
            
            Log::info("Shipping rates deleted", [
                'merchant_id' => $merchant->id
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Failed to delete shipping rates", [
                'merchant_id' => $merchant->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
}