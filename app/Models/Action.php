<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Action extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'actions';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'status',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all active actions
     */
    public static function getActive()
    {
        return self::where('status', true)
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Scope to filter active actions
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
