<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShippingMethodController extends Controller
{
    /**
     * Get all shipping methods
     * GET /api/admin/shipping-methods
     */
    public function index(Request $request)
    {
        try {
            $query = ShippingMethod::query();

            // Search
            if ($request->has('search') && $request->search) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'like', '%' . $searchTerm . '%')
                      ->orWhere('code', 'like', '%' . $searchTerm . '%');
                });
            }

            // Filter by status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // Sort
            $sortBy = $request->get('sort_by', 'sort_order');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Get all or paginate
            if ($request->get('all') === 'true') {
                $methods = $query->get();
                return response()->json([
                    'success' => true,
                    'data' => $methods
                ]);
            }

            $perPage = $request->get('per_page', 15);
            $methods = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $methods,
                'meta' => [
                    'total' => $methods->total(),
                    'per_page' => $methods->perPage(),
                    'current_page' => $methods->currentPage(),
                    'last_page' => $methods->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch shipping methods',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single shipping method
     * GET /api/admin/shipping-methods/{id}
     */
    public function show($id)
    {
        try {
            $method = ShippingMethod::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $method
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping method not found'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch shipping method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new shipping method
     * POST /api/admin/shipping-methods
     * 
     * Body:
     * {
     *   "name": "Standard Delivery",
     *   "code": "STANDARD",
     *   "description": "Delivery within 3-5 days",
     *   "min_days": 3,
     *   "max_days": 5,
     *   "sort_order": 1
     * }
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:shipping_methods,code',
            'description' => 'nullable|string',
            'min_days' => 'required|integer|min:0',
            'max_days' => 'required|integer|min:0|gte:min_days',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $method = ShippingMethod::create([
                'name' => $request->name,
                'code' => strtoupper($request->code),
                'description' => $request->description,
                'min_days' => $request->min_days,
                'max_days' => $request->max_days,
                'is_active' => $request->is_active ?? true,
                'sort_order' => $request->sort_order ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Shipping method created successfully',
                'data' => $method
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create shipping method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update shipping method
     * PUT /api/admin/shipping-methods/{id}
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:shipping_methods,code,' . $id,
            'description' => 'nullable|string',
            'min_days' => 'sometimes|integer|min:0',
            'max_days' => 'sometimes|integer|min:0',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate max_days >= min_days if both provided
        if ($request->has('min_days') && $request->has('max_days')) {
            if ($request->max_days < $request->min_days) {
                return response()->json([
                    'success' => false,
                    'message' => 'Max days must be greater than or equal to min days'
                ], 422);
            }
        }

        try {
            $method = ShippingMethod::findOrFail($id);

            $method->update($request->only([
                'name', 'code', 'description', 'min_days', 
                'max_days', 'is_active', 'sort_order'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Shipping method updated successfully',
                'data' => $method
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping method not found'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update shipping method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete shipping method
     * DELETE /api/admin/shipping-methods/{id}
     */
    public function destroy($id)
    {
        try {
            $method = ShippingMethod::findOrFail($id);

            // Check if method is being used in merchant rates
            $usageCount = $method->merchantRates()->count();
            
            if ($usageCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete method. It's being used in {$usageCount} merchant shipping rate(s)",
                    'usage_count' => $usageCount
                ], 400);
            }

            $method->delete();

            return response()->json([
                'success' => true,
                'message' => 'Shipping method deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping method not found'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete shipping method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle method status
     * PATCH /api/admin/shipping-methods/{id}/toggle-status
     */
    public function toggleStatus($id)
    {
        try {
            $method = ShippingMethod::findOrFail($id);
            $method->is_active = !$method->is_active;
            $method->save();

            return response()->json([
                'success' => true,
                'message' => 'Method status updated successfully',
                'data' => [
                    'id' => $method->id,
                    'is_active' => $method->is_active,
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping method not found'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update method status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder shipping methods
     * POST /api/admin/shipping-methods/reorder
     * 
     * Body:
     * {
     *   "orders": [
     *     {"id": 1, "sort_order": 1},
     *     {"id": 2, "sort_order": 2},
     *     {"id": 3, "sort_order": 3}
     *   ]
     * }
     */
    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'orders' => 'required|array|min:1',
            'orders.*.id' => 'required|exists:shipping_methods,id',
            'orders.*.sort_order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            foreach ($request->orders as $order) {
                ShippingMethod::where('id', $order['id'])
                    ->update(['sort_order' => $order['sort_order']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Shipping methods reordered successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder shipping methods',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active methods only (for frontend selection)
     * GET /api/shipping-methods/active
     */
    public function getActiveMethods()
    {
        try {
            $methods = ShippingMethod::active()->get();

            return response()->json([
                'success' => true,
                'data' => $methods
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active shipping methods',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}