<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Models\CpTransaction;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class CpTransactionController extends Controller
{
    /**
     * Get CP transactions related to merchant's purchases
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
                'status' => 'nullable|string|in:available,onhold,released',
                'transaction_type' => 'nullable|string|in:earned,unlocked',
                'level' => 'nullable|integer|min:1|max:30',
                'purchase_id' => 'nullable|integer|exists:purchases,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $perPage = $request->input('per_page', 20);

            // Get purchase IDs for this merchant
            $purchaseIds = Purchase::where('merchant_id', $merchant->id)
                ->pluck('id');

            $query = CpTransaction::with(['sourceMember', 'receiverMember', 'purchase'])
                ->whereIn('purchase_id', $purchaseIds);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('transaction_type')) {
                $query->where('transaction_type', $request->transaction_type);
            }

            if ($request->has('level')) {
                $query->where('level', $request->level);
            }

            if ($request->has('purchase_id')) {
                $query->where('purchase_id', $request->purchase_id);
            }

            $cpTransactions = $query->orderBy('created_at', 'desc')
                                   ->paginate($perPage);

            // Calculate statistics for merchant's purchases
            $totalCpDistributed = CpTransaction::whereIn('purchase_id', $purchaseIds)->sum('cp_amount');
            $totalAvailable = CpTransaction::whereIn('purchase_id', $purchaseIds)->where('status', 'available')->sum('cp_amount');
            $totalOnhold = CpTransaction::whereIn('purchase_id', $purchaseIds)->where('status', 'onhold')->sum('cp_amount');
            $totalReleased = CpTransaction::whereIn('purchase_id', $purchaseIds)->where('status', 'released')->sum('cp_amount');

            return response()->json([
                'success' => true,
                'message' => 'CP transactions retrieved successfully',
                'data' => $cpTransactions,
                'statistics' => [
                    'total_cp_distributed' => $totalCpDistributed,
                    'total_available' => $totalAvailable,
                    'total_onhold' => $totalOnhold,
                    'total_released' => $totalReleased,
                    'total_transactions' => CpTransaction::whereIn('purchase_id', $purchaseIds)->count(),
                ]
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
     * Get single CP transaction by ID (related to merchant's purchases only)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $merchant = JWTAuth::user();

            // Get purchase IDs for this merchant
            $purchaseIds = Purchase::where('merchant_id', $merchant->id)
                ->pluck('id');

            $cpTransaction = CpTransaction::with(['sourceMember', 'receiverMember', 'purchase'])
                ->whereIn('purchase_id', $purchaseIds)
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
