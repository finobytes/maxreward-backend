<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\AttributeItem;

class AttributeItemController extends Controller
{
    /**
     * Get all attribute items with pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Query builder
            $query = AttributeItem::with('attribute');

            // Search by name or slug (optional)
            if ($request->has('search') && !empty($request->search)) {
                $query->where(function($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('slug', 'LIKE', '%' . $request->search . '%');
                });
            }

            // Filter by is_active (optional)
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // Filter by attribute_id (optional)
            if ($request->has('attribute_id')) {
                $query->where('attribute_id', $request->attribute_id);
            }

            // Get pagination limit (default: 10)
            $perPage = $request->get('per_page', 10);

            // Fetch attribute items with pagination
            $attributeItems = $query->orderBy('created_at', 'desc')
                           ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Attribute items retrieved successfully',
                'data' => $attributeItems
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attribute items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all attribute items without pagination
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllAttributeItems()
    {
        try {
            $attributeItems = AttributeItem::with('attribute')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Attribute items retrieved successfully',
                'data' => [
                    'attribute_items' => $attributeItems,
                    'total' => $attributeItems->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attribute items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single attribute item by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $attributeItem = AttributeItem::with('attribute')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Attribute item retrieved successfully',
                'data' => $attributeItem
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute item not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attribute item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new attribute item
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'attribute_id' => 'required|integer|exists:attributes,id',
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:120',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Start database transaction
            DB::beginTransaction();

            // Check if combination of attribute_id and slug already exists
            $slug = $request->slug ?: \Illuminate\Support\Str::slug($request->name);
            $exists = AttributeItem::where('attribute_id', $request->attribute_id)
                ->where('slug', $slug)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => [
                        'slug' => ['This attribute item already exists for the selected attribute.']
                    ]
                ], 422);
            }

            // Create attribute item
            $attributeItem = AttributeItem::create([
                'attribute_id' => $request->attribute_id,
                'name' => $request->name,
                'slug' => $request->slug,
                'is_active' => $request->is_active ?? true,
            ]);

            // Load relationship
            $attributeItem->load('attribute');

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Attribute item created successfully',
                'data' => $attributeItem
            ], 201);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create attribute item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update attribute item information
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'attribute_id' => 'sometimes|required|integer|exists:attributes,id',
            'name' => 'sometimes|required|string|max:100',
            'slug' => 'nullable|string|max:120',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Start database transaction
            DB::beginTransaction();

            // Find attribute item
            $attributeItem = AttributeItem::findOrFail($id);

            // Get attribute_id (either from request or existing)
            $attributeId = $request->has('attribute_id') ? $request->attribute_id : $attributeItem->attribute_id;

            // Check if combination of attribute_id and slug already exists (excluding current item)
            if ($request->has('slug') || $request->has('name')) {
                $slug = $request->slug ?: ($request->has('name') ? \Illuminate\Support\Str::slug($request->name) : $attributeItem->slug);
                $exists = AttributeItem::where('attribute_id', $attributeId)
                    ->where('slug', $slug)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($exists) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation error',
                        'errors' => [
                            'slug' => ['This attribute item already exists for the selected attribute.']
                        ]
                    ], 422);
                }
            }

            // Update attribute item data (only fields that are provided)
            if ($request->has('attribute_id')) {
                $attributeItem->attribute_id = $request->attribute_id;
            }
            if ($request->has('name')) {
                $attributeItem->name = $request->name;
            }
            if ($request->has('slug')) {
                $attributeItem->slug = $request->slug;
            }
            if ($request->has('is_active')) {
                $attributeItem->is_active = $request->is_active;
            }

            // Save changes
            $attributeItem->save();

            // Load relationship
            $attributeItem->load('attribute');

            // Commit transaction
            DB::commit();

            // Refresh attribute item data
            $attributeItem->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Attribute item updated successfully',
                'data' => $attributeItem
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Attribute item not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update attribute item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete attribute item
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Start database transaction
            DB::beginTransaction();

            // Find attribute item
            $attributeItem = AttributeItem::findOrFail($id);

            // Store info for response
            $attributeItemInfo = [
                'id' => $attributeItem->id,
                'attribute_id' => $attributeItem->attribute_id,
                'name' => $attributeItem->name,
                'slug' => $attributeItem->slug,
            ];

            // Delete the attribute item
            $attributeItem->delete();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Attribute item deleted successfully',
                'data' => $attributeItemInfo
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Attribute item not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attribute item',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
