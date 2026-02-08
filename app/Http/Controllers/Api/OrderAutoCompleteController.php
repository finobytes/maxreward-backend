<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderAutoCompleteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OrderAutoCompleteController extends Controller
{
    protected $autoCompleteService;

    public function __construct(OrderAutoCompleteService $autoCompleteService)
    {
        $this->autoCompleteService = $autoCompleteService;
    }

    /**
     * Merchant manually triggers auto-completion for their eligible orders
     * POST /api/merchant/orders/auto-complete
     */
    public function merchantAutoComplete()
    {
        try {
            $merchantInfo = auth('merchant')->user();

            if (!$merchantInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Merchant not found.'
                ], 401);
            }

            Log::info("Merchant {$merchantInfo->merchant_id} triggered manual auto-completion");

            // Process auto-completion for this merchant only
            $results = $this->autoCompleteService->processForMerchant($merchantInfo->merchant_id);

            if ($results['total'] === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No orders are eligible for auto-completion at this time.',
                    'data' => $results
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => "Auto-completion completed. {$results['success']} orders completed successfully.",
                'data' => $results
            ], 200);

        } catch (\Exception $e) {
            Log::error('Merchant auto-completion failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete orders automatically',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get list of orders eligible for auto-completion (Merchant)
     * GET /api/merchant/orders/auto-complete/eligible
     */
    public function getEligibleOrders()
    {
        try {
            $merchantInfo = auth('merchant')->user();

            if (!$merchantInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Merchant not found.'
                ], 401);
            }

            $eligibleOrders = $this->autoCompleteService->getEligibleOrders($merchantInfo->merchant_id);

            return response()->json([
                'success' => true,
                'message' => 'Eligible orders retrieved successfully',
                'data' => [
                    'total_eligible' => $eligibleOrders->count(),
                    'orders' => $eligibleOrders
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to fetch eligible orders: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve eligible orders',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Admin triggers auto-completion for all merchants
     * POST /api/admin/orders/auto-complete/all
     */
    public function adminAutoCompleteAll()
    {
        try {
            // Optional: Add admin guard check
            // $admin = Auth::guard('admin')->user();
            
            Log::info('Admin triggered global auto-completion');

            // Process all eligible orders across all merchants
            $results = $this->autoCompleteService->processAutoCompletion();

            if ($results['total'] === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No orders are eligible for auto-completion at this time.',
                    'data' => $results
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => "Auto-completion completed. {$results['success']} orders completed successfully out of {$results['total']}.",
                'data' => $results
            ], 200);

        } catch (\Exception $e) {
            Log::error('Admin auto-completion failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete orders automatically',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all eligible orders across all merchants (Admin)
     * GET /api/admin/orders/auto-complete/eligible
     */
    public function adminGetAllEligibleOrders()
    {
        try {
            $eligibleOrders = $this->autoCompleteService->getEligibleOrders();

            return response()->json([
                'success' => true,
                'message' => 'All eligible orders retrieved successfully',
                'data' => [
                    'total_eligible' => $eligibleOrders->count(),
                    'orders' => $eligibleOrders
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to fetch all eligible orders: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve eligible orders',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}