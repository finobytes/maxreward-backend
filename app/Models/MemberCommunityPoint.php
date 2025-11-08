<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberCommunityPoint extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'member_community_points';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'member_id',
        'level',
        'total_cp',
        'available_cp',
        'onhold_cp',
        'is_locked',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'member_id' => 'integer',
        'level' => 'integer',
        'total_cp' => 'double',
        'available_cp' => 'double',
        'onhold_cp' => 'double',
        'is_locked' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the member
     */
    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * Add CP to a specific level
     * 
     * @param float $amount CP amount to add
     * @param bool $isLocked Whether CP should be locked
     * @return $this
     */
    public function addCp($amount, $isLocked = false)
    {
        $this->total_cp += $amount;
        
        if ($isLocked) {
            $this->onhold_cp += $amount;
        } else {
            $this->available_cp += $amount;
        }
        
        $this->save();
        
        return $this;
    }

    /**
     * Unlock CP for this level (move from onhold to available)
     * 
     * @return float Amount released
     */
    public function unlockCp()
    {
        if ($this->onhold_cp > 0) {
            $releasedAmount = $this->onhold_cp;
            $this->available_cp += $this->onhold_cp;
            $this->onhold_cp = 0;
            $this->is_locked = false;
            $this->save();
            
            return $releasedAmount;
        }
        
        return 0;
    }

    /**
     * Lock this level (mark as locked)
     * 
     * @return $this
     */
    public function lockLevel()
    {
        $this->is_locked = true;
        $this->save();
        
        return $this;
    }

    /**
     * Deduct CP from available balance
     * 
     * @param float $amount Amount to deduct
     * @return bool Success status
     */
    public function deductCp($amount)
    {
        if ($this->available_cp >= $amount) {
            $this->available_cp -= $amount;
            $this->save();
            
            return true;
        }
        
        return false;
    }

    /**
     * Get or create member CP record for a specific level
     * 
     * @param int $memberId Member ID
     * @param int $level Level (1-30)
     * @return MemberCommunityPoint
     */
    public static function getOrCreateForLevel($memberId, $level, $isLocked = false)
    {
        return self::firstOrCreate(
            [
                'member_id' => $memberId,
                'level' => $level
            ],
            [
                'total_cp' => 0,
                'available_cp' => 0,
                'onhold_cp' => 0,
                'is_locked' => $isLocked,
            ]
        );
    }

    /**
     * Scope to get locked levels
     */
    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }

    /**
     * Scope to get unlocked levels
     */
    public function scopeUnlocked($query)
    {
        return $query->where('is_locked', false);
    }

    /**
     * Scope to get levels with onhold CP
     */
    public function scopeHasOnholdCp($query)
    {
        return $query->where('onhold_cp', '>', 0);
    }

    /**
     * Scope to get by member
     */
    public function scopeByMember($query, $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Scope to get by level
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Get total CP for a member across all levels
     * 
     * @param int $memberId Member ID
     * @return float Total CP
     */
    public static function getTotalCpForMember($memberId)
    {
        return self::where('member_id', $memberId)->sum('total_cp');
    }

    /**
     * Get available CP for a member across all levels
     * 
     * @param int $memberId Member ID
     * @return float Available CP
     */
    public static function getAvailableCpForMember($memberId)
    {
        return self::where('member_id', $memberId)->sum('available_cp');
    }

    /**
     * Get onhold CP for a member across all levels
     * 
     * @param int $memberId Member ID
     * @return float Onhold CP
     */
    public static function getOnholdCpForMember($memberId)
    {
        return self::where('member_id', $memberId)->sum('onhold_cp');
    }

    /**
     * Get CP breakdown by level for a member
     * 
     * @param int $memberId Member ID
     * @return \Illuminate\Support\Collection
     */
    public static function getCpBreakdown($memberId)
    {
        return self::where('member_id', $memberId)
            ->orderBy('level')
            ->get()
            ->map(function($cp) {
                return [
                    'level' => $cp->level,
                    'total_cp' => $cp->total_cp,
                    'available_cp' => $cp->available_cp,
                    'onhold_cp' => $cp->onhold_cp,
                    'is_locked' => $cp->is_locked,
                ];
            });
    }

    /**
     * Unlock multiple levels for a member
     * 
     * @param int $memberId Member ID
     * @param int $fromLevel Starting level
     * @param int $toLevel Ending level
     * @return float Total amount released
     */
    public static function unlockLevels($memberId, $fromLevel, $toLevel)
    {
        $totalReleased = 0;
        
        for ($level = $fromLevel; $level <= $toLevel; $level++) {
            $mcp = self::where('member_id', $memberId)
                ->where('level', $level)
                ->first();
            
            if ($mcp) {
                $totalReleased += $mcp->unlockCp();
            }
        }
        
        return $totalReleased;
    }

    /**
     * Get locked levels for a member
     * 
     * @param int $memberId Member ID
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getLockedLevels($memberId)
    {
        return self::where('member_id', $memberId)
            ->where('is_locked', true)
            ->orderBy('level')
            ->get();
    }

    /**
     * Get unlocked levels for a member
     * 
     * @param int $memberId Member ID
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getUnlockedLevels($memberId)
    {
        return self::where('member_id', $memberId)
            ->where('is_locked', false)
            ->orderBy('level')
            ->get();
    }

    /**
     * Get CP summary for a member
     * 
     * @param int $memberId Member ID
     * @return array Summary data
     */
    public static function getCpSummary($memberId)
    {
        return [
            'total_cp' => self::getTotalCpForMember($memberId),
            'available_cp' => self::getAvailableCpForMember($memberId),
            'onhold_cp' => self::getOnholdCpForMember($memberId),
            'locked_levels_count' => self::where('member_id', $memberId)
                ->where('is_locked', true)
                ->count(),
            'unlocked_levels_count' => self::where('member_id', $memberId)
                ->where('is_locked', false)
                ->count(),
            'levels_with_cp' => self::where('member_id', $memberId)
                ->where('total_cp', '>', 0)
                ->count(),
        ];
    }

    /**
     * Get members with most CP at a specific level
     * 
     * @param int $level Level number
     * @param int $limit Number of results
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getTopEarnersAtLevel($level, $limit = 10)
    {
        return self::with('member')
            ->where('level', $level)
            ->orderBy('total_cp', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get total CP in the system across all members and levels
     * 
     * @return float Total CP
     */
    public static function getTotalSystemCp()
    {
        return self::sum('total_cp');
    }

    /**
     * Get total available CP in the system
     * 
     * @return float Total available CP
     */
    public static function getTotalSystemAvailableCp()
    {
        return self::sum('available_cp');
    }

    /**
     * Get total onhold CP in the system
     * 
     * @return float Total onhold CP
     */
    public static function getTotalSystemOnholdCp()
    {
        return self::sum('onhold_cp');
    }

    /**
     * Get CP statistics by level
     * 
     * @return array Statistics
     */
    public static function getCpStatisticsByLevel()
    {
        return self::selectRaw('
                level,
                COUNT(DISTINCT member_id) as member_count,
                SUM(total_cp) as total_cp,
                SUM(available_cp) as available_cp,
                SUM(onhold_cp) as onhold_cp,
                AVG(total_cp) as avg_cp_per_member
            ')
            ->groupBy('level')
            ->orderBy('level')
            ->get()
            ->toArray();
    }

    /**
     * Check if member has CP at specific level
     * 
     * @param int $memberId Member ID
     * @param int $level Level number
     * @return bool
     */
    public static function hasCpAtLevel($memberId, $level)
    {
        return self::where('member_id', $memberId)
            ->where('level', $level)
            ->where('total_cp', '>', 0)
            ->exists();
    }

    /**
     * Get all levels where member has earned CP
     * 
     * @param int $memberId Member ID
     * @return array Level numbers
     */
    public static function getLevelsWithCp($memberId)
    {
        return self::where('member_id', $memberId)
            ->where('total_cp', '>', 0)
            ->orderBy('level')
            ->pluck('level')
            ->toArray();
    }
}
