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

    // Relationship
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}