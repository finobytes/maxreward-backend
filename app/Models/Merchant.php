<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Merchant extends Model
{
    use SoftDeletes;

    protected $table = 'merchants';
    
    protected $fillable = [
        'business_name',
        'business_type_id',
        'business_description',
        'company_address',
        'status',
        'license_number',
        'unique_number',
        'bank_name',
        'account_holder_name',
        'account_number',
        'preferred_payment_method',
        'routing_number',
        'owner_name',
        'phone',
        'gender',
        'address',
        'email',
        'reward_budget',
        'annual_sales_turnover',
        'state',
        'country',
        'products_services',
        'merchant_created_by',
        'business_logo',
        'logo_cloudinary_id',
        'docs_url',
        'docs_cloudinary_id',
        'swift_code',
        'tax_certificate',
        'approved_by',
        'suspended_by',
        'designation',
        'suspended_reason',
        'rejected_by',
        'rejected_reason',
        'corporate_member_id',
        'verification_documents',
        'verification_docs_url',
        'verification_cloudinary_id',
        'iamge',
        'image_cloudinary_id',
        'authorized_person_name',
        'country_id',
        'country_code',
        'town'
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

    /**
     * Get the admin/user who suspended this merchant
     */
    public function suspendedBy()
    {
        return $this->belongsTo(Admin::class, 'suspended_by');
    }

    /**
     * Get the admin/user who rejected this merchant
     */
    public function rejectedBy()
    {
        return $this->belongsTo(Admin::class, 'rejected_by');
    }
}
