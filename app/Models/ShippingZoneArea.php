<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingZoneArea extends Model
{
    protected $fillable = ['zone_id', 'postcode_prefix', 'state', 'city'];

    public function zone()
    {
        return $this->belongsTo(ShippingZone::class, 'zone_id');
    }
}
