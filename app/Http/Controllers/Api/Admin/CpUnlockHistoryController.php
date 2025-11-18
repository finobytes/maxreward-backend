<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CpUnlockHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CpUnlockHistoryController extends Controller
{
    /**
     * Get all CP unlock history with filters and pagination (Admin only)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1',
                'member_id' => 'nullable|integer|exists:members,id',
                'new_unlocked_level' => 'nullable|integer|min:1|max:30',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'sort_by' => 'nullable|string|in:created_at,released_cp_amount,new_unlocked_level',
                'sort_order' => 'nullable|string|in:asc,desc',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $perPage = $request->input('per_page', 15);
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');

            // Build query with filters
            $query = CpUnlockHistory::with('member');

            // Apply filters
            if ($request->has('member_id')) {
                $query->where('member_id', $request->member_id);
            }

            if ($request->has('new_unlocked_level')) {
                $query->where('new_unlocked_level', $request->new_unlocked_level);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [
                    $request->start_date . ' 00:00:00',
                    $request->end_date . ' 23:59:59'
                ]);
            }

            // Apply sorting
            $query->orderBy($sortBy, $sortOrder);

            // Get paginated results
            $unlockHistory = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'CP unlock history retrieved successfully',
                'data' => $unlockHistory,
                'statistics' => CpUnlockHistory::getUnlockStatistics()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve CP unlock history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single unlock history by ID (Admin only)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $unlockHistory = CpUnlockHistory::with('member')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'CP unlock history retrieved successfully',
                'data' => $unlockHistory
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'CP unlock history not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}
