<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Member;
use App\Models\MemberWallet;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\OrderCancelReason;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\ShippingZone;
use App\Models\MerchantShippingRate;

class OrderController extends Controller
{

    // ===================================================================
    // CALCULATE SHIPPING API - Updated Weight Logic
    // ===================================================================

    public function calculateShipping(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_postcode' => 'required|string',
            'merchants' => 'required|array|min:1',
            'merchants.*.merchant_id' => 'required|exists:merchants,id',
            'merchants.*.shipping_method_id' => 'required|exists:shipping_methods,id',
            'merchants.*.items' => 'required|array|min:1',
            'merchants.*.items.*.product_id' => 'required|exists:products,id',
            'merchants.*.items.*.product_variation_id' => 'required|exists:product_variations,id',
            'merchants.*.items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Detect shipping zone from postcode
        $zone = ShippingZone::detectZoneByPostcode($request->customer_postcode);
        
        if (!$zone) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid postcode or shipping zone not found'
            ], 400);
        }

        $shippingDetails = [];
        $totalShipping = 0;

        foreach ($request->merchants as $merchantData) {
            $merchantId = $merchantData['merchant_id'];
            $methodId = $merchantData['shipping_method_id'];
            
            // Calculate total weight for this merchant
            $totalWeight = 0;
            $merchantSubtotal = 0;
            
            foreach ($merchantData['items'] as $item) {
                // ✅ Try variation first, then product
                $variation = ProductVariation::find($item['product_variation_id']);
                $product = Product::find($item['product_id']);
                
                // Weight calculation: variation > product
                $unitWeight = 0;
                if ($variation && $variation->unit_weight > 0) {
                    $unitWeight = $variation->unit_weight;
                } elseif ($product) {
                    $unitWeight = $product->unit_weight ?? 0;
                }
                
                $totalWeight += $unitWeight * $item['quantity'];
                
                // Calculate subtotal for free shipping check - variation price > product price
                $price = 0;
                if ($variation) {
                    $price = $variation->sale_point ?? $variation->regular_point;
                } elseif ($product) {
                    $price = $product->sale_point ?? $product->regular_point;
                }
                $merchantSubtotal += $price * $item['quantity'];
            }

            // Calculate shipping cost
            $shippingCalc = MerchantShippingRate::calculateShipping(
                $merchantId,
                $zone->id,
                $methodId,
                $totalWeight,
                $merchantSubtotal
            );

            if (!$shippingCalc) {
                return response()->json([
                    'success' => false,
                    'message' => "No shipping rate found for merchant {$merchantId} to {$zone->name}",
                    'merchant_id' => $merchantId,
                    'zone' => $zone->name,
                ], 400);
            }

            $shippingDetails[] = [
                'merchant_id' => $merchantId,
                'shipping_zone_id' => $zone->id,
                'shipping_zone_name' => $zone->name,
                'shipping_method_id' => $methodId,
                'total_weight' => $totalWeight,
                'shipping_points' => $shippingCalc['shipping_points'],
                'is_free_shipping' => $shippingCalc['is_free_shipping'],
            ];

            $totalShipping += $shippingCalc['shipping_points'];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'detected_zone' => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'code' => $zone->zone_code,
                ],
                'shipping_by_merchant' => $shippingDetails,
                'total_shipping_points' => $totalShipping,
            ]
        ]);
    }


    /**
     * Create orders (multiple orders - one per merchant)
     * 
     * Request Body Format:
     * {
     *   "customer_info": {
     *     "full_name": "John Doe",
     *     "email": "john@example.com",
     *     "phone": "60123456789",
     *     "address": "123 Main St",
     *     "postcode": "50000",
     *     "city": "Kuala Lumpur",
     *     "state": "Kuala Lumpur",
     *     "country": "Malaysia"
     *   },
     *   "merchants": [
     *     {
     *       "merchant_id": 1,
     *       "shipping_points": 10,
     *       "items": [
     *         {
     *           "product_id": 1,
     *           "product_variation_id": null,
     *           "quantity": 2,
     *           "points": 50
     *         },
     *         {
     *           "product_id": 2,
     *           "product_variation_id": 3,
     *           "quantity": 1,
     *           "points": 75
     *         }
     *       ]
     *     },
     *     {
     *       "merchant_id": 2,
     *       "shipping_points": 15,
     *       "items": [...]
     *     }
     *   ]
     * }
     */
    // public function createOrders_OLD(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'customer_info' => 'required|array',
    //         'customer_info.full_name' => 'required|string|max:200',
    //         'customer_info.email' => 'nullable|email|max:100',
    //         'customer_info.phone' => 'required|string|max:20',
    //         'customer_info.address' => 'required|string',
    //         'customer_info.postcode' => 'nullable|string|max:20',
    //         'customer_info.city' => 'nullable|string|max:100',
    //         'customer_info.state' => 'nullable|string|max:100',
    //         'customer_info.country' => 'nullable|string|max:100',
    //         'merchants' => 'required|array|min:1',
    //         'merchants.*.merchant_id' => 'required|exists:merchants,id',
    //         'merchants.*.shipping_points' => 'nullable|numeric|min:0',
    //         'merchants.*.items' => 'required|array|min:1',
    //         'merchants.*.items.*.product_id' => 'required|exists:products,id',
    //         'merchants.*.items.*.product_variation_id' => 'nullable|exists:product_variations,id',
    //         'merchants.*.items.*.quantity' => 'required|integer|min:1',
    //         'merchants.*.items.*.points' => 'required|numeric|min:0',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     $member = auth('member')->user();
    //     $wallet = $member->wallet;

    //     // Calculate total points needed
    //     $totalPointsNeeded = 0;
    //     foreach ($request->merchants as $merchantData) {
    //         $merchantTotal = $merchantData['shipping_points'] ?? 0;
    //         foreach ($merchantData['items'] as $item) {
    //             $merchantTotal += $item['points'] * $item['quantity'];
    //         }
    //         $totalPointsNeeded += $merchantTotal;
    //     }

    //     // Check if member has sufficient points
    //     if ($wallet->available_points < $totalPointsNeeded) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Insufficient points',
    //             'required_points' => $totalPointsNeeded,
    //             'available_points' => $wallet->available_points,
    //             'shortage' => $totalPointsNeeded - $wallet->available_points
    //         ], 400);
    //     }

    //     DB::beginTransaction();
        
    //     try {
    //         $createdOrders = [];

    //         // Create separate order for each merchant
    //         foreach ($request->merchants as $merchantData) {
    //             $merchantId = $merchantData['merchant_id'];
    //             $shippingPoints = $merchantData['shipping_points'] ?? 0;
                
    //             // Calculate order total
    //             $orderTotal = $shippingPoints;
    //             $totalWeight = 0;
                
    //             foreach ($merchantData['items'] as $itemData) {
    //                 $orderTotal += $itemData['points'] * $itemData['quantity'];
                    
    //                 // Get product weight (if available)
    //                 $product = Product::find($itemData['product_id']);
    //                 if ($product) {
    //                     $totalWeight += ($product->unit_weight ?? 0) * $itemData['quantity'];
    //                 }
    //             }

    //             // Create order
    //             $order = Order::create([
    //                 'merchant_id' => $merchantId,
    //                 'member_id' => $member->id,
    //                 'order_number' => Order::generateOrderNumber(),
    //                 'status' => 'pending',
    //                 'shipping_points' => $shippingPoints,
    //                 'total_points' => $orderTotal,
    //                 'customer_full_name' => $request->customer_info['full_name'],
    //                 'customer_email' => $request->customer_info['email'] ?? null,
    //                 'customer_phone' => $request->customer_info['phone'],
    //                 'customer_address' => $request->customer_info['address'],
    //                 'customer_postcode' => $request->customer_info['postcode'] ?? null,
    //                 'customer_city' => $request->customer_info['city'] ?? null,
    //                 'customer_state' => $request->customer_info['state'] ?? null,
    //                 'customer_country' => $request->customer_info['country'] ?? 'Malaysia',
    //                 'total_weight' => $totalWeight,
    //             ]);

    //             // Create order items
    //             foreach ($merchantData['items'] as $itemData) {
    //                 $product = Product::find($itemData['product_id']);
    //                 $variation = $itemData['product_variation_id'] 
    //                     ? ProductVariation::find($itemData['product_variation_id']) 
    //                     : null;

    //                 OrderItem::create([
    //                     'order_id' => $order->id,
    //                     'merchant_id' => $merchantId,
    //                     'member_id' => $member->id,
    //                     'product_id' => $itemData['product_id'],
    //                     'product_variation_id' => $itemData['product_variation_id'],
    //                     'name' => $product ? $product->name : null,
    //                     'sku' => $variation ? $variation->sku : null,
    //                     'quantity' => $itemData['quantity'],
    //                     'points' => $itemData['points'],
    //                 ]);
    //             }

    //             $createdOrders[] = $order->load('items');
    //         }

    //         // Deduct total points from member wallet
    //         $wallet->available_points -= $totalPointsNeeded;
    //         $wallet->save();

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => count($createdOrders) . ' order(s) created successfully',
    //             'data' => [
    //                 'orders' => $createdOrders,
    //                 'total_orders' => count($createdOrders),
    //                 'total_points_spent' => $totalPointsNeeded,
    //                 'remaining_points' => $wallet->available_points,
    //             ]
    //         ], 201);

    //     } catch (\Exception $e) {
    //         DB::rollback();
            
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to create orders',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    // ===================================================================
    // CREATE ORDERS API - Updated Weight Logic + Validation
    // ===================================================================

    public function createOrders(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_info' => 'required|array',
            'customer_info.full_name' => 'required|string|max:200',
            'customer_info.email' => 'required|email|max:100',  // ✅ Required
            'customer_info.phone' => 'required|string|max:20',
            'customer_info.address' => 'required|string',
            'customer_info.postcode' => 'required|string|max:20',
            'customer_info.city' => 'required|string|max:100',
            'customer_info.state' => 'required|string|max:100',
            'customer_info.country' => 'nullable|string|max:100',
            'merchants' => 'required|array|min:1',
            'merchants.*.merchant_id' => 'required|exists:merchants,id',
            'merchants.*.shipping_method_id' => 'required|exists:shipping_methods,id',
            'merchants.*.items' => 'required|array|min:1',
            'merchants.*.items.*.product_id' => 'required|exists:products,id',
            'merchants.*.items.*.product_variation_id' => 'required|exists:product_variations,id',  // ✅ Required
            'merchants.*.items.*.quantity' => 'required|integer|min:1',
            'merchants.*.items.*.points' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $member = auth('member')->user();
        $wallet = $member->wallet;

        // Detect shipping zone
        $zone = ShippingZone::detectZoneByPostcode($request->customer_info['postcode']);
        
        if (!$zone) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid postcode or shipping zone not found'
            ], 400);
        }

        // Calculate total points needed
        $totalPointsNeeded = 0;
        $shippingBreakdown = [];

        foreach ($request->merchants as $merchantData) {
            $merchantId = $merchantData['merchant_id'];
            $methodId = $merchantData['shipping_method_id'];
            
            // Calculate merchant subtotal and weight
            $merchantSubtotal = 0;
            $totalWeight = 0;
            
            foreach ($merchantData['items'] as $item) {
                $merchantSubtotal += $item['points'] * $item['quantity'];
                
                // ✅ Try variation first, then product
                $variation = ProductVariation::find($item['product_variation_id']);
                $product = Product::find($item['product_id']);
                
                // Weight calculation: variation > product
                $unitWeight = 0;
                if ($variation && $variation->unit_weight > 0) {
                    $unitWeight = $variation->unit_weight;
                } elseif ($product) {
                    $unitWeight = $product->unit_weight ?? 0;
                }
                
                $totalWeight += $unitWeight * $item['quantity'];
            }

            // Calculate shipping
            $shippingCalc = MerchantShippingRate::calculateShipping(
                $merchantId,
                $zone->id,
                $methodId,
                $totalWeight,
                $merchantSubtotal
            );

            if (!$shippingCalc) {
                return response()->json([
                    'success' => false,
                    'message' => "No shipping rate available for merchant {$merchantId}",
                ], 400);
            }

            $shippingPoints = $shippingCalc['shipping_points'];
            $shippingBreakdown[$merchantId] = [
                'weight' => $totalWeight,
                'shipping_points' => $shippingPoints,
                'is_free' => $shippingCalc['is_free_shipping'],
            ];

            $totalPointsNeeded += $merchantSubtotal + $shippingPoints;
        }

        // Check if member has sufficient points
        if ($wallet->available_points < $totalPointsNeeded) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient points',
                'required_points' => $totalPointsNeeded,
                'available_points' => $wallet->available_points,
                'shortage' => $totalPointsNeeded - $wallet->available_points
            ], 400);
        }

        DB::beginTransaction();
        
        try {
            $createdOrders = [];

            // Create orders
            foreach ($request->merchants as $merchantData) {
                $merchantId = $merchantData['merchant_id'];
                $methodId = $merchantData['shipping_method_id'];
                
                $shippingData = $shippingBreakdown[$merchantId];
                
                // Calculate order total
                $orderTotal = 0;
                foreach ($merchantData['items'] as $item) {
                    $orderTotal += $item['points'] * $item['quantity'];
                }

                // Create order
                $order = Order::create([
                    'merchant_id' => $merchantId,
                    'member_id' => $member->id,
                    'order_number' => Order::generateOrderNumber(),
                    'status' => 'pending',
                    'shipping_zone_id' => $zone->id,
                    'shipping_method_id' => $methodId,
                    'shipping_points' => $shippingData['shipping_points'],
                    'total_points' => $orderTotal + $shippingData['shipping_points'],
                    'customer_full_name' => $request->customer_info['full_name'],
                    'customer_email' => $request->customer_info['email'],  // ✅ Now required
                    'customer_phone' => $request->customer_info['phone'],
                    'customer_address' => $request->customer_info['address'],
                    'customer_postcode' => $request->customer_info['postcode'],
                    'customer_city' => $request->customer_info['city'],
                    'customer_state' => $request->customer_info['state'],
                    'customer_country' => $request->customer_info['country'] ?? 'Malaysia',
                    'total_weight' => $shippingData['weight'],
                ]);

                // Create order items
                foreach ($merchantData['items'] as $itemData) {
                    $product = Product::find($itemData['product_id']);
                    $variation = ProductVariation::find($itemData['product_variation_id']);

                    OrderItem::create([
                        'order_id' => $order->id,
                        'merchant_id' => $merchantId,
                        'member_id' => $member->id,
                        'product_id' => $itemData['product_id'],
                        'product_variation_id' => $itemData['product_variation_id'],
                        'name' => $product ? $product->name : null,
                        'sku' => $variation ? $variation->sku : null,
                        'quantity' => $itemData['quantity'],
                        'points' => $itemData['points'],
                    ]);
                }

                // ✅ Reduce inventory after order is created
                $order->reduceInventory();

                $createdOrders[] = $order->load(['items', 'shippingZone', 'shippingMethod']);
            }

            // Deduct points
            $wallet->available_points -= $totalPointsNeeded;
            $wallet->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($createdOrders) . ' order(s) created successfully',
                'data' => [
                    'orders' => $createdOrders,
                    'total_orders' => count($createdOrders),
                    'total_points_spent' => $totalPointsNeeded,
                    'remaining_points' => $wallet->available_points,
                    'shipping_zone' => [
                        'name' => $zone->name,
                        'code' => $zone->zone_code,
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get member's orders
     */
    public function getMyOrders(Request $request)
    {
        $member = auth('member')->user();

        $query = Order::with(['merchant', 'items'])
            ->where('member_id', $member->id)
            ->whereNull('deleted_at');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Get single order details
     */
    public function getOrderDetails($orderNumber)
    {
        $member = auth('member')->user();
        
        $order = Order::with(['merchant', 'items.product', 'items.productVariation', 'cancelReason'])
            ->where('order_number', $orderNumber)
            ->where('member_id', $member->id)
            ->whereNull('deleted_at')
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => ['order' => $order]
        ]);
    }


    // Available Reason Types:

    // out_of_stock - Product out of stock
    // customer_request - Customer requested cancellation
    // wrong_order - Wrong order placed
    // payment_issue - Payment issue
    // delivery_issue - Delivery not possible
    // other - Other reason

    /**
     * Cancel order (Member)
     */
    public function cancelOrder(Request $request, $orderNumber)
    {
        $validator = Validator::make($request->all(), [
            'reason_type' => 'required|string',
            'reason_details' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $member = auth('member')->user();
        
        $order = Order::where('order_number', $orderNumber)
            ->where('member_id', $member->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending orders can be cancelled'
            ], 400);
        }

        DB::beginTransaction();
        
        try {
            // Cancel the order
            $order->cancelOrder('member', $member->id, $request->reason_type, $request->reason_details);

            // Refund points to member
            $wallet = $member->wallet;
            $wallet->available_points += $order->total_points;
            $wallet->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'data' => [
                    'order' => $order->load('cancelReason'),
                    'refunded_points' => $order->total_points,
                    'new_balance' => $wallet->available_points,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get merchant orders (Merchant/Staff)
     */
    public function getMerchantOrders(Request $request)
    {
        $merchant = auth('merchant')->user();
        
        $query = Order::with(['member', 'items'])
            ->where('merchant_id', $merchant->merchant_id)
            ->whereNull('deleted_at');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Complete order (Merchant)
     */
    public function completeOrder(Request $request, $orderNumber)
    {
        $merchant = auth('merchant')->user();
        
        $order = Order::where('order_number', $orderNumber)
            ->where('merchant_id', $merchant->merchant_id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending orders can be completed'
            ], 400);
        }

        $order->markAsCompleted();

        // Optionally update tracking number
        if ($request->has('tracking_number')) {
            $order->updateTracking($request->tracking_number);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order completed successfully',
            'data' => ['order' => $order]
        ]);
    }


    // Available Return Reason Types:

    // defective_product - Product is defective/damaged
    // wrong_item - Received wrong item
    // not_as_described - Product not as described
    // changed_mind - Customer changed mind
    // quality_issue - Quality not satisfactory
    // other - Other reason

    /**
     * Return order (Member - Request Return)
     */
    public function requestReturn(Request $request, $orderNumber)
    {
        $validator = Validator::make($request->all(), [
            'reason_type' => 'required|string',
            'reason_details' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $member = auth('member')->user();
        
        $order = Order::where('order_number', $orderNumber)
            ->where('member_id', $member->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Only completed orders can be returned'
            ], 400);
        }

        DB::beginTransaction();
        
        try {
            // Mark as returned
            $order->markAsReturned();

            // Create return reason record
            OrderCancelReason::create([
                'order_id' => $order->id,
                'cancelled_by_type' => 'member',
                'cancelled_by_id' => $member->id,
                'reason_type' => $request->reason_type,
                'reason_details' => $request->reason_details,
            ]);

            // Refund points to member
            $wallet = $member->wallet;
            $wallet->available_points += $order->total_points;
            $wallet->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order returned successfully and points refunded',
                'data' => [
                    'order' => $order,
                    'refunded_points' => $order->total_points,
                    'new_balance' => $wallet->available_points,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process return',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Return order (Merchant - Accept Return)
     */
    public function returnOrder(Request $request, $orderNumber)
    {
        $validator = Validator::make($request->all(), [
            'reason_type' => 'nullable|string',
            'reason_details' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $merchant = auth('merchant')->user();
        
        $order = Order::where('order_number', $orderNumber)
            ->where('merchant_id', $merchant->merchant_id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Only completed orders can be returned'
            ], 400);
        }

        DB::beginTransaction();
        
        try {
            $order->markAsReturned();

            // Create return reason record if provided
            if ($request->has('reason_type')) {
                OrderCancelReason::create([
                    'order_id' => $order->id,
                    'cancelled_by_type' => 'merchant',
                    'cancelled_by_id' => $merchant->id,
                    'reason_type' => $request->reason_type,
                    'reason_details' => $request->reason_details,
                ]);
            }

            // Refund points to member
            $wallet = MemberWallet::where('member_id', $order->member_id)->first();
            $wallet->available_points += $order->total_points;
            $wallet->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order marked as returned and points refunded to customer',
                'data' => [
                    'order' => $order,
                    'refunded_points' => $order->total_points,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process return',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
}