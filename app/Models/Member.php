<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
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

    // Relationship
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}