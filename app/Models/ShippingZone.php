<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    protected $fillable = ['name', 'zone_code', 'region', 'description', 'is_active'];

    public function areas()
    {
        return $this->hasMany(ShippingZoneArea::class, 'zone_id');
    }

    public function merchantRates()
    {
        return $this->hasMany(MerchantShippingRate::class, 'zone_id');
    }

    public static function detectZoneByPostcode($postcode)
    {
        $prefix = substr($postcode, 0, 2);
        
        $area = ShippingZoneArea::where('postcode_prefix', $prefix)->first();
        
        if ($area) {
            return $area->zone;
        }
        
        return null;
    }
}
