<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderOnholdPoint;
use App\Models\OrderExchange;
use App\Models\Member;
use App\Models\MemberWallet;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\ShippingZone;
use App\Models\MerchantShippingRate;
use App\Models\Transaction;
use App\Models\Notification;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Helpers\CommonFunctionHelper;
use Illuminate\Support\Facades\Log;
use App\Models\Referral;
use App\Models\CompanyInfo;
use App\Traits\PointDistributionTrait; 

class OrderController extends Controller
{
    use PointDistributionTrait;
    
    protected $settingAttributes;

    public function __construct(CommonFunctionHelper $commonFunctionHelper)
    {
        $this->settingAttributes = $commonFunctionHelper->settingAttributes()['maxreward'];
    }

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

        DB::beginTransaction();
        
        try {

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

            // Deduct total points from member wallet
            $wallet->decrement('available_points', $totalPointsNeeded);

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

                // Create onhold points record
                OrderOnholdPoint::createFromOrder($order);

                // Transaction record - Points deducted from member
                Transaction::create([
                    'member_id' => $member->id,
                    'transaction_points' => $order->total_points,
                    'transaction_type' => Transaction::TYPE_DP,
                    'points_type' => Transaction::POINTS_DEBITED,
                    'transaction_reason' => "Order {$order->order_number} placed. Points on hold.",
                    'bap' => $wallet->available_points,
                    'bop' => $wallet->onhold_points,
                    'brp' => $wallet->total_rp
                ]);

                // Notification for member
                Notification::create([
                    'member_id' => $member->id,
                    'type' => 'order_type_alert',
                    'title' => 'Order Placed Successfully',
                    'message' => "Your order {$order->order_number} has been placed. Total: {$order->total_points} points.",
                    'data' => [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'total_points' => $order->total_points,
                    ],
                    'status' => 'unread',
                    'is_read' => false
                ]);

                // Notification for merchant
                Notification::create([
                    'merchant_id' => $order->merchant_id,
                    'type' => 'order_type_alert',
                    'title' => 'New Order Received',
                    'message' => "New order {$order->order_number} from {$member->name}. Total: {$order->total_points} points.",
                    'data' => [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'member_name' => $member->name,
                        'total_points' => $order->total_points,
                    ],
                    'status' => 'unread',
                    'is_read' => false
                ]);

                $createdOrders[] = $order->load(['items', 'shippingZone', 'shippingMethod']);
            }

