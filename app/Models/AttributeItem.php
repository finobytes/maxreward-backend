<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class AttributeItem extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'attribute_items';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'attribute_id',
        'name',
        'slug',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'attribute_id' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($attributeItem) {
            if (empty($attributeItem->slug)) {
                $attributeItem->slug = Str::slug($attributeItem->name);
            }
        });

        static::updating(function ($attributeItem) {
            if ($attributeItem->isDirty('name') && empty($attributeItem->slug)) {
                $attributeItem->slug = Str::slug($attributeItem->name);
            }
        });
    }

    /**
     * Get the attribute that owns the attribute item
     */
    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }

    /**
     * Get variation attributes
     */
    public function variationAttributes()
    {
        return $this->hasMany(ProductVariationAttribute::class, 'attribute_item_id');
    }

    /**
     * Get all active attribute items
     */
    public static function getActive()
    {
        return self::where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Get attribute item by slug and attribute_id
     */
    public static function getBySlugAndAttribute($slug, $attributeId)
    {
        return self::where('slug', $slug)
            ->where('attribute_id', $attributeId)
            ->first();
    }

    /**
     * Scope to filter active attribute items
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by attribute
     */
    public function scopeByAttribute($query, $attributeId)
    {
        return $query->where('attribute_id', $attributeId);
    }
}
