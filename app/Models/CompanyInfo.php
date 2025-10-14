<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompanyInfo extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'company_info';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'logo',
        'logo_cloudinary_id',
        'cr_points',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'cr_points' => 'double',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the company reserve points formatted
     */
    public function getFormattedCrPointAttribute()
    {
        return number_format($this->cr_points, 2);
    }

    /**
     * Increment company reserve points
     */
    public function incrementCrPoint($amount)
    {
        $this->increment('cr_points', $amount);
        return $this;
    }

    /**
     * Decrement company reserve points
     */
    public function decrementCrPoint($amount)
    {
        $this->decrement('cr_points', $amount);
        return $this;
    }

    /**
     * Check if company has sufficient CR points
     */
    public function hasSufficientCrPoints($amount)
    {
        return $this->cr_points >= $amount;
    }

    /**
     * Get company info (singleton pattern - only one record should exist)
     */
    public static function getCompany()
    {
        return self::first();
    }

    /**
     * Create or update company info
     */
    public static function updateCompanyInfo($data)
    {
        $company = self::first(); 
        
        if ($company) { 
            $company->update($data); 
        } else {
            $company = self::create($data);
        }
        
        return $company;
    }
}
