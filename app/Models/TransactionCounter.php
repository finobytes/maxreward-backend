<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionCounter extends Model
{
    protected $table = 'transaction_counters';
    protected $fillable = ['prefix'];
}