            // Deduct points
            // $wallet->available_points -= $totalPointsNeeded;
            // $wallet->save();

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
     * Member Route: GET /member/orders
     */
    public function getMyOrders(Request $request)
    {
        try {
            $member = auth('member')->user();
            
            $query = Order::with(['merchant', 'items', 'onholdPoints'])
                ->byMember($member->id);

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $orders = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $orders
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get single order details
     * Member Route: GET /member/orders/{orderNumber}
     */
    public function getOrderDetails($orderNumber)
    {
        try {
            $member = auth('member')->user();
            
            $order = Order::with(['merchant', 'items.product', 'items.productVariation', 'onholdPoints', 'exchanges'])
                ->where('order_number', $orderNumber)
                ->byMember($member->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $order
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get merchant's orders
     * Merchant Route: GET /merchant/orders
     */
    public function getMerchantOrders(Request $request)
    {
        try {
            $merchant = auth('merchant')->user();
            
            $query = Order::with(['member', 'items', 'onholdPoints'])
                ->byMerchant($merchant->merchant_id);

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $orders = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $orders
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Cancel order (Merchant only, pending status only)
     * Merchant Route: POST /merchant/orders/{orderNumber}/cancel
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
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $merchant = auth('merchant')->user();
            
            $order = Order::with(['member.wallet', 'onholdPoints'])
                ->where('order_number', $orderNumber)
                ->byMerchant($merchant->merchant_id)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            if (!$order->canBeCancelledByMerchant()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending orders can be cancelled'
                ], 400);
            }

            // Cancel order
            $result = $order->cancelOrder(
                'merchant',
                $merchant->merchant_id,
                $request->reason_type,
                $request->reason_details
            );

            if (!$result) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel order'
                ], 400);
            }

            // Notification for member
            Notification::create([
                'member_id' => $order->member_id,
                'type' => 'order_type_alert',
                'title' => 'Order Cancelled',
                'message' => "Your order {$orderNumber} has been cancelled by merchant. Points refunded: {$order->total_points}",
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $orderNumber,
                    'reason' => $request->reason_details,
                    'refunded_points' => $order->total_points,
                ],
                'status' => 'unread',
                'is_read' => false
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'data' => [
                    'order_number' => $orderNumber,
                    'refunded_points' => $order->total_points,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Ship order with tracking number
     * Merchant Route: POST /merchant/orders/{orderNumber}/ship
     */
    public function shipOrder(Request $request, $orderNumber)
    {
        $validator = Validator::make($request->all(), [
            'tracking_number' => 'required|string',
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
            
            $order = Order::with(['member', 'onholdPoints'])
                ->where('order_number', $orderNumber)
                ->byMerchant($merchant->merchant_id)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            if (!$order->canBeShipped()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending orders can be shipped'
                ], 400);
            }

            // Get auto-release days from settings
            $autoReleaseDays = $this->settingAttributes['auto_release_days'] ?? 3;

            // Mark as shipped
            $order->markAsShipped($request->tracking_number, $autoReleaseDays);

            // Notification for member
            Notification::create([
                'member_id' => $order->member_id,
                'type' => 'order_type_alert',
                'title' => 'Order Shipped',
                'message' => "Your order {$orderNumber} has been shipped. Tracking: {$request->tracking_number}",
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $orderNumber,
                    'tracking_number' => $request->tracking_number,
                    'shipped_at' => $order->shipped_at,
                ],
                'status' => 'unread',
                'is_read' => false
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order marked as shipped',
                'data' => [
                    'order_number' => $orderNumber,
                    'tracking_number' => $request->tracking_number,
                    'shipped_at' => $order->shipped_at,
                    'auto_release_at' => $order->onholdPoints->auto_release_at ?? null,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to ship order',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }


     /**
     * Release points and distribute (called by cron job or manually)
     * This method distributes points like approvePurchase
     */
    public function releaseOrderPoints($orderId)
    {
        DB::beginTransaction();

        try {
            $order = Order::with([
                'member.wallet', 
                'merchant.wallet', 
                'merchant.corporateMember.wallet',
                'onholdPoints'
            ])->find($orderId);

            if (!$order) {
                return [
                    'success' => false,
                    'message' => 'Order not found'
                ];
            }

            // Check if order is shipped and points are on hold
            if ($order->status !== 'shipped' || !$order->onholdPoints || $order->onholdPoints->status !== 'onhold') {
                return [
                    'success' => false,
                    'message' => 'Order not eligible for point release'
                ];
            }

            // Check if ready for release
            if (!$order->onholdPoints->isReadyForRelease()) {
                return [
                    'success' => false,
                    'message' => 'Order not yet ready for auto-release'
                ];
            }

            $orderTotalPoints = $order->total_points;
            $member = $order->member;
            $merchant = $order->merchant;

            // Calculate reward budget points
            $rewardBudget = $merchant->reward_budget ?? 10; // Default 10%
            $totalRewardAmount = ($orderTotalPoints * $rewardBudget) / 100;

            $rmPoints = $this->settingAttributes['rm_points'];

            $totalPoints = $totalRewardAmount * $rmPoints;

            // Check if merchant has enough points in corporate wallet
            if (!$merchant->corporateMember || !$merchant->corporateMember->wallet) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Merchant corporate account not found'
                ];
            }

            if ($merchant->corporateMember->wallet->available_points < $totalPoints) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Insufficient points in merchant corporate wallet for reward distribution'
                ];
            }

            // Step 1: Add total points to merchant wallet
            $merchant->wallet->increment('total_points', $totalPoints);

            Transaction::create([
                'merchant_id' => $merchant->id,
                'transaction_points' => $totalPoints,
                'transaction_type' => Transaction::TYPE_AP,
                'points_type' => Transaction::POINTS_CREDITED,
                'transaction_reason' => "Order {$order->order_number} completed. Points released.",
                'merchant_balance' => $merchant->wallet->total_points
            ]);

            // Step 2: Add total points to merchant corporate member wallet
            $merchant->corporateMember->wallet->increment('available_points', $totalPoints);
            $merchant->corporateMember->wallet->increment('total_points', $totalPoints);

            Transaction::create([
                'member_id' => $merchant->corporateMember->id,
                'transaction_points' => $totalPoints,
                'transaction_type' => Transaction::TYPE_AP,
                'points_type' => Transaction::POINTS_CREDITED,
                'transaction_reason' => "Order {$order->order_number} completed.",
                'bap' => $merchant->corporateMember->wallet->available_points,
                'bop' => $merchant->corporateMember->wallet->onhold_points,
                'brp' => $merchant->corporateMember->wallet->total_rp
            ]);

            // Step 3: Deduct reward budget points from Merchant wallet
            $merchant->wallet->decrement('total_points', $totalPoints);

            Transaction::create([
                'merchant_id' => $merchant->id,
                'transaction_points' => $totalPoints,
                'transaction_type' => Transaction::TYPE_DP,
                'points_type' => Transaction::POINTS_DEBITED,
                'transaction_reason' => "Reward budget deducted for order {$order->order_number}. Budget: {$rewardBudget}%",
                'merchant_balance' => $merchant->wallet->total_points
            ]);

            // Step 4: Deduct reward budget points from Merchant corporate wallet
            $merchant->corporateMember->wallet->decrement('available_points', $totalPoints);

            Transaction::create([
                'member_id' => $merchant->corporateMember->id,
                'transaction_points' => $totalPoints,
                'transaction_type' => Transaction::TYPE_DP,
                'points_type' => Transaction::POINTS_DEBITED,
                'transaction_reason' => "Reward budget deducted for order {$order->order_number}. Budget: {$rewardBudget}%",
                'bap' => $merchant->corporateMember->wallet->available_points,
                'bop' => $merchant->corporateMember->wallet->onhold_points,
                'brp' => $merchant->corporateMember->wallet->total_rp
            ]);

            // Step 5: Distribute PP (Personal Points) to buyer
            $ppAmount = $totalPoints * ($this->settingAttributes['pp_points'] / 100);
            $member->wallet->increment('total_pp', $ppAmount);
            $member->wallet->increment('available_points', $ppAmount);
            $member->wallet->increment('total_points', $ppAmount);

            Transaction::create([
                'member_id' => $member->id,
                'transaction_points' => $ppAmount,
                'transaction_type' => Transaction::TYPE_PP,
                'points_type' => Transaction::POINTS_CREDITED,
                'transaction_reason' => "Personal Points from order {$order->order_number}",
                'bap' => $member->wallet->available_points,
                'brp' => $member->wallet->total_rp,
                'bop' => $member->wallet->onhold_points
            ]);

            Notification::create([
                'member_id' => $member->id,
                'type' => 'personal_points_earned',
                'title' => 'Points Earned!',
                'message' => "You earned {$ppAmount} Personal Points from order {$order->order_number}!",
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'pp_points' => $ppAmount,
                ],
                'status' => 'unread',
                'is_read' => false
            ]);

            // Step 6: Distribute RP (Referral Points) to sponsor
            $rpAmount = $totalPoints * ($this->settingAttributes['rp_points'] / 100);
            $sponsor = Referral::where('child_member_id', $member->id)->first();

            if ($sponsor) {
                $sponsorMember = Member::find($sponsor->sponsor_member_id);
                if ($sponsorMember && $sponsorMember->wallet) {
                    $sponsorMember->wallet->increment('available_points', $rpAmount);
                    $sponsorMember->wallet->increment('total_points', $rpAmount);

                    Transaction::create([
                        'member_id' => $sponsorMember->id,
                        'transaction_points' => $rpAmount,
                        'transaction_type' => Transaction::TYPE_RP,
                        'points_type' => Transaction::POINTS_CREDITED,
                        'transaction_reason' => "Referral Points from {$member->name}'s order {$order->order_number}",
                        'bap' => $sponsorMember->wallet->available_points,
                        'brp' => $sponsorMember->wallet->total_rp,
                        'bop' => $sponsorMember->wallet->onhold_points
                    ]);

                    Notification::create([
                        'member_id' => $sponsorMember->id,
                        'type' => 'referral_points_earned',
                        'title' => 'Referral Points Earned',
                        'message' => "You earned {$rpAmount} Referral Points from {$member->name}'s order!",
                        'data' => [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'rp_points' => $rpAmount,
                            'member_name' => $member->name,
                        ],
                        'status' => 'unread',
                        'is_read' => false
                    ]);
                }
            }

            // Step 7: Distribute CP (Community Points) - 30 level distribution
            $cpAmount = $totalPoints * ($this->settingAttributes['cp_points'] / 100);
            $this->distributeCommunityPoints($member, $member->id, $member->id, $cpAmount, 'order', null, null, $totalPoints, $order->order_number);

            // Step 8: Add CR (Company Reserve)
            $crAmount = $totalPoints * ($this->settingAttributes['cr_points'] / 100);
            $company = CompanyInfo::getCompany();
            $company->incrementCrPoint($crAmount);

            Transaction::create([
                'transaction_points' => $crAmount,
                'transaction_type' => Transaction::TYPE_CR,
                'points_type' => Transaction::POINTS_CREDITED,
                'transaction_reason' => "Company Reserve from order {$order->order_number}",
                'cr_balance' => $company->cr_points
            ]);

            // Step 9: Mark onhold points as released
            $order->onholdPoints->releasePoints();

            // Step 10: Mark order as completed
            $order->markAsCompleted();

            // Notifications
            Notification::create([
                'merchant_id' => $merchant->id,
                'type' => 'order_type_alert',
                'title' => 'Order Completed',
                'message' => "Order {$order->order_number} has been completed. Points distributed.",
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_points' => $totalPoints,
                ],
                'status' => 'unread',
                'is_read' => false
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Points released and distributed successfully',
                'data' => [
                    'order_number' => $order->order_number,
                    'total_points' => $totalPoints,
                    'distribution' => [
                        'pp_points' => $ppAmount,
                        'rp_points' => $rpAmount,
                        'cp_points' => $cpAmount,
                        'cr_points' => $crAmount,
                    ]
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Illuminate\Support\Facades\Log::error('Point release failed: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'exception' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to release points',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ];
        }
    }

    /**
     * Distribute community points across 30-level tree
     * This should match your existing distributeCommunityPoints method
     */
    // private function distributeCommunityPoints($sourceMember, $newMemberId, $purchaseMemberId, $cpAmount, $type = 'order', $referenceId = null, $transactionId = null, $transactionAmount = 0)
    // {
    //     // Use your existing distributeCommunityPoints logic from MerchantController
    //     // This is a placeholder - implement according to your existing logic
        
    //     $levelPercentages = [
    //         1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 5,
    //         6 => 4, 7 => 4, 8 => 4, 9 => 4, 10 => 4,
    //         11 => 3, 12 => 3, 13 => 3, 14 => 3, 15 => 3,
    //         16 => 2, 17 => 2, 18 => 2, 19 => 2, 20 => 2,
    //         21 => 1.5, 22 => 1.5, 23 => 1.5, 24 => 1.5, 25 => 1.5,
    //         26 => 1, 27 => 1, 28 => 1, 29 => 1, 30 => 1
    //     ];

    //     $currentMember = $sourceMember;
    //     $level = 1;

    //     while ($currentMember && $level <= 30) {
    //         $sponsor = \App\Models\Referral::where('child_member_id', $currentMember->id)->first();
            
    //         if (!$sponsor) break;

    //         $sponsorMember = \App\Models\Member::with('wallet')->find($sponsor->sponsor_member_id);
            
    //         if (!$sponsorMember || !$sponsorMember->wallet) {
    //             $currentMember = $sponsorMember;
    //             $level++;
    //             continue;
    //         }

    //         $levelPercentage = $levelPercentages[$level] ?? 0;
    //         $levelPoints = ($cpAmount * $levelPercentage) / 100;

    //         // Check unlock level
    //         $unlockedLevel = $sponsorMember->wallet->unlocked_level ?? 5;

    //         if ($level <= $unlockedLevel) {
    //             // Released to available points
    //             $sponsorMember->wallet->increment('available_points', $levelPoints);
    //             $sponsorMember->wallet->increment('total_cp', $levelPoints);
    //             $sponsorMember->wallet->increment('total_points', $levelPoints);

    //             Transaction::create([
    //                 'member_id' => $sponsorMember->id,
    //                 'transaction_points' => $levelPoints,
    //                 'transaction_type' => Transaction::TYPE_CP,
    //                 'points_type' => Transaction::POINTS_CREDITED,
    //                 'transaction_reason' => "Level {$level} CP from order",
    //                 'bap' => $sponsorMember->wallet->available_points,
    //                 'brp' => $sponsorMember->wallet->total_rp,
    //                 'bop' => $sponsorMember->wallet->onhold_points
    //             ]);
    //         } else {
    //             // Locked in onhold points
    //             $sponsorMember->wallet->increment('onhold_points', $levelPoints);
    //             $sponsorMember->wallet->increment('total_cp', $levelPoints);
    //             $sponsorMember->wallet->increment('total_points', $levelPoints);

    //             Transaction::create([
    //                 'member_id' => $sponsorMember->id,
    //                 'transaction_points' => $levelPoints,
    //                 'transaction_type' => Transaction::TYPE_CP,
    //                 'points_type' => Transaction::POINTS_CREDITED,
    //                 'transaction_reason' => "Level {$level} CP (locked) from order",
    //                 'bap' => $sponsorMember->wallet->available_points,
    //                 'brp' => $sponsorMember->wallet->total_rp,
    //                 'bop' => $sponsorMember->wallet->onhold_points
    //             ]);
    //         }

    //         $currentMember = $sponsorMember;
    //         $level++;
    //     }
    // }

    /**
     * Auto-complete orders (run by cron job)
     * Command: php artisan orders:auto-complete
     */
    public function autoCompleteOrders()
    {
        try {
            // Find all orders ready for auto-completion
            $readyOrders = OrderOnholdPoint::readyForRelease()
                ->with('order')
                ->get();

            $results = [
                'total' => $readyOrders->count(),
                'success' => 0,
                'failed' => 0,
                'errors' => []
            ];

            foreach ($readyOrders as $onholdPoint) {
                $result = $this->releaseOrderPoints($onholdPoint->order_id);
                
                if ($result['success']) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = [
                        'order_id' => $onholdPoint->order_id,
                        'error' => $result['message']
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Auto-completion completed',
                'data' => $results
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auto-completion failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

}