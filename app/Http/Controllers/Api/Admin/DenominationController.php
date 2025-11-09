<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Denomination;

class DenominationController extends Controller
{
    /**
     * Get all denominations with pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Query builder
            $query = Denomination::query();

            // Search by title or value (optional)
            if ($request->has('search') && !empty($request->search)) {
                $query->where(function($q) use ($request) {
                    $q->where('title', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('value', 'LIKE', '%' . $request->search . '%');
                });
            }

            // Get pagination limit (default: 10)
            $perPage = $request->get('per_page', 10);

            // Fetch denominations with pagination
            $denominations = $query->orderBy('created_at', 'desc')
                                   ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Denominations retrieved successfully',
                'data' => $denominations
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve denominations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all denominations without pagination
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllDenominations()
    {
        try {
            $denominations = Denomination::orderBy('value', 'asc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Denominations retrieved successfully',
                'data' => [
                    'denominations' => $denominations,
                    'total' => $denominations->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve denominations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single denomination by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $denomination = Denomination::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Denomination retrieved successfully',
                'data' => $denomination
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Denomination not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve denomination',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new denomination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Check if already 3 denominations exist
        $denominationCount = Denomination::count();
        if ($denominationCount >= 3) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum 3 denominations allowed. Cannot add more.',
            ], 422);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255|unique:denominations,title',
            'value' => 'required|string|max:255|unique:denominations,value',
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

            // Create denomination
            $denomination = Denomination::create([
                'title' => $request->title,
                'value' => $request->value,
            ]);

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Denomination created successfully',
                'data' => $denomination
            ], 201);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create denomination',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update denomination information
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255|unique:denominations,title,' . $id,
            'value' => 'sometimes|required|string|max:255|unique:denominations,value,' . $id,
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

            // Find denomination
            $denomination = Denomination::findOrFail($id);

            // Update denomination data (only fields that are provided)
            $denominationData = [];

            if ($request->has('title')) {
                $denominationData['title'] = $request->title;
            }
            if ($request->has('value')) {
                $denominationData['value'] = $request->value;
            }

            if (!empty($denominationData)) {
                $denomination->update($denominationData);
            }

            // Commit transaction
            DB::commit();

            // Refresh denomination data
            $denomination->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Denomination updated successfully',
                'data' => $denomination
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Denomination not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update denomination',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete denomination
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Start database transaction
            DB::beginTransaction();

            // Find denomination
            $denomination = Denomination::findOrFail($id);

            // Store info for response
            $denominationInfo = [
                'id' => $denomination->id,
                'title' => $denomination->title,
                'value' => $denomination->value,
            ];

            // Delete the denomination
            $denomination->delete();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Denomination deleted successfully',
                'data' => $denominationInfo
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Denomination not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete denomination',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
