<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * Get all notifications with filters and pagination
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
                'merchant_id' => 'nullable|integer|exists:merchants,id',
                'type' => 'nullable|string',
                'status' => 'nullable|string|in:read,unread',
                'is_read' => 'nullable|boolean',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'sort_by' => 'nullable|string|in:created_at,read_at',
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
            $query = Notification::with(['member', 'merchant']);

            // Apply filters
            if ($request->has('member_id')) {
                $query->where('member_id', $request->member_id);
            }

            if ($request->has('merchant_id')) {
                $query->where('merchant_id', $request->merchant_id);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('is_read')) {
                $query->where('is_read', $request->is_read);
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
            $notifications = $query->paginate($perPage);

            // Get statistics
            $statistics = [
                'total_notifications' => Notification::count(),
                'total_read' => Notification::where('status', 'read')->count(),
                'total_unread' => Notification::where('status', 'unread')->count(),
                'by_type' => Notification::selectRaw('type, COUNT(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type')
                    ->toArray(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Notifications retrieved successfully',
                'data' => $notifications,
                'statistics' => $statistics
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all notifications without pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllNotifications(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'member_id' => 'nullable|integer|exists:members,id',
                'merchant_id' => 'nullable|integer|exists:merchants,id',
                'type' => 'nullable|string',
                'status' => 'nullable|string|in:read,unread',
                'is_read' => 'nullable|boolean',
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
            $query = Notification::with(['member', 'merchant']);

            // Apply filters
            if ($request->has('member_id')) {
                $query->where('member_id', $request->member_id);
            }

            if ($request->has('merchant_id')) {
                $query->where('merchant_id', $request->merchant_id);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('is_read')) {
                $query->where('is_read', $request->is_read);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [
                    $request->start_date . ' 00:00:00',
                    $request->end_date . ' 23:59:59'
                ]);
            }

            // Get limited results
            $notifications = $query->orderBy('created_at', 'desc')
                                   ->limit($limit)
                                   ->get();

            return response()->json([
                'success' => true,
                'message' => 'Notifications retrieved successfully',
                'data' => $notifications,
                'total' => $notifications->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single notification by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $notification = Notification::with(['member', 'merchant'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Notification retrieved successfully',
                'data' => $notification
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get notifications for authenticated member
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMemberNotifications(Request $request)
    {
        try {
            $member = $request->user();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member not authenticated'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1',
                'type' => 'nullable|string',
                'status' => 'nullable|string|in:read,unread',
                'is_read' => 'nullable|boolean',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'sort_by' => 'nullable|string|in:created_at,read_at',
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

            // Build query for authenticated member's notifications
            $query = Notification::where('member_id', $member->id)
                ->with(['merchant']);

            // Apply filters
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('is_read')) {
                $query->where('is_read', $request->is_read);
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
            $notifications = $query->paginate($perPage);

            // Get statistics for this member
            $statistics = [
                'total_notifications' => Notification::where('member_id', $member->id)->count(),
                'total_read' => Notification::where('member_id', $member->id)->where('status', 'read')->count(),
                'total_unread' => Notification::where('member_id', $member->id)->where('status', 'unread')->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Notifications retrieved successfully',
                'data' => $notifications,
                'statistics' => $statistics
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notifications for authenticated merchant
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMerchantNotifications(Request $request)
    {
        try {
            $merchant = $request->user();

            $merchantData = Merchant::with(['corporateMember'])->find($merchant->id);


            

            if (!$merchant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Merchant not authenticated'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1',
                'type' => 'nullable|string',
                'status' => 'nullable|string|in:read,unread',
                'is_read' => 'nullable|boolean',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'sort_by' => 'nullable|string|in:created_at,read_at',
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

            // Build query for authenticated merchant's notifications
            $query = Notification::where('member_id', $merchantData->corporateMember->id)
                ->with(['member']);

            // Apply filters
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('is_read')) {
                $query->where('is_read', $request->is_read);
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
            $notifications = $query->paginate($perPage);

            

            // Get statistics for this merchant
            $statistics = [
                'total_notifications' => Notification::where('member_id', $merchantData->corporateMember->id)->count(),
                'total_read' => Notification::where('member_id', $merchantData->corporateMember->id)->where('status', 'read')->count(),
                'total_unread' => Notification::where('member_id', $merchantData->corporateMember->id)->where('status', 'unread')->count(),
            ];

            // dd($notifications);

            return response()->json([
                'success' => true,
                'message' => 'Notifications retrieved successfully',
                'data' => $notifications,
                'statistics' => $statistics
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete notification
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $notification = Notification::findOrFail($id);
            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
