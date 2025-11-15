<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Country extends Model
{
    use HasFactory;

    protected $table = 'countries';

    protected $fillable = [
        'country_code',
        'country',
        'region',
    ];

    protected $casts = [
        'country_code' => 'string',
        'country' => 'string',
        'region' => 'string',
    ];
}
