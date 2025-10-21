<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Denomination extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'denominations';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'value',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'value' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get vouchers with this denomination
     */
    public function vouchers()
    {
        return $this->hasMany(Voucher::class, 'denomination_id');
    }

    /**
     * Get all available denominations
     */
    public static function getAvailable()
    {
        return self::orderBy('value', 'asc')->get();
    }

    /**
     * Get denomination by value
     */
    public static function getByValue($value)
    {
        return self::where('value', $value)->first();
    }

    /**
     * Format value as currency
     */
    public function getFormattedValueAttribute()
    {
        return 'RM ' . number_format($this->value, 2);
    }
}
