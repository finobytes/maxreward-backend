<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariationAttribute extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'product_variation_attributes';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'product_variation_id',
        'attribute_id',
        'attribute_item_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'product_variation_id' => 'integer',
        'attribute_id' => 'integer',
        'attribute_item_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the product variation that owns the attribute.
     */
    public function productVariation()
    {
        return $this->belongsTo(ProductVariation::class);
    }

    /**
     * Get the attribute.
     */
    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * Get the attribute item.
     */
    public function attributeItem()
    {
        return $this->belongsTo(AttributeItem::class);
    }
}
