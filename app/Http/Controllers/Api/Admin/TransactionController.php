<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    /**
     * Get all transactions with filters and pagination
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
                'transaction_type' => 'nullable|string|in:pp,rp,cp,cr,dp,ap,vrp,vap',
                'points_type' => 'nullable|string|in:debited,credited',
                'member_id' => 'nullable|integer|exists:members,id',
                'merchant_id' => 'nullable|integer|exists:merchants,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'sort_by' => 'nullable|string|in:created_at,transaction_points',
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
            $query = Transaction::with(['member', 'merchant', 'referralMember']);

            // Apply filters
            if ($request->has('transaction_type')) {
                $query->where('transaction_type', $request->transaction_type);
            }

            if ($request->has('points_type')) {
                $query->where('points_type', $request->points_type);
            }

            if ($request->has('member_id')) {
                $query->where('member_id', $request->member_id);
            }

            if ($request->has('merchant_id')) {
                $query->where('merchant_id', $request->merchant_id);
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
            $transactions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Transactions retrieved successfully',
                'data' => $transactions,
                'statistics' => Transaction::getTransactionStatistics()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all transactions without pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllTransactions(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'transaction_type' => 'nullable|string|in:pp,rp,cp,cr,dp,ap,vrp,vap',
                'points_type' => 'nullable|string|in:debited,credited',
                'member_id' => 'nullable|integer|exists:members,id',
                'merchant_id' => 'nullable|integer|exists:merchants,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'limit' => 'nullable|integer|min:1|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $limit = $request->input('limit', 100);

            // Build query with filters
            $query = Transaction::with(['member', 'merchant', 'referralMember']);

            // Apply filters
            if ($request->has('transaction_type')) {
                $query->where('transaction_type', $request->transaction_type);
            }

            if ($request->has('points_type')) {
                $query->where('points_type', $request->points_type);
            }

            if ($request->has('member_id')) {
                $query->where('member_id', $request->member_id);
            }

            if ($request->has('merchant_id')) {
                $query->where('merchant_id', $request->merchant_id);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [
                    $request->start_date . ' 00:00:00',
                    $request->end_date . ' 23:59:59'
                ]);
            }

            // Get limited results
            $transactions = $query->orderBy('created_at', 'desc')
                                  ->limit($limit)
                                  ->get();

            return response()->json([
                'success' => true,
                'message' => 'Transactions retrieved successfully',
                'data' => $transactions,
                'total' => $transactions->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

  

    /**
     * Get single transaction by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $transaction = Transaction::with(['member', 'merchant', 'referralMember'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Transaction retrieved successfully',
                'data' => $transaction
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}
