<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Gender;

class GenderController extends Controller
{
    /**
     * Get all genders with pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Query builder
            $query = Gender::query();

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

            // Get pagination limit (default: 10)
            $perPage = $request->get('per_page', 10);

            // Fetch genders with pagination
            $genders = $query->orderBy('created_at', 'desc')
                           ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Genders retrieved successfully',
                'data' => $genders
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve genders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all genders without pagination
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllGenders()
    {
        try {
            $genders = Gender::orderBy('name', 'asc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Genders retrieved successfully',
                'data' => [
                    'genders' => $genders,
                    'total' => $genders->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve genders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single gender by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $gender = Gender::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Gender retrieved successfully',
                'data' => $gender
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gender not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve gender',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new gender
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:genders,name',
            'slug' => 'nullable|string|max:60|unique:genders,slug',
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

            // Create gender
            $gender = Gender::create([
                'name' => $request->name,
                'slug' => $request->slug,
                'is_active' => $request->is_active ?? true,
            ]);

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Gender created successfully',
                'data' => $gender
            ], 201);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create gender',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update gender information
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:50|unique:genders,name,' . $id,
            'slug' => 'nullable|string|max:60|unique:genders,slug,' . $id,
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

            // Find gender
            $gender = Gender::findOrFail($id);

            // Update gender data (only fields that are provided)
            if ($request->has('name')) {
                $gender->name = $request->name;
            }
            if ($request->has('slug')) {
                $gender->slug = $request->slug;
            }
            if ($request->has('is_active')) {
                $gender->is_active = $request->is_active;
            }

            // Save changes
            $gender->save();

            // Commit transaction
            DB::commit();

            // Refresh gender data
            $gender->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Gender updated successfully',
                'data' => $gender
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gender not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update gender',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete gender
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Start database transaction
            DB::beginTransaction();

            // Find gender
            $gender = Gender::findOrFail($id);

            // Store info for response
            $genderInfo = [
                'id' => $gender->id,
                'name' => $gender->name,
                'slug' => $gender->slug,
            ];

            // Delete the gender
            $gender->delete();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Gender deleted successfully',
                'data' => $genderInfo
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gender not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete gender',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
