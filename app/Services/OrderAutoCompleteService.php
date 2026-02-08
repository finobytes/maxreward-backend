<?php

namespace App\Services;

use App\Models\OrderOnholdPoint;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Log;

class OrderAutoCompleteService
{
    protected $orderController;

    public function __construct(OrderController $orderController)
    {
        $this->orderController = $orderController;
    }

    /**
     * Process auto-completion for all eligible orders
     * Can be called from API or scheduled job
     */
    public function processAutoCompletion()
    {
        Log::info('Starting auto-completion of orders...');

        try {
            // Find all orders ready for release
            $readyOrders = OrderOnholdPoint::readyForRelease()
                ->with('order')
                ->get();

            $results = [
                'total' => $readyOrders->count(),
                'success' => 0,
                'failed' => 0,
                'orders' => [],
                'errors' => []
            ];

            Log::info("Found {$readyOrders->count()} orders ready for auto-completion");

            foreach ($readyOrders as $onholdPoint) {
                $orderNumber = $onholdPoint->order->order_number ?? 'Unknown';
                
                Log::info("Processing order ID: {$onholdPoint->order_id} ({$orderNumber})");
                
                $result = $this->orderController->releaseOrderPoints($onholdPoint->order_id);
                
                if ($result['success']) {
                    $results['success']++;
                    $results['orders'][] = [
                        'order_id' => $onholdPoint->order_id,
                        'order_number' => $orderNumber,
                        'status' => 'completed',
                        'points_distributed' => $result['data']['total_points'] ?? 0
                    ];
                    
                    Log::info("✓ Order {$orderNumber} completed successfully");
                } else {
                    $results['failed']++;
                    $results['errors'][] = [
                        'order_id' => $onholdPoint->order_id,
                        'order_number' => $orderNumber,
                        'error' => $result['message'] ?? 'Unknown error'
                    ];
                    
                    Log::error("✗ Order {$orderNumber} failed: {$result['message']}");
                }
            }

            Log::info("Auto-completion summary: Total={$results['total']}, Success={$results['success']}, Failed={$results['failed']}");

            return $results;

        } catch (\Exception $e) {
            Log::error('Auto-completion process failed: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Process auto-completion for a specific merchant's orders
     * Useful for merchant-specific manual completion
     */
    public function processForMerchant($merchantId)
    {
        Log::info("Starting auto-completion for merchant: {$merchantId}");

        try {
            // Find ready orders for specific merchant
            $readyOrders = OrderOnholdPoint::readyForRelease()
                ->where('merchant_id', $merchantId)
                ->with('order')
                ->get();

            $results = [
                'merchant_id' => $merchantId,
                'total' => $readyOrders->count(),
                'success' => 0,
                'failed' => 0,
                'orders' => [],
                'errors' => []
            ];

            Log::info("Found {$readyOrders->count()} orders for merchant {$merchantId}");

            foreach ($readyOrders as $onholdPoint) {
                $orderNumber = $onholdPoint->order->order_number ?? 'Unknown';
                
                $result = $this->orderController->releaseOrderPoints($onholdPoint->order_id);
                
                if ($result['success']) {
                    $results['success']++;
                    $results['orders'][] = [
                        'order_id' => $onholdPoint->order_id,
                        'order_number' => $orderNumber,
                        'status' => 'completed',
                        'points_distributed' => $result['data']['total_points'] ?? 0
                    ];
                } else {
                    $results['failed']++;
                    $results['errors'][] = [
                        'order_id' => $onholdPoint->order_id,
                        'order_number' => $orderNumber,
                        'error' => $result['message'] ?? 'Unknown error'
                    ];
                }
            }

            return $results;

        } catch (\Exception $e) {
            Log::error("Auto-completion failed for merchant {$merchantId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get list of orders eligible for auto-completion
     */
    public function getEligibleOrders($merchantId = null)
    {
        $query = OrderOnholdPoint::readyForRelease()
            ->with(['order', 'merchant', 'member']);

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        $orders = $query->get();

        return $orders->map(function ($onholdPoint) {
            return [
                'order_id' => $onholdPoint->order_id,
                'order_number' => $onholdPoint->order->order_number ?? 'N/A',
                'merchant_id' => $onholdPoint->merchant_id,
                'merchant_name' => $onholdPoint->merchant->business_name ?? 'N/A',
                'member_name' => $onholdPoint->member->name ?? 'N/A',
                'total_points' => $onholdPoint->total_points,
                'shipped_at' => $onholdPoint->shipped_at,
                'auto_release_at' => $onholdPoint->auto_release_at,
                'days_until_release' => $onholdPoint->auto_release_at 
                    ? now()->diffInDays($onholdPoint->auto_release_at, false) 
                    : null,
            ];
        });
    }
}