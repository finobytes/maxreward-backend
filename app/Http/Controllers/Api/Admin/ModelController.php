<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\ProductModel;

class ModelController extends Controller
{
    /**
     * Get all models with pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Query builder
            $query = ProductModel::query();

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

            // Filter by brand_id (optional)
            if ($request->has('brand_id')) {
                $query->where('brand_id', $request->brand_id);
            }

            // Get pagination limit (default: 10)
            $perPage = $request->get('per_page', 10);

            // Fetch models with pagination
            $models = $query->orderBy('created_at', 'desc')
                           ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Models retrieved successfully',
                'data' => $models
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve models',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all models without pagination
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllModels()
    {
        try {
            $models = ProductModel::orderBy('name', 'asc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Models retrieved successfully',
                'data' => [
                    'models' => $models,
                    'total' => $models->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve models',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single model by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $model = ProductModel::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Model retrieved successfully',
                'data' => $model
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Model not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve model',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new model
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:models,name',
            'slug' => 'nullable|string|max:120|unique:models,slug',
            'brand_id' => 'nullable|integer|exists:brands,id',
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

            // Create model
            $model = ProductModel::create([
                'name' => $request->name,
                'slug' => $request->slug,
                'brand_id' => $request->brand_id,
                'is_active' => $request->is_active ?? true,
            ]);

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Model created successfully',
                'data' => $model
            ], 201);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create model',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update model information
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100|unique:models,name,' . $id,
            'slug' => 'nullable|string|max:120|unique:models,slug,' . $id,
            'brand_id' => 'nullable|integer|exists:brands,id',
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

            // Find model
            $model = ProductModel::findOrFail($id);

            // Update model data (only fields that are provided)
            if ($request->has('name')) {
                $model->name = $request->name;
            }
            if ($request->has('slug')) {
                $model->slug = $request->slug;
            }
            if ($request->has('brand_id')) {
                $model->brand_id = $request->brand_id;
            }
            if ($request->has('is_active')) {
                $model->is_active = $request->is_active;
            }

            // Save changes
            $model->save();

            // Commit transaction
            DB::commit();

            // Refresh model data
            $model->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Model updated successfully',
                'data' => $model
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Model not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update model',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete model
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Start database transaction
            DB::beginTransaction();

            // Find model
            $model = ProductModel::findOrFail($id);

            // Store info for response
            $modelInfo = [
                'id' => $model->id,
                'name' => $model->name,
                'slug' => $model->slug,
            ];

            // Delete the model
            $model->delete();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Model deleted successfully',
                'data' => $modelInfo
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Model not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete model',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
