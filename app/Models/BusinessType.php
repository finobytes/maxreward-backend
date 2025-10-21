<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessType extends Model
{
    protected $table = 'business_types';

    protected $fillable = ['name'];
}
