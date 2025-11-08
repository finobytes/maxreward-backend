<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppMessageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WhatsAppLogController extends Controller
{
    /**
     * Get all WhatsApp message logs with filters and pagination
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
                'sent_by_member_id' => 'nullable|integer|exists:members,id',
                'phone_number' => 'nullable|string',
                'message_type' => 'nullable|string',
                'status' => 'nullable|string|in:pending,sent,failed',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'sort_by' => 'nullable|string|in:created_at,sent_at',
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
            $query = WhatsAppMessageLog::with(['member', 'sentByMember']);

            // Apply filters
            if ($request->has('member_id')) {
                $query->where('member_id', $request->member_id);
            }

            if ($request->has('sent_by_member_id')) {
                $query->where('sent_by_member_id', $request->sent_by_member_id);
            }

            if ($request->has('phone_number')) {
                $query->where('phone_number', 'LIKE', '%' . $request->phone_number . '%');
            }

            if ($request->has('message_type')) {
                $query->where('message_type', $request->message_type);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [
                    $request->start_date . '00:00:00',
                    $request->end_date . ' 23:59:59'
                ]);
            }

            // Apply sorting
            $query->orderBy($sortBy, $sortOrder);

            // Get paginated results
            $logs = $query->paginate($perPage);

            // Get statistics
            $statistics = WhatsAppMessageLog::getStatistics();

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp message logs retrieved successfully',
                'data' => $logs,
                'statistics' => $statistics
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve WhatsApp message logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all WhatsApp message logs without pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllLogs(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'member_id' => 'nullable|integer|exists:members,id',
                'sent_by_member_id' => 'nullable|integer|exists:members,id',
                'phone_number' => 'nullable|string',
                'message_type' => 'nullable|string',
                'status' => 'nullable|string|in:pending,sent,failed',
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
            $query = WhatsAppMessageLog::with(['member', 'sentByMember']);

            // Apply filters
            if ($request->has('member_id')) {
                $query->where('member_id', $request->member_id);
            }

            if ($request->has('sent_by_member_id')) {
                $query->where('sent_by_member_id', $request->sent_by_member_id);
            }

            if ($request->has('phone_number')) {
                $query->where('phone_number', 'LIKE', '%' . $request->phone_number . '%');
            }

            if ($request->has('message_type')) {
                $query->where('message_type', $request->message_type);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [
                    $request->start_date . ' 00:00:00',
                    $request->end_date . ' 23:59:59'
                ]);
            }

            // Get limited results
            $logs = $query->orderBy('created_at', 'desc')
                          ->limit($limit)
                          ->get();

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp message logs retrieved successfully',
                'data' => $logs,
                'total' => $logs->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve WhatsApp message logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single WhatsApp message log by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $log = WhatsAppMessageLog::with(['member', 'sentByMember'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp message log retrieved successfully',
                'data' => $log
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp message log not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get WhatsApp message statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatistics()
    {
        try {
            $statistics = WhatsAppMessageLog::getStatistics();

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp message statistics retrieved successfully',
                'data' => $statistics
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retry failed message
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function retry($id)
    {
        try {
            $log = WhatsAppMessageLog::findOrFail($id);

            if ($log->status !== 'failed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only failed messages can be retried'
                ], 400);
            }

            // Reset status to pending for retry
            $log->status = 'pending';
            $log->error_message = null;
            $log->save();

            return response()->json([
                'success' => true,
                'message' => 'Message queued for retry',
                'data' => $log
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete WhatsApp message log
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $log = WhatsAppMessageLog::findOrFail($id);
            $log->delete();

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp message log deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete WhatsApp message log',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
