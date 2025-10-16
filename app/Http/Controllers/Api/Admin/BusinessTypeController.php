<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\BusinessType;

class BusinessTypeController extends Controller
{
    /**
     * Get all business types with pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Query builder
            $query = BusinessType::query();

            // Search by name (optional)
            if ($request->has('search')) {
                $query->where('name', 'LIKE', '%' . $request->search . '%');
            }

            // Get pagination limit (default: 10)
            $perPage = $request->get('per_page', 10);

            // Fetch business types with pagination
            $businessTypes = $query->orderBy('created_at', 'desc')
                                   ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Business types retrieved successfully',
                'data' => $businessTypes
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve business types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all business types without pagination
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllBusinessTypes()
    {
        try {
            $businessTypes = BusinessType::orderBy('name', 'asc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Business types retrieved successfully',
                'data' => [
                    'business_types' => $businessTypes,
                    'total' => $businessTypes->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve business types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single business type by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $businessType = BusinessType::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Business type retrieved successfully',
                'data' => $businessType
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Business type not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve business type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new business type
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:business_types,name',
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

            // Create business type
            $businessType = BusinessType::create([
                'name' => $request->name,
            ]);

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Business type created successfully',
                'data' => $businessType
            ], 201);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create business type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update business type information
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:business_types,name,' . $id,
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

            // Find business type
            $businessType = BusinessType::findOrFail($id);

            // Update business type
            $businessType->update([
                'name' => $request->name,
            ]);

            // Commit transaction
            DB::commit();

            // Refresh business type data
            $businessType->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Business type updated successfully',
                'data' => $businessType
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Business type not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update business type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete business type
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Start database transaction
            DB::beginTransaction();

            // Find business type
            $businessType = BusinessType::findOrFail($id);

            // Store info for response
            $businessTypeInfo = [
                'id' => $businessType->id,
                'name' => $businessType->name,
            ];

            // Delete the business type
            $businessType->delete();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Business type deleted successfully',
                'data' => $businessTypeInfo
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Business type not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete business type',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
