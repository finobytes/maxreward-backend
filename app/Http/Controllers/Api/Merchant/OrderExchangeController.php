<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderExchange;
use App\Models\ProductVariation;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class OrderExchangeController extends Controller
{
    /**
     * Get all exchange requests for merchant
     * Merchant Route: GET /merchant/exchanges
     */
    public function getMerchantExchanges(Request $request)
    {
        try {
            $merchant = auth('merchant')->user();
            
            $query = OrderExchange::with([
                'order',
                'orderItem',
                'member',
                'originalVariation',
                'exchangeVariation'
            ])->byMerchant($merchant->merchant_id);

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $exchanges = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $exchanges
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch exchanges',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get single exchange details
     * Merchant Route: GET /merchant/exchanges/{id}
     */
    public function getExchangeDetails($id)
    {
        try {
            $merchant = auth('merchant')->user();
            
            $exchange = OrderExchange::with([
                'order',
                'orderItem',
                'member',
                'originalVariation.product',
                'originalVariation.variationAttributes.attribute',
                'originalVariation.variationAttributes.attributeItem',
                'exchangeVariation.product',
                'exchangeVariation.variationAttributes.attribute',
                'exchangeVariation.variationAttributes.attributeItem'
            ])
            ->byMerchant($merchant->merchant_id)
            ->find($id);

            if (!$exchange) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exchange request not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $exchange
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch exchange details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create exchange request (Merchant creates on behalf of customer)
     * Merchant Route: POST /merchant/exchanges
     */
    public function createExchange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'order_item_id' => 'required|exists:order_items,id',
            'exchange_product_variation_id' => 'required|exists:product_variations,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $merchant = auth('merchant')->user();

            // Verify order belongs to merchant
            $order = Order::with(['items'])
                ->byMerchant($merchant->merchant_id)
                ->find($request->order_id);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found or does not belong to this merchant'
                ], 404);
            }

            // Verify order item
            $orderItem = OrderItem::where('id', $request->order_item_id)
                ->where('order_id', $order->id)
                ->first();

            if (!$orderItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order item not found'
                ], 404);
            }

            // Check if quantity is valid
            if ($request->quantity > $orderItem->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exchange quantity cannot exceed ordered quantity'
                ], 400);
            }

            // Get original and exchange variations
            $originalVariation = ProductVariation::with(['variationAttributes.attribute', 'variationAttributes.attributeItem'])->find($orderItem->product_variation_id);
            $exchangeVariation = ProductVariation::with(['variationAttributes.attribute', 'variationAttributes.attributeItem'])->find($request->exchange_product_variation_id);

            if (!$originalVariation || !$exchangeVariation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product variation not found'
                ], 404);
            }

            // Verify both variations are from the same product
            if ($originalVariation->product_id !== $exchangeVariation->product_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only exchange for same product with different variation'
                ], 400);
            }

            // Check if exchange variation has enough stock
            if ($exchangeVariation->actual_quantity < $request->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock for exchange variation',
                    'available_quantity' => $exchangeVariation->actual_quantity
                ], 400);
            }

            $originalVariantName = $originalVariation->getVariationNameAttribute();
            $exchangeVariantName = $exchangeVariation->getVariationNameAttribute();

            // Create exchange request
            $exchange = OrderExchange::create([
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'merchant_id' => $merchant->merchant_id,
                'member_id' => $order->member_id,
                'original_product_variation_id' => $originalVariation->id,
                'original_variant_name' => $originalVariantName,
                'exchange_product_variation_id' => $exchangeVariation->id,
                'exchange_variant_name' => $exchangeVariantName,
                'quantity' => $request->quantity,
                'reason' => $request->reason,
                'status' => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Exchange request created successfully',
                'data' => $exchange
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create exchange request',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Approve exchange request and adjust stock
     * Merchant Route: POST /merchant/exchanges/{id}/approve
     */
    public function approveExchange($id)
    {
        DB::beginTransaction();

        try {
            $merchant = auth('merchant')->user();

            $exchange = OrderExchange::with([
                'order',
                'orderItem',
                'member',
                'originalVariation.variationAttributes.attribute',
                'originalVariation.variationAttributes.attributeItem',
                'exchangeVariation.variationAttributes.attribute',
                'exchangeVariation.variationAttributes.attributeItem'
            ])
            ->byMerchant($merchant->merchant_id)
            ->find($id);

            if (!$exchange) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exchange request not found'
                ], 404);
            }

            if ($exchange->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending exchanges can be approved'
                ], 400);
            }

            // Check stock availability again
            if ($exchange->exchangeVariation->actual_quantity < $exchange->quantity) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock for exchange',
                    'available_quantity' => $exchange->exchangeVariation->actual_quantity
                ], 400);
            }

            // Approve exchange (this will adjust stock automatically)
            $result = $exchange->approve($merchant->merchant_id);

            if (!$result) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to approve exchange'
                ], 400);
            }

            // Update order item with new variation
            $exchange->orderItem->update([
                'product_variation_id' => $exchange->exchange_product_variation_id,
                'name' => $exchange->exchange_variant_name,
                'sku' => $exchange->exchangeVariation->sku,
            ]);

            // Notification for member
            Notification::create([
                'member_id' => $exchange->member_id,
                'type' => 'order_type_alert',
                'title' => 'Exchange Approved',
                'message' => "Your exchange request for order {$exchange->order->order_number} has been approved. Original: {$exchange->original_variant_name}, New: {$exchange->exchange_variant_name}",
                'data' => [
                    'exchange_id' => $exchange->id,
                    'order_number' => $exchange->order->order_number,
                    'original_variant' => $exchange->original_variant_name,
                    'exchange_variant' => $exchange->exchange_variant_name,
                    'quantity' => $exchange->quantity,
                ],
                'status' => 'unread',
                'is_read' => false
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Exchange approved successfully',
                'data' => [
                    'exchange_id' => $exchange->id,
                    'order_number' => $exchange->order->order_number,
                    'original_variant' => $exchange->original_variant_name,
                    'exchange_variant' => $exchange->exchange_variant_name,
                    'quantity' => $exchange->quantity,
                    'stock_adjusted' => true,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve exchange',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Reject exchange request
     * Merchant Route: POST /merchant/exchanges/{id}/reject
     */
    public function rejectExchange(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $merchant = auth('merchant')->user();

            $exchange = OrderExchange::with(['order', 'member'])
                ->byMerchant($merchant->merchant_id)
                ->find($id);

            if (!$exchange) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exchange request not found'
                ], 404);
            }

            if ($exchange->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending exchanges can be rejected'
                ], 400);
            }

            // Reject exchange
            $exchange->reject($request->rejection_reason, $merchant->merchant_id);

            // Notification for member
            Notification::create([
                'member_id' => $exchange->member_id,
                'type' => 'order_type_alert',
                'title' => 'Exchange Rejected',
                'message' => "Your exchange request for order {$exchange->order->order_number} has been rejected. Reason: {$request->rejection_reason}",
                'data' => [
                    'exchange_id' => $exchange->id,
                    'order_number' => $exchange->order->order_number,
                    'rejection_reason' => $request->rejection_reason,
                ],
                'status' => 'unread',
                'is_read' => false
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Exchange rejected successfully',
                'data' => [
                    'exchange_id' => $exchange->id,
                    'rejection_reason' => $request->rejection_reason,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject exchange',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark exchange as completed (delivered to customer)
     * Merchant Route: POST /merchant/exchanges/{id}/complete
     */
    public function completeExchange($id)
    {
        DB::beginTransaction();

        try {
            $merchant = auth('merchant')->user();

            $exchange = OrderExchange::with(['order', 'member'])
                ->byMerchant($merchant->merchant_id)
                ->find($id);

            if (!$exchange) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exchange request not found'
                ], 404);
            }

            if ($exchange->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved exchanges can be completed'
                ], 400);
            }

            // Mark as completed
            $exchange->markAsCompleted();

            // Notification for member
            Notification::create([
                'member_id' => $exchange->member_id,
                'type' => 'order_type_alert',
                'title' => 'Exchange Completed',
                'message' => "Your exchange for order {$exchange->order->order_number} has been completed.",
                'data' => [
                    'exchange_id' => $exchange->id,
                    'order_number' => $exchange->order->order_number,
                ],
                'status' => 'unread',
                'is_read' => false
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Exchange marked as completed',
                'data' => $exchange
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete exchange',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get available variations for exchange (same product, different variations)
     * Merchant Route: GET /merchant/exchanges/available-variations/{orderItemId}
     */
    public function getAvailableVariations($orderItemId)
    {
        try {
            $merchant = auth('merchant')->user();

            $orderItem = OrderItem::with(['product', 'productVariation'])
                ->where('merchant_id', $merchant->merchant_id)
                ->find($orderItemId);

            if (!$orderItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order item not found'
                ], 404);
            }

            // Get all variations of the same product (excluding current variation)
            $availableVariations = ProductVariation::where('product_id', $orderItem->product_id)
                ->where('id', '!=', $orderItem->product_variation_id)
                ->where('actual_quantity', '>', 0)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'current_variation' => $orderItem->productVariation,
                    'available_variations' => $availableVariations,
                    'product' => $orderItem->product
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available variations',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get exchange statistics for merchant
     * Merchant Route: GET /merchant/exchanges/statistics
     */
    public function getExchangeStatistics()
    {
        try {
            $merchant = auth('merchant')->user();

            $stats = [
                'total' => OrderExchange::byMerchant($merchant->merchant_id)->count(),
                'pending' => OrderExchange::byMerchant($merchant->merchant_id)->pending()->count(),
                'approved' => OrderExchange::byMerchant($merchant->merchant_id)->approved()->count(),
                'rejected' => OrderExchange::byMerchant($merchant->merchant_id)->rejected()->count(),
                'completed' => OrderExchange::byMerchant($merchant->merchant_id)->completed()->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}