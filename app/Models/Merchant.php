<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Merchant extends Model
{
    protected $fillable = [
        'business_name',
        'business_type',
        'business_description',
        'company_address',
        'status',
        'license_number',
        'business_logo',
        'logo_cloudinary_id',
        'docs_url',
        'docs_cloudinary_id',
        'bank_name',
        'account_holder_name',
        'account_number',
        'preferred_payment_method',
        'routing_number',
        'swift_code',
        'owner_name',
        'phone',
        'gender',
        'address',
        'email',
        'tax_certificate',
        'commission_rate',
        'settlement_period',
        'approved_by',
        'state',
        'country',
        'products_services',
        'corporate_member_id',
        'verification_documents',
        'verification_docs_url',
        'verification_cloudinary_id',
    ];

    protected $casts = [
        'status' => 'string',
        'gender' => 'string',
        'commission_rate' => 'decimal:2'
    ];

    /**
     * Get all staff members for this merchant
     */
    public function staffs()
    {
        return $this->hasMany(MerchantStaff::class, 'merchant_id');
    }

    /**
     * Get the corporate member account for this merchant
     */
    public function corporateMember()
    {
        return $this->belongsTo(Member::class, 'corporate_member_id');
    }

    /**
     * Get the wallet for this merchant
     */
    public function wallet()
    {
        return $this->hasOne(MerchantWallet::class, 'merchant_id');
    }
}
