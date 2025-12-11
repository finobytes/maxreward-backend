<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Gender extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'genders';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($gender) {
            if (empty($gender->slug)) {
                $gender->slug = Str::slug($gender->name);
            }
        });

        static::updating(function ($gender) {
            if ($gender->isDirty('name') && empty($gender->slug)) {
                $gender->slug = Str::slug($gender->name);
            }
        });
    }

    /**
     * Get all active genders
     */
    public static function getActive()
    {
        return self::where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Get gender by slug
     */
    public static function getBySlug($slug)
    {
        return self::where('slug', $slug)->first();
    }

    /**
     * Scope to filter active genders
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
