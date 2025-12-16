<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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
    }

    /**
     * Get the category that the product belongs to
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
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
        return $this->belongsTo(Brand::class);
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
        return $this->belongsTo(Gender::class);
    }

    /**
     * Get the product variations for the product
     */
    public function variations()
    {
        return $this->hasMany(ProductVariation::class);
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
}
