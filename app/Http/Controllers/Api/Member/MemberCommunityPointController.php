<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberCommunityPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MemberCommunityPointController extends Controller
{
    /**
     * Get authenticated member's community points
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $member = auth()->user();

            $validator = Validator::make($request->all(), [
                'level' => 'nullable|integer|min:1|max:30',
                'is_locked' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = MemberCommunityPoint::where('member_id', $member->id);

            // Apply filters
            if ($request->has('level')) {
                $query->where('level', $request->level);
            }

            if ($request->has('is_locked')) {
                $query->where('is_locked', $request->is_locked);
            }

            $communityPoints = $query->orderBy('level', 'asc')->get();

            $summary = MemberCommunityPoint::getCpSummary($member->id);

            return response()->json([
                'success' => true,
                'message' => 'Community points retrieved successfully',
                'data' => $communityPoints,
                'summary' => $summary
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve community points',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get single community point by ID (member's own only)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $member = auth()->user();

            $communityPoint = MemberCommunityPoint::where('member_id', $member->id)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Community point retrieved successfully',
                'data' => $communityPoint
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Community point not found or unauthorized',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}
