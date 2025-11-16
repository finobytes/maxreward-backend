<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberCommunityPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MemberCommunityPointController extends Controller
{
    /**
     * Get all member community points with filters and pagination (Admin only)
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
                'level' => 'nullable|integer|min:1|max:30',
                'is_locked' => 'nullable|boolean',
                'has_cp' => 'nullable|boolean',
                'sort_by' => 'nullable|string|in:level,total_cp,available_cp,onhold_cp,created_at',
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
            $query = MemberCommunityPoint::with('member');

            // Apply filters
            if ($request->has('member_id')) {
                $query->where('member_id', $request->member_id);
            }

            if ($request->has('level')) {
                $query->where('level', $request->level);
            }

            if ($request->has('is_locked')) {
                $query->where('is_locked', $request->is_locked);
            }

            if ($request->has('has_cp') && $request->has_cp) {
                $query->where('total_cp', '>', 0);
            }

            // Apply sorting
            $query->orderBy($sortBy, $sortOrder);

            // Get paginated results
            $memberCommunityPoints = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Member community points retrieved successfully',
                'data' => $memberCommunityPoints,
                'statistics' => [
                    'total_system_cp' => MemberCommunityPoint::getTotalSystemCp(),
                    'total_available_cp' => MemberCommunityPoint::getTotalSystemAvailableCp(),
                    'total_onhold_cp' => MemberCommunityPoint::getTotalSystemOnholdCp(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve member community points',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single member community point by ID (Admin only)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $memberCommunityPoint = MemberCommunityPoint::with('member')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Member community point retrieved successfully',
                'data' => $memberCommunityPoint
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Member community point not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

}
