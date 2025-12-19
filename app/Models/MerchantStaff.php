<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

class MerchantStaff extends Authenticatable implements JWTSubject
{
    use HasFactory, SoftDeletes, HasRoles;

    protected $table = 'merchant_staffs';

    /**
     * The guard name for Spatie permissions
     */
    protected $guard_name = 'merchant';

    protected $fillable = [
        'merchant_id',
        'user_name',
        'name',
        'phone',
        'email',
        'password',
        'address',
        'type',
        'status',
        'gender_type',
        'designation',
        'image',
        'image_cloudinary_id',
        'country_id',
        'country_code'
    ];

    protected $hidden = [
        'password',
    ];

      /**
     * Get the merchant that this staff belongs to.
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

}