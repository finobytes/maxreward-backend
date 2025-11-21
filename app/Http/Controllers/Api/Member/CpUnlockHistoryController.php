<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\CpUnlockHistory;
use Illuminate\Http\Request;

class CpUnlockHistoryController extends Controller
{
    /**
     * Get authenticated member's unlock history
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
             $member = auth()->user();

            $unlockHistory = CpUnlockHistory::getMemberHistory($member->id);
            $stats = CpUnlockHistory::getMemberUnlockStats($member->id);

            return response()->json([
                'success' => true,
                'message' => 'Unlock history retrieved successfully',
                'data' => $unlockHistory,
                'statistics' => $stats
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
     * Get single unlock history by ID (member's own only)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $member = auth()->user();

            $unlockHistory = CpUnlockHistory::where('member_id', $member->id)
                ->findOrFail($id);

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
