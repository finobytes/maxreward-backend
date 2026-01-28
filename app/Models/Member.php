<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class Member extends Authenticatable implements JWTSubject
{
    use HasFactory, SoftDeletes, HasRoles;

    protected $table = 'members';

    /**
     * The guard name for Spatie permissions
     */
    protected $guard_name = 'member';

    protected $fillable = [
        'user_name',
        'name',
        'phone',
        'email',
        'password',
        'address',
        'member_type',
        'gender_type',
        'status',
        'company_id',        // NEW: for company logo inheritance
        'merchant_id',       // MODIFIED: can be used for both corporate members and general members under merchants
        'member_created_by',
        'referral_code',
        'suspended_reason',
        'block_reason',
        'image',
        'image_cloudinary_id',
        'country_id',
        'country_code',
        'suspended_by',
        'blocked_by',
        'last_status_changed_at',
        'referred_by',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'member_type' => 'string',
        'gender_type' => 'string',
        'status' => 'string',
        'member_created_by' => 'string',
        'last_status_changed_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'last_status_changed_at' => $this->last_status_changed_at ? $this->last_status_changed_at->timestamp : null,
        ];
    }

    /**
     * Get the company that this member belongs to (for branding/logo)
     */
    public function company()
    {
        return $this->belongsTo(CompanyInfo::class, 'company_id');
    }

    /**
     * Get the merchant that this member belongs to (for corporate members or members under merchants)
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

    /** 
     * Get sponsored member info
     */
    public function sponsoredMemberInfo()
    {
        return $this->hasOne(Referral::class, 'child_member_id');
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'member_id');
    }

    /**
     * Get the admin/user who suspended this merchant
     */
    public function suspendedBy()
    {
        return $this->belongsTo(Admin::class, 'suspended_by');
    }

    /**
     * Get the admin/user who blocked this member
     */
    public function blockedBy()
    {
        return $this->belongsTo(Admin::class, 'blocked_by');
    }

    /**
     * Get the member who referred this member
     */
    public function referrer()
    {
        return $this->belongsTo(Member::class, 'referred_by');
    }

    /**
     * Get all members referred by this member
     */
    public function referredMembers()
    {
        return $this->hasMany(Member::class, 'referred_by');
    }

    /**
     * NEW: Get the display logo for dashboard (gift card style)
     * Priority: Merchant Logo > Company Logo
     */
    public function getDisplayLogoAttribute()
    {
        // If member has merchant_id, show merchant logo
        if ($this->merchant_id && $this->merchant) {
            return [
                'type' => 'merchant',
                'logo_url' => $this->merchant->business_logo,
                'logo_id' => $this->merchant->logo_cloudinary_id,
                'name' => $this->merchant->business_name,
                'merchant_id' => $this->merchant_id,
            ];
        }

        // If member has company_id, show company logo
        if ($this->company_id && $this->company) {
            return [
                'type' => 'company',
                'logo_url' => $this->company->logo,
                'logo_id' => $this->company->logo_cloudinary_id,
                'name' => $this->company->name,
                'company_id' => $this->company_id,
            ];
        }

        // Default: No logo
        return null;
    }

    /**
     * NEW: Get the branding info for member card/dashboard
     */
    public function getBrandingInfoAttribute()
    {
        $displayLogo = $this->display_logo;
        
        if (!$displayLogo) {
            return null;
        }

        return [
            'logo_url' => $displayLogo['logo_url'],
            'brand_name' => $displayLogo['name'],
            'brand_type' => $displayLogo['type'], // 'merchant' or 'company'
        ];
    }

    /**
     * NEW: Check if member has any branding (company or merchant)
     */
    public function hasBranding()
    {
        return $this->company_id || $this->merchant_id;
    }

    /**
     * NEW: Inherit branding from referrer
     * This should be called when creating a new member
     */
    public function inheritBrandingFromReferrer($referrer)
    {
        if (!$referrer) {
            return;
        }

        // If referrer is corporate member with merchant
        if ($referrer->member_type === 'corporate' && $referrer->merchant_id) {
            $this->merchant_id = $referrer->merchant_id;
            $this->company_id = null; // Clear company_id if merchant is set
        }
        // If referrer has merchant_id (was referred by corporate member)
        elseif ($referrer->merchant_id) {
            $this->merchant_id = $referrer->merchant_id;
            $this->company_id = null;
        }
        // If referrer has company_id only
        elseif ($referrer->company_id) {
            $this->company_id = $referrer->company_id;
            $this->merchant_id = null;
        }

        $this->save();
    }
}