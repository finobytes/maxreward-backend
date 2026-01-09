<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Action;

class ActionController extends Controller
{
    /**
     * Get all actions with pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Query builder
            $query = Action::query();

            // Search by name (optional)
            if ($request->has('search') && !empty($request->search)) {
                $query->where('name', 'LIKE', '%' . $request->search . '%');
            }

            // Filter by status (optional)
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Get pagination limit (default: 10)
            $perPage = $request->get('per_page', 10);

            // Fetch actions with pagination
            $actions = $query->orderBy('created_at', 'desc')
                             ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Actions retrieved successfully',
                'data' => $actions
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve actions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all actions without pagination
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllActions()
    {
        try {
            $actions = Action::orderBy('name', 'asc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Actions retrieved successfully',
                'data' => [
                    'actions' => $actions,
                    'total' => $actions->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve actions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single action by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $action = Action::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Action retrieved successfully',
                'data' => $action
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Action not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve action',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new action
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:actions,name',
            'status' => 'nullable|boolean',
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

            // Create action
            $action = Action::create([
                'name' => $request->name,
                'status' => $request->status ?? true,
            ]);

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Action created successfully',
                'data' => $action
            ], 201);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create action',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update action information
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100|unique:actions,name,' . $id,
            'status' => 'nullable|boolean',
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

            // Find action
            $action = Action::findOrFail($id);

            // Update action data (only fields that are provided)
            if ($request->has('name')) {
                $action->name = $request->name;
            }
            if ($request->has('status')) {
                $action->status = $request->status;
            }

            // Save changes
            $action->save();

            // Commit transaction
            DB::commit();

            // Refresh action data
            $action->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Action updated successfully',
                'data' => $action
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Action not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update action',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete action
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Start database transaction
            DB::beginTransaction();

            // Find action
            $action = Action::findOrFail($id);

            // Store info for response
            $actionInfo = [
                'id' => $action->id,
                'name' => $action->name,
            ];

            // Delete the action
            $action->delete();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Action deleted successfully',
                'data' => $actionInfo
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Action not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete action',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
