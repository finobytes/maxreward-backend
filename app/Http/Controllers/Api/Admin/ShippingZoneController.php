<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use App\Models\ShippingZoneArea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ShippingZoneController extends Controller
{
    /**
     * Get all shipping zones with areas
     * GET /api/admin/shipping-zones
     */
    public function index(Request $request)
    {
        try {
            $query = ShippingZone::with('areas');

            // Search
            if ($request->has('search') && $request->search) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'like', '%' . $searchTerm . '%')
                      ->orWhere('zone_code', 'like', '%' . $searchTerm . '%');
                });
            }

            // Filter by region
            if ($request->has('region') && $request->region) {
                $query->where('region', $request->region);
            }

            // Filter by status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // Sort
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            
            if ($request->get('all') === 'true') {
                $zones = $query->get();
                return response()->json([
                    'success' => true,
                    'data' => $zones
                ]);
            }

            $zones = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $zones,
                'meta' => [
                    'total' => $zones->total(),
                    'per_page' => $zones->perPage(),
                    'current_page' => $zones->currentPage(),
                    'last_page' => $zones->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch shipping zones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single shipping zone
     * GET /api/admin/shipping-zones/{id}
     */
    public function show($id)
    {
        try {
            $zone = ShippingZone::with('areas')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $zone
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping zone not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch shipping zone',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new shipping zone
     * POST /api/admin/shipping-zones
     * 
     * Body:
     * {
     *   "name": "West Malaysia - Central",
     *   "zone_code": "WM_CENTRAL",
     *   "region": "west_malaysia",
     *   "description": "Central region description",
     *   "postcodes": ["40", "41", "50", "51"]
     * }
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'zone_code' => 'required|string|max:50|unique:shipping_zones,zone_code',
            'region' => 'required|in:west_malaysia,east_malaysia,remote',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'postcodes' => 'required|array|min:1',
            'postcodes.*' => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            // Create zone
            $zone = ShippingZone::create([
                'name' => $request->name,
                'zone_code' => strtoupper($request->zone_code),
                'region' => $request->region,
                'description' => $request->description,
                'is_active' => $request->is_active ?? true,
            ]);

            // Add postcode areas
            foreach ($request->postcodes as $postcode) {
                ShippingZoneArea::create([
                    'zone_id' => $zone->id,
                    'postcode_prefix' => $postcode,
                ]);
            }

            DB::commit();

            $zone->load('areas');

            return response()->json([
                'success' => true,
                'message' => 'Shipping zone created successfully',
                'data' => $zone
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create shipping zone',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update shipping zone
     * PUT /api/admin/shipping-zones/{id}
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'zone_code' => 'sometimes|string|max:50|unique:shipping_zones,zone_code,' . $id,
            'region' => 'sometimes|in:west_malaysia,east_malaysia,remote',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'postcodes' => 'sometimes|array|min:1',
            'postcodes.*' => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            $zone = ShippingZone::findOrFail($id);

            // Update zone
            $zone->update($request->only([
                'name', 'zone_code', 'region', 'description', 'is_active'
            ]));

            // Update postcodes if provided
            if ($request->has('postcodes')) {
                // Delete old areas
                ShippingZoneArea::where('zone_id', $zone->id)->delete();
                
                // Add new areas
                foreach ($request->postcodes as $postcode) {
                    ShippingZoneArea::create([
                        'zone_id' => $zone->id,
                        'postcode_prefix' => $postcode,
                    ]);
                }
            }

            DB::commit();

            $zone->load('areas');

            return response()->json([
                'success' => true,
                'message' => 'Shipping zone updated successfully',
                'data' => $zone
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Shipping zone not found'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update shipping zone',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete shipping zone
     * DELETE /api/admin/shipping-zones/{id}
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        
        try {
            $zone = ShippingZone::findOrFail($id);

            // Check if zone is being used in merchant rates
            $usageCount = $zone->merchantRates()->count();
            
            if ($usageCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete zone. It's being used in {$usageCount} merchant shipping rate(s)",
                    'usage_count' => $usageCount
                ], 400);
            }

            // Delete zone areas first
            ShippingZoneArea::where('zone_id', $zone->id)->delete();
            
            // Delete zone
            $zone->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Shipping zone deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Shipping zone not found'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete shipping zone',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle zone status
     * PATCH /api/admin/shipping-zones/{id}/toggle-status
     */
    public function toggleStatus($id)
    {
        try {
            $zone = ShippingZone::findOrFail($id);
            $zone->is_active = !$zone->is_active;
            $zone->save();

            return response()->json([
                'success' => true,
                'message' => 'Zone status updated successfully',
                'data' => [
                    'id' => $zone->id,
                    'is_active' => $zone->is_active,
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping zone not found'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update zone status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detect zone by postcode
     * GET /api/admin/shipping-zones/detect-by-postcode?postcode=50480
     */
    public function detectByPostcode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'postcode' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $zone = ShippingZone::detectZoneByPostcode($request->postcode);

            if (!$zone) {
                return response()->json([
                    'success' => false,
                    'message' => 'No shipping zone found for this postcode',
                    'postcode' => $request->postcode
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'postcode' => $request->postcode,
                    'zone' => $zone
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to detect zone',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available regions
     * GET /api/admin/shipping-zones/regions
     */
    public function getRegions()
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['value' => 'west_malaysia', 'label' => 'West Malaysia'],
                ['value' => 'east_malaysia', 'label' => 'East Malaysia'],
                ['value' => 'remote', 'label' => 'Remote Areas'],
            ]
        ]);
    }
}