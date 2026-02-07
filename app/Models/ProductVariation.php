<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariation extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'product_variations';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'product_id',
        'sku',
        'regular_price',
        'regular_point',
        'sale_price',
        'sale_point',
        'cost_price',
        'actual_quantity',
        'low_stock_threshold',
        'ean_no',
        'unit_weight',
        'images',
        'is_active',
        'deleted_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'product_id' => 'integer',
        'regular_price' => 'decimal:2',
        'regular_point' => 'float',
        'sale_price' => 'decimal:2',
        'sale_point' => 'float',
        'cost_price' => 'decimal:2',
        'actual_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'unit_weight' => 'decimal:2',
        'images' => 'array',
        'is_active' => 'boolean',
        'deleted_by' => 'integer',
        'updated_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the product that the variation belongs to
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope to filter active variations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter low stock variations
     */
    public function scopeLowStock($query)
    {
        return $query->whereColumn('actual_quantity', '<=', 'low_stock_threshold');
    }

    /**
     * Scope to filter out of stock variations
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('actual_quantity', '<=', 0);
    }

    /**
     * Scope to filter in stock variations
     */
    public function scopeInStock($query)
    {
        return $query->where('actual_quantity', '>', 0);
    }

    /**
     * Check if variation has sale price
     */
    public function hasSalePrice()
    {
        return !is_null($this->sale_price) || !is_null($this->sale_point);
    }

    /**
     * Check if variation is low on stock
     */
    public function isLowStock()
    {
        return $this->actual_quantity <= $this->low_stock_threshold;
    }

    /**
     * Check if variation is out of stock
     */
    public function isOutOfStock()
    {
        return $this->actual_quantity <= 0;
    }

    /**
     * Check if variation is in stock
     */
    public function isInStock()
    {
        return $this->actual_quantity > 0;
    }

    /**
     * Get the variation attributes
     */
    public function variationAttributes()
    {
        return $this->hasMany(ProductVariationAttribute::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($variation) {
            // Delete variation attributes (if not using cascade)
            $variation->variationAttributes()->delete();
        });
    }

    /**
     * Get variation name from attributes
     */
    public function getVariationNameAttribute()
    {
        // এখানে ইগার লোড ব্যবহার করুন
        $this->loadMissing(['variationAttributes.attribute', 'variationAttributes.attributeItem']);
        
        $nameParts = [];
        
        if ($this->variationAttributes) {
            foreach ($this->variationAttributes as $attr) {
                if ($attr->attribute && $attr->attributeItem) {
                    $nameParts[] = $attr->attribute->name . ': ' . $attr->attributeItem->name;
                }
            }
        }
        
        return empty($nameParts) ? 'No Attributes' : implode(', ', $nameParts);
    }
}
