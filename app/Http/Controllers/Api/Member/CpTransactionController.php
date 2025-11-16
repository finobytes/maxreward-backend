<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\CpTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class CpTransactionController extends Controller
{
    /**
     * Get authenticated member's CP transactions
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $member = JWTAuth::user();

            $validator = Validator::make($request->all(), [
                'per_page' => 'nullable|integer|min:1|max:100',
                'status' => 'nullable|string|in:available,onhold,released',
                'transaction_type' => 'nullable|string|in:earned,unlocked',
                'level' => 'nullable|integer|min:1|max:30',
                'type' => 'nullable|string|in:received,sent,all',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $perPage = $request->input('per_page', 20);
            $type = $request->input('type', 'received');

            $query = CpTransaction::with(['sourceMember', 'receiverMember', 'purchase']);

            // Filter by type
            if ($type === 'received') {
                $query->where('receiver_member_id', $member->id);
            } elseif ($type === 'sent') {
                $query->where('source_member_id', $member->id);
            } else {
                $query->where(function ($q) use ($member) {
                    $q->where('receiver_member_id', $member->id)
                      ->orWhere('source_member_id', $member->id);
                });
            }

            // Apply additional filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('transaction_type')) {
                $query->where('transaction_type', $request->transaction_type);
            }

            if ($request->has('level')) {
                $query->where('level', $request->level);
            }

            $cpTransactions = $query->orderBy('created_at', 'desc')
                                   ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'CP transactions retrieved successfully',
                'data' => $cpTransactions,
                'statistics' => CpTransaction::getCpStatistics($member->id)
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve CP transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single CP transaction by ID (member's own transaction only)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $member = JWTAuth::user();

            $cpTransaction = CpTransaction::with(['sourceMember', 'receiverMember', 'purchase'])
                ->where(function ($query) use ($member) {
                    $query->where('receiver_member_id', $member->id)
                          ->orWhere('source_member_id', $member->id);
                })
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'CP transaction retrieved successfully',
                'data' => $cpTransaction
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'CP transaction not found or unauthorized',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}
