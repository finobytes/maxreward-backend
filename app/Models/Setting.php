<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'setting_attribute'
    ];

    protected $table = 'settings';


    protected $casts = [
        'setting_attribute' => 'array',
    ];
}
