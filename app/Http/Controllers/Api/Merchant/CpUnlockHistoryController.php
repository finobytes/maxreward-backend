<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Models\CpUnlockHistory;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class CpUnlockHistoryController extends Controller
{
    /**
     * Get unlock history for members who purchased from this merchant
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
                'new_unlocked_level' => 'nullable|integer|min:1|max:30',
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

            $query = CpUnlockHistory::with('member')
                ->whereIn('member_id', $memberIds);

            // Apply filters
            if ($request->has('new_unlocked_level')) {
                $query->where('new_unlocked_level', $request->new_unlocked_level);
            }

            $unlockHistory = $query->orderBy('created_at', 'desc')
                                  ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Unlock history retrieved successfully',
                'data' => $unlockHistory
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve unlock history',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get single unlock history by ID (for members who purchased from this merchant)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $merchant = JWTAuth::user();

            $unlockHistory = CpUnlockHistory::with('member')->findOrFail($id);

            // Verify the member has made purchases from this merchant
            $hasPurchased = Purchase::where('merchant_id', $merchant->id)
                ->where('member_id', $unlockHistory->member_id)
                ->exists();

            if (!$hasPurchased) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Unlock history retrieved successfully',
                'data' => $unlockHistory
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unlock history not found or unauthorized',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}
