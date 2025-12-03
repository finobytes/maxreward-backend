<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CpTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\CpDistributionPool;

class CpTransactionController extends Controller
{
    /**
     * Get all CP transactions with filters and pagination (Admin only)
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
                'status' => 'nullable|string|in:available,onhold,released',
                'transaction_type' => 'nullable|string|in:earned,unlocked',
                'source_member_id' => 'nullable|integer|exists:members,id',
                'receiver_member_id' => 'nullable|integer|exists:members,id',
                'purchase_id' => 'nullable|integer|exists:purchases,id',
                'level' => 'nullable|integer|min:1|max:30',
                'is_locked' => 'nullable|boolean',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'sort_by' => 'nullable|string|in:created_at,cp_amount,level',
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
            $query = CpTransaction::with(['sourceMember', 'receiverMember', 'purchase']);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('transaction_type')) {
                $query->where('transaction_type', $request->transaction_type);
            }

            if ($request->has('source_member_id')) {
                $query->where('source_member_id', $request->source_member_id);
            }

            if ($request->has('receiver_member_id')) {
                $query->where('receiver_member_id', $request->receiver_member_id);
            }

            if ($request->has('purchase_id')) {
                $query->where('purchase_id', $request->purchase_id);
            }

            if ($request->has('level')) {
                $query->where('level', $request->level);
            }

            if ($request->has('is_locked')) {
                $query->where('is_locked', $request->is_locked);
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
            $cpTransactions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'CP transactions retrieved successfully',
                'data' => $cpTransactions,
                'statistics' => [
                    'total_available' => CpTransaction::where('status', 'available')->sum('cp_amount'),
                    'total_onhold' => CpTransaction::where('status', 'onhold')->sum('cp_amount'),
                    'total_released' => CpTransaction::where('status', 'released')->sum('cp_amount'),
                    'total_transactions' => CpTransaction::count(),
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
     * Get single CP transaction by ID (Admin only)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $cpTransaction = CpTransaction::with(['sourceMember', 'receiverMember', 'purchase'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'CP transaction retrieved successfully',
                'data' => $cpTransaction
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'CP transaction not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function getCpDistributionPool()
    {
        // dd("ok");
        try {
            $cpDistributionPool = CpDistributionPool::with(['member'])->orderBy('id', 'desc')->paginate(20);
            return response()->json([
                'success' => true,
                'message' => 'CP distribution pool retrieved successfully',
                'data' => $cpDistributionPool
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve CP distribution pool',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getSingleCpDistributionPool($id)
    {
        try{
            $getSingleCpDistributionPool = CpDistributionPool::with(['member'])->findOrFail($id);
            $getSingleCpDistributionPoolData = CpTransaction::with('receiverMember')->where('cp_distribution_pools_id', $id)->get();
            return response()->json([
                'success' => true,
                'message' => 'Single CP distribution pool retrieved successfully',
                'data' => [
                    'getSingleCpDistributionPool' => $getSingleCpDistributionPool,
                    'getSingleCpDistributionPoolData' => $getSingleCpDistributionPoolData
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve CP distribution pool',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
