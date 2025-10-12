<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Merchant;

class MerchantController extends Controller
{
    /**
     * Get all merchants with pagination
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request) 
    {
        try {
            // Query builder
            $query = Merchant::query();

            // Filter by status (optional)
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by business type (optional)
            if ($request->has('business_type')) {
                $query->where('business_type', $request->business_type);
            }

            // Search by business name (optional)
            if ($request->has('search')) {
                $query->where('business_name', 'LIKE', '%' . $request->search . '%');
            }

            // Get pagination limit (default: 10)
            $perPage = $request->get('per_page', 10);

            // Fetch merchants with relationships
            $merchants = $query->with([
                'wallet',              // Include merchant wallet
                'corporateMember',     // Include linked corporate member
                'staffs' => function($q) {
                    $q->where('status', 'active'); // Only active staffs
                }
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Merchants retrieved successfully',
                'data' => $merchants
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve merchants',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single merchant by ID
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $merchant = Merchant::with([
                'wallet',
                'corporateMember.wallet',
                'staffs'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Merchant retrieved successfully',
                'data' => $merchant
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Merchant not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve merchant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get merchant by unique number
     * 
     * @param string $uniqueNumber
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByUniqueNumber($uniqueNumber)
    {
        try {
            $merchant = Merchant::with([
                'wallet',
                'corporateMember',
                'staffs'
            ])->where('unique_number', $uniqueNumber)->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Merchant retrieved successfully',
                'data' => $merchant
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Merchant not found with this unique number'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve merchant',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}