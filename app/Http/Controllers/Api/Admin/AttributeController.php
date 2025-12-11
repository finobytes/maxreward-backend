<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Attribute;

class AttributeController extends Controller
{
    /**
     * Get all attributes with pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Query builder
            $query = Attribute::query();

            // Search by name or slug (optional)
            if ($request->has('search') && !empty($request->search)) {
                $query->where(function($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('slug', 'LIKE', '%' . $request->search . '%');
                });
            }

            // Get pagination limit (default: 10)
            $perPage = $request->get('per_page', 10);

            // Fetch attributes with pagination
            $attributes = $query->orderBy('created_at', 'desc')
                           ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Attributes retrieved successfully',
                'data' => $attributes
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attributes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all attributes without pagination
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllAttributes()
    {
        try {
            $attributes = Attribute::orderBy('name', 'asc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Attributes retrieved successfully',
                'data' => [
                    'attributes' => $attributes,
                    'total' => $attributes->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attributes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single attribute by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $attribute = Attribute::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Attribute retrieved successfully',
                'data' => $attribute
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attribute',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new attribute
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:attributes,name',
            'slug' => 'nullable|string|max:120|unique:attributes,slug',
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

            // Create attribute
            $attribute = Attribute::create([
                'name' => $request->name,
                'slug' => $request->slug,
            ]);

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Attribute created successfully',
                'data' => $attribute
            ], 201);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create attribute',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update attribute information
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100|unique:attributes,name,' . $id,
            'slug' => 'nullable|string|max:120|unique:attributes,slug,' . $id,
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

            // Find attribute
            $attribute = Attribute::findOrFail($id);

            // Update attribute data (only fields that are provided)
            if ($request->has('name')) {
                $attribute->name = $request->name;
            }
            if ($request->has('slug')) {
                $attribute->slug = $request->slug;
            }

            // Save changes
            $attribute->save();

            // Commit transaction
            DB::commit();

            // Refresh attribute data
            $attribute->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Attribute updated successfully',
                'data' => $attribute
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Attribute not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update attribute',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete attribute
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Start database transaction
            DB::beginTransaction();

            // Find attribute
            $attribute = Attribute::findOrFail($id);

            // Store info for response
            $attributeInfo = [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'slug' => $attribute->slug,
            ];

            // Delete the attribute
            $attribute->delete();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Attribute deleted successfully',
                'data' => $attributeInfo
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Attribute not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attribute',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
