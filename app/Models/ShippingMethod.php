<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    protected $table = 'shipping_methods';

    protected $fillable = ['name', 'code', 'description', 'min_days', 'max_days', 'is_active', 'sort_order'];

    public function merchantRates()
    {
        return $this->hasMany(MerchantShippingRate::class, 'method_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
