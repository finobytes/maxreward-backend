<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Cart extends Model
{
    use HasFactory;

    protected $table = 'carts';

    protected $fillable = [
        'member_id',
        'product_id',
        'product_variation_id',
        'quantity',
        'expire_date',
    ];

    protected $casts = [
        'member_id' => 'integer',
        'product_id' => 'integer',
        'product_variation_id' => 'integer',
        'quantity' => 'integer',
        'expire_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot method to set expire_date automatically
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($cart) {
            if (empty($cart->expire_date)) {
                $cart->expire_date = Carbon::now()->addDays(7);
            }
        });
    }

    /**
     * Get the member who owns this cart item
     */
    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the product variation (if any)
     */
    public function productVariation()
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }

    /**
     * Get the merchant through product
     */
    public function merchant()
    {
        return $this->hasOneThrough(
            Merchant::class,
            Product::class,
            'id', // Product model এর foreign key
            'id', // Merchant model এর foreign key
            'product_id', // Cart model এর local key
            'merchant_id' // Product model এর local key
        );
    }

    /**
     * Scope to get non-expired cart items
     */
    public function scopeActive($query)
    {
        return $query->where('expire_date', '>', Carbon::now());
    }

    /**
     * Scope to get expired cart items
     */
    public function scopeExpired($query)
    {
        return $query->where('expire_date', '<=', Carbon::now());
    }

    /**
     * Scope to get cart by member
     */
    public function scopeByMember($query, $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Check if cart item is expired
     */
    public function isExpired()
    {
        return Carbon::now()->greaterThan($this->expire_date);
    }

    /**
     * Get cart item price (product or variation)
     */
    public function getPrice()
    {
        if ($this->product_variation_id) {
            $variation = $this->productVariation;
            return $variation->sale_point ?? $variation->regular_point;
        }
        
        $product = $this->product;
        return $product->sale_point ?? $product->regular_point;
    }

    /**
     * Get cart item subtotal
     */
    public function getSubtotal()
    {
        return $this->getPrice() * $this->quantity;
    }

    /**
     * Add to existing quantity or create new
     */
    public static function addOrUpdate($data)
    {
        $cart = self::where('member_id', $data['member_id'])
            ->where('product_id', $data['product_id'])
            ->where('product_variation_id', $data['product_variation_id'] ?? null)
            ->first();

        if ($cart) {
            // Update existing
            $cart->quantity += $data['quantity'];
            $cart->expire_date = Carbon::now()->addDays(7); // Refresh expiry
            $cart->save();
        } else {
            // Create new
            $cart = self::create($data);
        }

        return $cart;
    }

    /**
     * Get member's cart with products
     */
    public static function getMemberCart($memberId)
    {
        return self::with(['product', 'productVariation'])
            ->byMember($memberId)
            ->active()
            ->get();
    }

    /**
     * Get cart total for member
     */
    public static function getMemberCartTotal($memberId)
    {
        $cartItems = self::getMemberCart($memberId);
        $total = 0;

        foreach ($cartItems as $item) {
            $total += $item->getSubtotal();
        }

        return $total;
    }

    /**
     * Get cart count for member
     */
    public static function getMemberCartCount($memberId)
    {
        return self::byMember($memberId)->active()->count();
    }

    /**
     * Clear member's cart
     */
    public static function clearMemberCart($memberId)
    {
        return self::byMember($memberId)->delete();
    }

    /**
     * Remove expired cart items (should run via cron)
     */
    public static function removeExpiredItems()
    {
        return self::expired()->delete();
    }

    /**
     * Check stock availability
     */
    public function checkStockAvailability()
    {
        if ($this->product_variation_id) {
            $variation = $this->productVariation;
            return $variation && $variation->actual_quantity >= $this->quantity;
        }
        
        // Simple products don't have stock tracking in your schema
        // return true;
    }
}
