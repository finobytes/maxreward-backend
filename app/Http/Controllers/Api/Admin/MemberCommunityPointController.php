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
        // dd("ok");
        try {
            $validator = Validator::make($request->all(), [
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1',
                'member_id' => 'nullable|integer|exists:members,id',
                'level' => 'nullable|integer|min:1|max:30',
                'is_locked' => 'nullable|boolean',
                'has_cp' => 'nullable|boolean',
                'sort_by' => 'nullable|string|in:member_id,total_cp,available_cp,onhold_cp,created_at',
                'sort_order' => 'nullable|string|in:asc,desc',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $perPage = $request->input('per_page', 15);
            $sortBy = $request->input('sort_by', 'total_cp');
            $sortOrder = $request->input('sort_order', 'desc');

            // Build query with group by member_id
            $query = MemberCommunityPoint::query()
                ->selectRaw('member_id')
                ->selectRaw('SUM(total_cp) as total_cp')
                ->selectRaw('SUM(available_cp) as available_cp')
                ->selectRaw('SUM(onhold_cp) as onhold_cp')
                ->selectRaw('MAX(created_at) as created_at')
                ->with('member')
                ->groupBy('member_id');

            // Apply filters
            if ($request->has('member_id')) {
                $query->where('member_id', $request->member_id);
            }

            if ($request->has('level')) {
                $query->havingRaw('COUNT(CASE WHEN level = ? THEN 1 END) > 0', [$request->level]);
            }

            if ($request->has('is_locked')) {
                $query->havingRaw('COUNT(CASE WHEN is_locked = ? THEN 1 END) > 0', [$request->is_locked]);
            }

            if ($request->has('has_cp') && $request->has_cp) {
                $query->havingRaw('SUM(total_cp) > 0');
            }

            // Apply sorting (only allow aggregated or grouped columns)
            $allowedSortColumns = ['member_id', 'total_cp', 'available_cp', 'onhold_cp', 'created_at'];
            if (in_array($sortBy, $allowedSortColumns)) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy('total_cp', $sortOrder);
            }

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
     * Get all community points for a specific member (Admin only)
     *
     * @param int $memberId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMemberPoints($memberId)
    {
        try {
            $memberCommunityPoints = MemberCommunityPoint::with('member')
                ->where('member_id', $memberId)
                ->orderBy('level', 'asc')
                ->get();

            if ($memberCommunityPoints->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No community points found for this member'
                ], 404);
            }

            // Calculate totals
            $totalCp = $memberCommunityPoints->sum('total_cp');
            $totalAvailableCp = $memberCommunityPoints->sum('available_cp');
            $totalOnholdCp = $memberCommunityPoints->sum('onhold_cp');

            return response()->json([
                'success' => true,
                'message' => 'Member community points retrieved successfully',
                'data' => $memberCommunityPoints,
                'summary' => [
                    'total_levels' => $memberCommunityPoints->count(),
                    'total_cp' => $totalCp,
                    'total_available_cp' => $totalAvailableCp,
                    'total_onhold_cp' => $totalOnholdCp,
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
