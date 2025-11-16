<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Models\MemberCommunityPoint;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class MemberCommunityPointController extends Controller
{
    /**
     * Get member community points for members who purchased from this merchant
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $merchant = JWTAuth::user();

            $validator = Validator::make($request->all(), [
                'per_page' => 'nullable|integer|min:1|max:100',
                'level' => 'nullable|integer|min:1|max:30',
                'is_locked' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $perPage = $request->input('per_page', 20);

            // Get member IDs who made purchases from this merchant
            $memberIds = Purchase::where('merchant_id', $merchant->id)
                ->distinct()
                ->pluck('member_id');

            $query = MemberCommunityPoint::with('member')
                ->whereIn('member_id', $memberIds);

            // Apply filters
            if ($request->has('level')) {
                $query->where('level', $request->level);
            }

            if ($request->has('is_locked')) {
                $query->where('is_locked', $request->is_locked);
            }

            $communityPoints = $query->orderBy('created_at', 'desc')
                                    ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Member community points retrieved successfully',
                'data' => $communityPoints
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
     * Get single community point by ID (for members who purchased from this merchant)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $merchant = JWTAuth::user();

            $communityPoint = MemberCommunityPoint::with('member')->findOrFail($id);

            // Verify the member has made purchases from this merchant
            $hasPurchased = Purchase::where('merchant_id', $merchant->id)
                ->where('member_id', $communityPoint->member_id)
                ->exists();

            if (!$hasPurchased) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

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
