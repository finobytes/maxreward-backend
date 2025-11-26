<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherCounter extends Model
{
    protected $table = 'voucher_counters';
    protected $fillable = ['prefix'];
}
