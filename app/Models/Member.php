<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;  // ⬅️ এটা পরিবর্তন করুন

class Member extends Authenticatable implements JWTSubject
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_name',
        'name',
        'phone',
        'email',
        'password',
        'member_type',
        'gender_type',
        'status',
        'merchant_id',
        'member_created_by',
        'referral_code',
        'image',
        'image_cloudinary_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'member_type' => 'string',
        'gender_type' => 'string',
        'status' => 'string',
        'member_created_by' => 'string',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

        /**
     * Get the merchant that this member belongs to (for corporate members)
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * Get the wallet for this member
     */
    public function wallet()
    {
        return $this->hasOne(MemberWallet::class, 'member_id');
    }
}