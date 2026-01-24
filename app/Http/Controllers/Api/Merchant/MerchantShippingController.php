<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MerchantShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingMethod;
use Illuminate\Support\Facades\Validator;

class MerchantShippingController extends Controller
{
     /**
     * Get merchant's shipping configuration
     * GET /api/merchant/shipping-rates
     */
    public function index()
    {
        $merchant = auth('merchant')->user();
        
        $rates = MerchantShippingRate::with(['zone', 'method'])
            ->where('merchant_id', $merchant->merchant_id)
            ->get()
            ->groupBy('zone.name');

        $zones = ShippingZone::where('is_active', true)->get();
        $methods = ShippingMethod::active()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'rates_by_zone' => $rates,
                'available_zones' => $zones,
                'available_methods' => $methods,
            ]
        ]);
    }

    /**
     * Setup shipping rates for merchant
     * POST /api/merchant/shipping-rates
     * 
     * Example payload:
     * {
     *   "zone_id": 1,
     *   "method_id": 1,
     *   "weight_from": 0,
     *   "weight_to": 1000,
     *   "base_points": 50,
     *   "per_kg_points": 10,
     *   "free_shipping_min_order": 500
     * }
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'zone_id' => 'required|exists:shipping_zones,id',
            'method_id' => 'required|exists:shipping_methods,id',
            'weight_from' => 'required|numeric|min:0',
            'weight_to' => 'required|numeric|gt:weight_from',
            'base_points' => 'required|numeric|min:0',
            'per_kg_points' => 'required|numeric|min:0',
            'free_shipping_min_order' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $merchant = auth('merchant')->user();

        // Check for overlapping weight ranges
        $existing = MerchantShippingRate::where('merchant_id', $merchant->merchant_id)
            ->where('zone_id', $request->zone_id)
            ->where('method_id', $request->method_id)
            ->where(function($q) use ($request) {
                $q->whereBetween('weight_from', [$request->weight_from, $request->weight_to])
                  ->orWhereBetween('weight_to', [$request->weight_from, $request->weight_to])
                  ->orWhere(function($q2) use ($request) {
                      $q2->where('weight_from', '<=', $request->weight_from)
                         ->where('weight_to', '>=', $request->weight_to);
                  });
            })
            ->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Weight range overlaps with existing configuration'
            ], 400);
        }

        $rate = MerchantShippingRate::create([
            'merchant_id' => $merchant->merchant_id,
            'zone_id' => $request->zone_id,
            'method_id' => $request->method_id,
            'weight_from' => $request->weight_from,
            'weight_to' => $request->weight_to,
            'base_points' => $request->base_points,
            'per_kg_points' => $request->per_kg_points,
            'free_shipping_min_order' => $request->free_shipping_min_order,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shipping rate created successfully',
            'data' => $rate->load(['zone', 'method'])
        ], 201);
    }

    /**
     * Update shipping rate
     * PUT /api/merchant/shipping-rates/{id}
     */
    public function update(Request $request, $id)
    {
        $merchant = auth('merchant')->user();
        
        $rate = MerchantShippingRate::where('merchant_id', $merchant->merchant_id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'weight_from' => 'sometimes|numeric|min:0',
            'weight_to' => 'sometimes|numeric|gt:weight_from',
            'base_points' => 'sometimes|numeric|min:0',
            'per_kg_points' => 'sometimes|numeric|min:0',
            'free_shipping_min_order' => 'nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $rate->update($request->only([
            'weight_from', 'weight_to', 'base_points', 
            'per_kg_points', 'free_shipping_min_order', 'is_active'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Shipping rate updated successfully',
            'data' => $rate->load(['zone', 'method'])
        ]);
    }

    /**
     * Delete shipping rate
     * DELETE /api/merchant/shipping-rates/{id}
     */
    public function destroy($id)
    {
        $merchant = auth('merchant')->user();
        
        $rate = MerchantShippingRate::where('merchant_id', $merchant->merchant_id)
            ->findOrFail($id);

        $rate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shipping rate deleted successfully'
        ]);
    }

    /**
     * Bulk setup - Create default rates for all zones
     * POST /api/merchant/shipping-rates/bulk-setup
     * 
     * Example:
     * {
     *   "method_id": 1,
     *   "rates": [
     *     {"weight_from": 0, "weight_to": 1000, "base_points": 50, "per_kg_points": 10},
     *     {"weight_from": 1001, "weight_to": 3000, "base_points": 80, "per_kg_points": 15},
     *     {"weight_from": 3001, "weight_to": 10000, "base_points": 150, "per_kg_points": 20}
     *   ]
     * }
     */
    public function bulkSetup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'method_id' => 'required|exists:shipping_methods,id',
            'rates' => 'required|array|min:1',
            'rates.*.weight_from' => 'required|numeric|min:0',
            'rates.*.weight_to' => 'required|numeric',
            'rates.*.base_points' => 'required|numeric|min:0',
            'rates.*.per_kg_points' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $merchant = auth('merchant')->user();
        $zones = ShippingZone::where('is_active', true)->get();
        $created = 0;

        foreach ($zones as $zone) {
            foreach ($request->rates as $rateData) {
                MerchantShippingRate::create([
                    'merchant_id' => $merchant->merchant_id,
                    'zone_id' => $zone->id,
                    'method_id' => $request->method_id,
                    'weight_from' => $rateData['weight_from'],
                    'weight_to' => $rateData['weight_to'],
                    'base_points' => $rateData['base_points'],
                    'per_kg_points' => $rateData['per_kg_points'],
                ]);
                $created++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Created {$created} shipping rates across all zones",
            'zones_configured' => $zones->count(),
        ]);
    }
}
