<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Models\MerchantShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MerchantShippingRateController extends Controller
{
    /**
     * Get merchant's shipping rates
     * GET /api/merchant/shipping-rates
     */
    public function index(Request $request)
    {
        try {
            $merchant = auth('merchant')->user();
            
            $query = MerchantShippingRate::with(['zone', 'method'])
                ->where('merchant_id', $merchant->merchant_id);

            // Filter by zone
            if ($request->has('zone_id') && $request->zone_id) {
                $query->where('zone_id', $request->zone_id);
            }

            // Filter by method
            if ($request->has('method_id') && $request->method_id) {
                $query->where('method_id', $request->method_id);
            }

            // Filter by status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Get all or paginate
            if ($request->get('all') === 'true') {
                $rates = $query->get();
                
                // Group by zone
                $grouped = $rates->groupBy('zone.name')->map(function($zoneRates) {
                    return [
                        'zone' => $zoneRates->first()->zone,
                        'rates' => $zoneRates->groupBy('method.name')->map(function($methodRates) {
                            return [
                                'method' => $methodRates->first()->method,
                                'weight_ranges' => $methodRates->values()
                            ];
                        })->values()
                    ];
                })->values();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'rates_by_zone' => $grouped,
                        'total_rates' => $rates->count()
                    ]
                ]);
            }

            $perPage = $request->get('per_page', 15);
            $rates = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $rates,
                'meta' => [
                    'total' => $rates->total(),
                    'per_page' => $rates->perPage(),
                    'current_page' => $rates->currentPage(),
                    'last_page' => $rates->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch shipping rates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available zones and methods for setup
     * GET /api/merchant/shipping-rates/options
     */
    public function getOptions()
    {
        try {
            $zones = ShippingZone::where('is_active', true)
                ->orderBy('name')
                ->get();
                
            $methods = ShippingMethod::where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'zones' => $zones,
                    'methods' => $methods,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch options',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single shipping rate
     * GET /api/merchant/shipping-rates/{id}
     */
    public function show($id)
    {
        try {
            $merchant = auth('merchant')->user();
            
            $rate = MerchantShippingRate::with(['zone', 'method'])
                ->where('merchant_id', $merchant->merchant_id)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $rate
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping rate not found'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch shipping rate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new shipping rate
     * POST /api/merchant/shipping-rates
     * 
     * Body:
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
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $merchant = auth('merchant')->user();

        try {
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
                    'message' => 'Weight range overlaps with existing configuration for this zone and method'
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
                'is_active' => $request->is_active ?? true,
            ]);

            $rate->load(['zone', 'method']);

            return response()->json([
                'success' => true,
                'message' => 'Shipping rate created successfully',
                'data' => $rate
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create shipping rate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update shipping rate
     * PUT /api/merchant/shipping-rates/{id}
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'weight_from' => 'sometimes|numeric|min:0',
            'weight_to' => 'sometimes|numeric',
            'base_points' => 'sometimes|numeric|min:0',
            'per_kg_points' => 'sometimes|numeric|min:0',
            'free_shipping_min_order' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $merchant = auth('merchant')->user();

        try {
            $rate = MerchantShippingRate::where('merchant_id', $merchant->merchant_id)
                ->findOrFail($id);

            // Validate weight_to > weight_from if both provided
            $weightFrom = $request->weight_from ?? $rate->weight_from;
            $weightTo = $request->weight_to ?? $rate->weight_to;

            if ($weightTo <= $weightFrom) {
                return response()->json([
                    'success' => false,
                    'message' => 'Weight to must be greater than weight from'
                ], 422);
            }

            // Check for overlapping if weight range changed
            if ($request->has('weight_from') || $request->has('weight_to')) {
                $existing = MerchantShippingRate::where('merchant_id', $merchant->merchant_id)
                    ->where('zone_id', $rate->zone_id)
                    ->where('method_id', $rate->method_id)
                    ->where('id', '!=', $id)
                    ->where(function($q) use ($weightFrom, $weightTo) {
                        $q->whereBetween('weight_from', [$weightFrom, $weightTo])
                          ->orWhereBetween('weight_to', [$weightFrom, $weightTo])
                          ->orWhere(function($q2) use ($weightFrom, $weightTo) {
                              $q2->where('weight_from', '<=', $weightFrom)
                                 ->where('weight_to', '>=', $weightTo);
                          });
                    })
                    ->exists();

                if ($existing) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Weight range overlaps with existing configuration'
                    ], 400);
                }
            }

            $rate->update($request->only([
                'weight_from', 'weight_to', 'base_points', 
                'per_kg_points', 'free_shipping_min_order', 'is_active'
            ]));

            $rate->load(['zone', 'method']);

            return response()->json([
                'success' => true,
                'message' => 'Shipping rate updated successfully',
                'data' => $rate
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping rate not found'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update shipping rate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete shipping rate
     * DELETE /api/merchant/shipping-rates/{id}
     */
    public function destroy($id)
    {
        try {
            $merchant = auth('merchant')->user();
            
            $rate = MerchantShippingRate::where('merchant_id', $merchant->merchant_id)
                ->findOrFail($id);

            $rate->delete();

            return response()->json([
                'success' => true,
                'message' => 'Shipping rate deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping rate not found'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete shipping rate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle rate status
     * PATCH /api/merchant/shipping-rates/{id}/toggle-status
     */
    public function toggleStatus($id)
    {
        try {
            $merchant = auth('merchant')->user();
            
            $rate = MerchantShippingRate::where('merchant_id', $merchant->merchant_id)
                ->findOrFail($id);
                
            $rate->is_active = !$rate->is_active;
            $rate->save();

            return response()->json([
                'success' => true,
                'message' => 'Rate status updated successfully',
                'data' => [
                    'id' => $rate->id,
                    'is_active' => $rate->is_active,
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping rate not found'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update rate status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk create shipping rates for all zones
     * POST /api/merchant/shipping-rates/bulk-create
     * 
     * Body:
     * {
     *   "method_id": 1,
     *   "apply_to_zones": [1, 2, 3],  // or "all" for all zones
     *   "weight_ranges": [
     *     {"from": 0, "to": 1000, "base": 50, "per_kg": 10},
     *     {"from": 1001, "to": 3000, "base": 80, "per_kg": 15},
     *     {"from": 3001, "to": 10000, "base": 150, "per_kg": 20}
     *   ],
     *   "free_shipping_min_order": 500
     * }
     */
    public function bulkCreate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'method_id' => 'required|exists:shipping_methods,id',
            'apply_to_zones' => 'required',
            'weight_ranges' => 'required|array|min:1',
            'weight_ranges.*.from' => 'required|numeric|min:0',
            'weight_ranges.*.to' => 'required|numeric',
            'weight_ranges.*.base' => 'required|numeric|min:0',
            'weight_ranges.*.per_kg' => 'required|numeric|min:0',
            'free_shipping_min_order' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $merchant = auth('merchant')->user();

        DB::beginTransaction();
        
        try {
            // Get zones to apply
            if ($request->apply_to_zones === 'all') {
                $zones = ShippingZone::where('is_active', true)->get();
            } else {
                $zones = ShippingZone::whereIn('id', $request->apply_to_zones)
                    ->where('is_active', true)
                    ->get();
            }

            if ($zones->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid zones selected'
                ], 400);
            }

            $created = 0;
            $skipped = 0;

            foreach ($zones as $zone) {
                foreach ($request->weight_ranges as $range) {
                    // Check if already exists
                    $exists = MerchantShippingRate::where('merchant_id', $merchant->merchant_id)
                        ->where('zone_id', $zone->id)
                        ->where('method_id', $request->method_id)
                        ->where(function($q) use ($range) {
                            $q->whereBetween('weight_from', [$range['from'], $range['to']])
                              ->orWhereBetween('weight_to', [$range['from'], $range['to']])
                              ->orWhere(function($q2) use ($range) {
                                  $q2->where('weight_from', '<=', $range['from'])
                                     ->where('weight_to', '>=', $range['to']);
                              });
                        })
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    MerchantShippingRate::create([
                        'merchant_id' => $merchant->merchant_id,
                        'zone_id' => $zone->id,
                        'method_id' => $request->method_id,
                        'weight_from' => $range['from'],
                        'weight_to' => $range['to'],
                        'base_points' => $range['base'],
                        'per_kg_points' => $range['per_kg'],
                        'free_shipping_min_order' => $request->free_shipping_min_order,
                    ]);
                    
                    $created++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Bulk creation completed",
                'data' => [
                    'created' => $created,
                    'skipped' => $skipped,
                    'zones_processed' => $zones->count(),
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk create shipping rates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete all rates for a specific zone/method combination
     * DELETE /api/merchant/shipping-rates/bulk-delete
     * 
     * Body:
     * {
     *   "zone_id": 1,
     *   "method_id": 1
     * }
     */
    public function bulkDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'zone_id' => 'required|exists:shipping_zones,id',
            'method_id' => 'required|exists:shipping_methods,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $merchant = auth('merchant')->user();
            
            $deleted = MerchantShippingRate::where('merchant_id', $merchant->merchant_id)
                ->where('zone_id', $request->zone_id)
                ->where('method_id', $request->method_id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "{$deleted} shipping rate(s) deleted successfully",
                'deleted_count' => $deleted
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete shipping rates',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}