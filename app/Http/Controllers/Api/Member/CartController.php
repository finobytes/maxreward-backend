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
     * Get member's cart
     * GET /api/member/cart
     */
    public function index()
    {
        try {
        
            $memberId = auth('member')->id();
    
            $cartItems = Cart::with(['product', 'productVariation'])
                ->byMember($memberId)
                ->active()
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        'product_image' => $item->product->images[0] ?? null,
                        'variation_id' => $item->product_variation_id,
                        'variation_details' => $item->productVariation ? [
                            'sku' => $item->productVariation->sku,
                            'attributes' => $item->productVariation->variationAttributes ?? null,
                        ] : null,
                        'quantity' => $item->quantity,
                        'price' => $item->getPrice(),
                        'subtotal' => $item->getSubtotal(),
                        'expire_date' => $item->expire_date,
                        'in_stock' => $item->checkStockAvailability(),
                    ];
                });

            $total = Cart::getMemberCartTotal($memberId);
            $totalItems = $cartItems->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'cart_items' => $cartItems,
                    'total_items' => $totalItems,
                    'total_amount' => $total,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cart items',
                'error' => $e->getMessage()
            ]);
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
                'product_variation_id' => 'nullable|exists:product_variations,id',
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
            $product = Product::find($request->product_id);
            
            if (!$product || $product->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not available'
                ], 404);
            }
    
            // If variation is specified, check it
            if ($request->product_variation_id) {
                $variation = ProductVariation::find($request->product_variation_id);
                
                if (!$variation || !$variation->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Product variation not available'
                    ], 404);
                }
    
                // Check stock
                if ($variation->actual_quantity < $request->quantity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock. Available: ' . $variation->actual_quantity
                    ], 400);
                }
            }
    
            // Add or update cart
            $cart = Cart::addOrUpdate([
                'member_id' => $memberId,
                'product_id' => $request->product_id,
                'product_variation_id' => $request->product_variation_id,
                'quantity' => $request->quantity,
            ]);
    
            // Get updated cart count
            $cartCount = Cart::getMemberCartCount($memberId);
    
            return response()->json([
                'success' => true,
                'message' => 'Product added to cart successfully',
                'data' => [
                    'cart_item' => $cart,
                    'cart_count' => $cartCount,
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add product to cart',
                'error' => $e->getMessage()
            ]);
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
            
            $cartItem = Cart::byMember($memberId)->find($id);
    
            if (!$cartItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found'
                ], 404);
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
                    'cart_item' => $cartItem,
                    'subtotal' => $cartItem->getSubtotal(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update cart',
                'error' => $e->getMessage()
            ]);
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
        
            $cartItem = Cart::byMember($memberId)->find($id);

            if (!$cartItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found'
                ], 404);
            }

            $cartItem->delete();

            // Get updated cart count
            $cartCount = Cart::getMemberCartCount($memberId);

            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart',
                'data' => [
                    'cart_count' => $cartCount,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item from cart',
                'error' => $e->getMessage()
            ]);
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
        
            Cart::clearMemberCart($memberId);
    
            return response()->json([
                'success' => true,
                'message' => 'Cart cleared successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cart',
                'error' => $e->getMessage()
            ]);
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
            ]);
        }
        
    }
}
