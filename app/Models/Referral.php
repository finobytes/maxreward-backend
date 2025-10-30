<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    use HasFactory;

    protected $table = 'referrals';

    protected $fillable = [
        'parent_member_id',
        // 'merchant_id',
        'child_member_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the parent member (referrer)
     */
    public function parentMember()
    {
        return $this->belongsTo(Member::class, 'parent_member_id');
    }

    /**
     * Get the child member (referred member)
     */
    public function childMember()
    {
        return $this->belongsTo(Member::class, 'child_member_id');
    }

    /**
     * Get the merchant who referred (if applicable)
     */
    // public function merchant()
    // {
    //     return $this->belongsTo(Merchant::class, 'merchant_id');
    // }

    /**
     * Scope to get referrals by parent member
     */
    public function scopeByParent($query, $memberId)
    {
        return $query->where('parent_member_id', $memberId);
    }

    /**
     * Scope to get referrals by child member
     */
    public function scopeByChild($query, $memberId)
    {
        return $query->where('child_member_id', $memberId);
    }

    /**
     * Scope to get referrals by merchant
     */
    // public function scopeByMerchant($query, $merchantId)
    // {
    //     return $query->where('merchant_id', $merchantId);
    // }

    /**
     * Get all descendants (referral tree) up to 30 levels
     */
    public static function getReferralTree($memberId, $maxLevel = 30)
    {
        $tree = [];
        $currentLevel = [$memberId];
        
        for ($level = 1; $level <= $maxLevel; $level++) {
            $nextLevel = self::whereIn('parent_member_id', $currentLevel)
                ->pluck('child_member_id')
                ->toArray();
            
            if (empty($nextLevel)) {
                break;
            }
            
            $tree[$level] = $nextLevel;
            $currentLevel = $nextLevel;
        }
        
        return $tree;
    }

    /**
     * Count direct referrals for a member
     */
    public static function countDirectReferrals($memberId)
    {
        return self::where('parent_member_id', $memberId)->count();
    }

    /**
     * Get referral path from child to root (upline path)
     */
    public static function getReferralPath($memberId, $maxLevels = 30)
    {
        $path = [];
        $currentMemberId = $memberId;
        
        for ($i = 0; $i < $maxLevels; $i++) {
            $referral = self::where('child_member_id', $currentMemberId)->first();
            
            if (!$referral || !$referral->parent_member_id) {
                break;
            }
            
            $path[] = [
                'member_id' => $referral->parent_member_id,
                'level' => $i + 1,
                'referral_id' => $referral->id
            ];
            
            $currentMemberId = $referral->parent_member_id;
        }
        
        return $path;
    }

    /**
     * Get direct children (level 1 referrals)
     */
    public static function getDirectReferrals($memberId)
    {
        return self::with('childMember')
            ->where('parent_member_id', $memberId)
            ->get();
    }

    /**
     * Get total referrals count (all levels)
     */
    public static function getTotalReferralsCount($memberId)
    {
        $tree = self::getReferralTree($memberId);
        $total = 0;
        
        foreach ($tree as $level => $members) {
            $total += count($members);
        }
        
        return $total;
    }

    /**
     * Check if member A referred member B (directly or indirectly)
     */
    public static function isInDownline($parentMemberId, $childMemberId)
    {
        $tree = self::getReferralTree($parentMemberId);
        
        foreach ($tree as $level => $members) {
            if (in_array($childMemberId, $members)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get referral statistics for a member
     */
    public static function getReferralStatistics($memberId)
    {
        $tree = self::getReferralTree($memberId);
        
        $stats = [
            'direct_referrals' => self::countDirectReferrals($memberId),
            'total_referrals' => 0,
            'deepest_level' => 0,
            'by_level' => []
        ];
        
        foreach ($tree as $level => $members) {
            $count = count($members);
            $stats['total_referrals'] += $count;
            $stats['deepest_level'] = $level;
            $stats['by_level'][$level] = $count;
        }
        
        return $stats;
    }
}
