<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * Get member's cart - grouped by merchant
     * GET /api/member/cart
     */
    public function index()
    {
        try {
            $memberId = auth('member')->id();
            
            // Fetch all cart items with relationships
            $cartItems = Cart::with([
                'product', 
                'product.merchant', // Load merchant through product
                'productVariation.variationAttributes.attribute',
                'productVariation.variationAttributes.attributeItem'
            ])
                ->byMember($memberId)
                ->active()
                ->get();

            // Group cart items by merchant
            $groupedByMerchant = $cartItems->groupBy(function($item) {
                return $item->product->merchant_id ?? 'no_merchant';
            });

            $formattedCart = [];
            
            foreach ($groupedByMerchant as $merchantId => $merchantCartItems) {
                $merchantData = null;
                
                if ($merchantId !== 'no_merchant' && $merchantCartItems->first()->product->merchant) {
                    $merchant = $merchantCartItems->first()->product->merchant;
                    $merchantData = [
                        'merchant_id' => $merchant->id,
                        'merchant_name' => $merchant->business_name,
                        'merchant_logo' => $merchant->business_logo,
                        // 'merchant_unique_number' => $merchant->unique_number,
                        // 'merchant_status' => $merchant->status,
                    ];
                }

                $items = $merchantCartItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        // 'product_slug' => $item->product->slug,
                        // 'product_sku' => $item->product->sku_short_code,
                        // 'product_image' => $item->product->images ? $item->product->images[0] ?? null : null,
                        'variation_id' => $item->product_variation_id,
                        'variation_details' => $item->productVariation ? [
                            'sku' => $item->productVariation->sku,
                            // 'regular_price' => $item->productVariation->regular_price,
                            // 'regular_point' => $item->productVariation->regular_point,
                            'sale_price' => $item->productVariation->sale_price,
                            'sale_point' => $item->productVariation->sale_point,
                            'actual_quantity' => $item->productVariation->actual_quantity,
                            'image' => $item->productVariation->images[0] ?? null,
                            'attributes' => $item->productVariation->variationAttributes->map(function($attr) {
                                return [
                                    'id' => $attr->id,
                                    'attribute_id' => $attr->attribute_id,
                                    'attribute_name' => $attr->attribute->name ?? 'N/A',
                                    'attribute_item_id' => $attr->attribute_item_id,
                                    'attribute_item_name' => $attr->attributeItem->name ?? 'N/A',
                                    'display' => ($attr->attribute->name ?? 'N/A') . ': ' . ($attr->attributeItem->name ?? 'N/A'),
                                ];
                            }),
                        ] : null,
                        'quantity' => $item->quantity,
                        'price' => $item->getPrice(),
                        'subtotal' => $item->getSubtotal(),
                        'expire_date' => $item->expire_date,
                        'in_stock' => $item->checkStockAvailability(),
                        'created_at' => $item->created_at,
                    ];
                });

                // Calculate merchant subtotal
                $merchantSubtotal = $merchantCartItems->sum(function($item) {
                    return $item->getSubtotal();
                });

                $formattedCart[] = [
                    'merchant' => $merchantData,
                    'items' => $items,
                    'merchant_subtotal' => $merchantSubtotal,
                    'item_count' => $items->count(),
                ];
            }

            // Calculate overall totals
            $totalItems = $cartItems->count();
            $totalAmount = Cart::getMemberCartTotal($memberId);

            return response()->json([
                'success' => true,
                'data' => [
                    'cart_by_merchant' => $formattedCart,
                    'summary' => [
                        'total_items' => $totalItems,
                        'total_merchants' => count($formattedCart),
                        'total_amount' => $totalAmount,
                        'items_per_merchant' => array_map(function($merchantGroup) {
                            return [
                                'merchant_name' => $merchantGroup['merchant']['merchant_name'] ?? 'No Merchant',
                                'item_count' => $merchantGroup['item_count'],
                                'subtotal' => $merchantGroup['merchant_subtotal'],
                            ];
                        }, $formattedCart),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cart items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add item to cart
     * POST /api/member/cart
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|exists:products,id',
                'product_variation_id' => 'required|exists:product_variations,id',
                'quantity' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $memberId = auth('member')->id();
            
            // Check if product exists and is active
            $product = Product::with('merchant')->find($request->product_id);
            
            if (!$product || $product->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not available'
                ], 404);
            }

            // Get merchant info
            $merchantInfo = null;
            if ($product->merchant) {
                $merchantInfo = [
                    'id' => $product->merchant->id,
                    'name' => $product->merchant->business_name,
                    'logo' => $product->merchant->business_logo,
                ];
            }

            // Check if variation exists and is active
            $variation = ProductVariation::find($request->product_variation_id);
            
            if (!$variation || !$variation->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product variation not available'
                ], 404);
            }

            // ✅ IMPORTANT: Check if variation belongs to the product
            if ($variation->product_id != $request->product_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'This variation does not belong to the selected product'
                ], 400);
            }

            // Check stock
            if ($variation->actual_quantity < $request->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock. Available: ' . $variation->actual_quantity
                ], 400);
            }

            // Add or update cart
            $cart = Cart::addOrUpdate([
                'member_id' => $memberId,
                'product_id' => $request->product_id,
                'product_variation_id' => $request->product_variation_id,
                'quantity' => $request->quantity,
            ]);

            // Load relationships
            $cart->load(['product.merchant', 'productVariation']);

            // Get updated cart count
            $cartCount = Cart::getMemberCartCount($memberId);

            return response()->json([
                'success' => true,
                'message' => 'Product added to cart successfully',
                'data' => [
                    'cart_item' => [
                        'id' => $cart->id,
                        'product_id' => $cart->product_id,
                        'product_name' => $cart->product->name,
                        'merchant' => $merchantInfo,
                        'variation_id' => $cart->product_variation_id,
                        'variation_sku' => $cart->productVariation->sku ?? null,
                        'quantity' => $cart->quantity,
                        'price' => $cart->getPrice(),
                        'subtotal' => $cart->getSubtotal(),
                    ],
                    'cart_count' => $cartCount,
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add product to cart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update cart item quantity
     * PUT /api/member/cart/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'quantity' => 'required|integer|min:1',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
    
            $memberId = auth('member')->id();
            
            $cartItem = Cart::with(['product.merchant', 'productVariation'])
                ->byMember($memberId)
                ->find($id);
    
            if (!$cartItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found'
                ], 404);
            }

            // Get merchant info
            $merchantInfo = null;
            if ($cartItem->product->merchant) {
                $merchantInfo = [
                    'id' => $cartItem->product->merchant->id,
                    'name' => $cartItem->product->merchant->business_name,
                    'logo' => $cartItem->product->merchant->business_logo,
                ];
            }
    
            // Check stock if variation
            if ($cartItem->product_variation_id) {
                $variation = $cartItem->productVariation;
                
                if ($variation->actual_quantity < $request->quantity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock. Available: ' . $variation->actual_quantity
                    ], 400);
                }
            }
    
            $cartItem->quantity = $request->quantity;
            $cartItem->expire_date = \Carbon\Carbon::now()->addDays(7); // Refresh expiry
            $cartItem->save();
    
            return response()->json([
                'success' => true,
                'message' => 'Cart updated successfully',
                'data' => [
                    'cart_item' => [
                        'id' => $cartItem->id,
                        'product_id' => $cartItem->product_id,
                        'product_name' => $cartItem->product->name,
                        'merchant' => $merchantInfo,
                        'quantity' => $cartItem->quantity,
                        'price' => $cartItem->getPrice(),
                        'subtotal' => $cartItem->getSubtotal(),
                    ],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update cart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove item from cart
     * DELETE /api/member/cart/{id}
     */
    public function destroy($id)
    {
        try {
            $memberId = auth('member')->id();
        
            $cartItem = Cart::with('product.merchant')
                ->byMember($memberId)
                ->find($id);

            if (!$cartItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found'
                ], 404);
            }

            // Store merchant info before deletion
            $merchantInfo = null;
            if ($cartItem->product->merchant) {
                $merchantInfo = [
                    'id' => $cartItem->product->merchant->id,
                    'name' => $cartItem->product->merchant->business_name,
                ];
            }

            $cartItem->delete();

            // Get updated cart count
            $cartCount = Cart::getMemberCartCount($memberId);

            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart',
                'data' => [
                    'removed_item' => [
                        'id' => $id,
                        'product_name' => $cartItem->product->name,
                        'merchant' => $merchantInfo,
                    ],
                    'cart_count' => $cartCount,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item from cart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear entire cart
     * DELETE /api/member/cart
     */
    public function clear()
    {
        try {
            $memberId = auth('member')->id();
            
            // Get cart items before clearing for info
            $cartItems = Cart::with('product.merchant')
                ->byMember($memberId)
                ->get();
            
            $merchantsAffected = $cartItems->groupBy(function($item) {
                return $item->product->merchant_id ?? 'no_merchant';
            })->map(function($items, $merchantId) {
                if ($merchantId === 'no_merchant') {
                    return [
                        'merchant_name' => 'No Merchant',
                        'item_count' => $items->count(),
                    ];
                }
                
                $merchant = $items->first()->product->merchant;
                return [
                    'merchant_id' => $merchant->id,
                    'merchant_name' => $merchant->business_name,
                    'item_count' => $items->count(),
                ];
            })->values();
        
            Cart::clearMemberCart($memberId);
    
            return response()->json([
                'success' => true,
                'message' => 'Cart cleared successfully',
                'data' => [
                    'merchants_affected' => $merchantsAffected,
                    'total_items_cleared' => $cartItems->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cart count
     * GET /api/member/cart/count
     */
    public function count()
    {
        try {
            $memberId = auth('member')->id();
            $count = Cart::getMemberCartCount($memberId);

            return response()->json([
                'success' => true,
                'data' => [
                    'cart_count' => $count
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cart count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cart summary by merchant
     * GET /api/member/cart/summary
     */
    public function summary()
    {
        try {
            $memberId = auth('member')->id();
            
            $cartItems = Cart::with(['product.merchant'])
                ->byMember($memberId)
                ->active()
                ->get();

            // Group by merchant
            $merchantGroups = $cartItems->groupBy(function($item) {
                return $item->product->merchant_id ?? 'no_merchant';
            });

            $summary = [];
            
            foreach ($merchantGroups as $merchantId => $items) {
                $merchantData = null;
                
                if ($merchantId !== 'no_merchant' && $items->first()->product->merchant) {
                    $merchant = $items->first()->product->merchant;
                    $merchantData = [
                        'merchant_id' => $merchant->id,
                        'merchant_name' => $merchant->business_name,
                        'merchant_logo' => $merchant->business_logo,
                    ];
                }

                $totalAmount = $items->sum(function($item) {
                    return $item->getSubtotal();
                });

                $summary[] = [
                    'merchant' => $merchantData ?? ['merchant_name' => 'No Merchant'],
                    'item_count' => $items->count(),
                    'total_amount' => $totalAmount,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'merchant_summary' => $summary,
                    'total_items' => $cartItems->count(),
                    'total_amount' => $cartItems->sum(function($item) {
                        return $item->getSubtotal();
                    }),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cart summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}