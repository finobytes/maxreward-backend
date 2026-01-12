<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Helpers\CloudinaryHelper;
use Illuminate\Support\Facades\Log;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'products';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'merchant_id',
        'category_id',
        'subcategory_id',
        'brand_id',
        'model_id',
        'gender_id',
        'name',
        'slug',
        'sku_short_code',
        'regular_price',
        'regular_point',
        'sale_price',
        'sale_point',
        'cost_price',
        'unit_weight',
        'short_description',
        'description',
        'images',
        'type',
        'status',
        'deleted_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'merchant_id' => 'integer',
        'category_id' => 'integer',
        'subcategory_id' => 'integer',
        'brand_id' => 'integer',
        'model_id' => 'integer',
        'gender_id' => 'integer',
        'regular_price' => 'decimal:2',
        'regular_point' => 'float',
        'sale_price' => 'decimal:2',
        'sale_point' => 'float',
        'cost_price' => 'decimal:2',
        'unit_weight' => 'decimal:2',
        'images' => 'array',
        'deleted_by' => 'integer',
        'updated_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });

        static::updating(function ($product) {
            if ($product->isDirty('name') && empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });

        static::deleting(function ($product) {
            // Delete variations and their images/attributes
            foreach ($product->variations as $variation) {
                // Delete variation images
                if (is_array($variation->images)) {
                    foreach ($variation->images as $image) {
                        try {
                            CloudinaryHelper::deleteImage($image['public_id']);
                        } catch (\Exception $e) {
                            \Log::error("Cloudinary delete failed: {$image['public_id']}");
                        }
                    }
                }
                
                // Attributes will be deleted by cascade
                // But we explicitly delete for clarity
                $variation->variationAttributes()->delete();
                $variation->delete();
            }

            // Delete product images
            if (is_array($product->images)) {
                foreach ($product->images as $image) {
                    try {
                        CloudinaryHelper::deleteImage($image['public_id']);
                    } catch (\Exception $e) {
                        \Log::error("Cloudinary delete failed: {$image['public_id']}");
                    }
                }
            }
        });
    }


    /**
     * Get the merchant
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }


    /**
     * Get the category that the product belongs to
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Get the sub-category that the product belongs to
     */
    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class, 'subcategory_id');
    }

    /**
     * Get the brand that the product belongs to
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * Get the model that the product belongs to
     */
    public function model()
    {
        return $this->belongsTo(ProductModel::class, 'model_id');
    }

    /**
     * Get the gender that the product belongs to
     */
    public function gender()
    {
        return $this->belongsTo(Gender::class, 'gender_id');
    }

    /**
     * Get the product variations for the product
     */
    public function variations()
    {
        return $this->hasMany(ProductVariation::class, 'product_id');
    }

    /**
     * Scope to filter active products
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter by product type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get product by slug
     */
    public static function getBySlug($slug)
    {
        return self::where('slug', $slug)->first();
    }

    /**
     * Check if product has sale price
     */
    public function hasSalePrice()
    {
        return !is_null($this->sale_price) || !is_null($this->sale_point);
    }

    /**
     * Check if product is variable type
     */
    public function isVariable()
    {
        return $this->type === 'variable';
    }

    /**
     * Check if product is simple type
     */
    public function isSimple()
    {
        return $this->type === 'simple';
    }

    /**
     * Get grouped attributes for the product variation
     */
    public function getGroupedAttributesAttribute()
    {
        if ($this->type !== 'variable' || $this->variations->isEmpty()) {
            return [];
        }

        $groupedAttributes = [];
        
        foreach ($this->variations as $variation) {
            foreach ($variation->variationAttributes as $varAttr) {
                $attrId = $varAttr->attribute_id;
                
                // Initialize attribute group if not exists
                if (!isset($groupedAttributes[$attrId])) {
                    $groupedAttributes[$attrId] = [
                        'attribute_id' => $attrId,
                        'attribute_name' => $varAttr->attribute->name,
                        'items' => []
                    ];
                }
                
                // Add item if not already added
                $itemId = $varAttr->attribute_item_id;
                if (!isset($groupedAttributes[$attrId]['items'][$itemId])) {
                    $groupedAttributes[$attrId]['items'][$itemId] = [
                        'item_id' => $itemId,
                        'item_name' => $varAttr->attributeItem->name,
                    ];
                }
            }
        }

        // Convert to array and reset keys
        return array_values(array_map(function($attr) {
            $attr['items'] = array_values($attr['items']);
            return $attr;
        }, $groupedAttributes));
    }


    /**
     * Get cart items
     */
    public function cartItems()
    {
        return $this->hasMany(Cart::class, 'product_id');
    }
    

}
