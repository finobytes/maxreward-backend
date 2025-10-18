<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CpUnlockHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'cp_unlock_history';

    /**
     * Only created_at, no updated_at for history records
     */
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'member_id',
        'previous_referrals',
        'new_referrals',
        'previous_unlocked_level',
        'new_unlocked_level',
        'released_cp_amount',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'member_id' => 'integer',
        'previous_referrals' => 'integer',
        'new_referrals' => 'integer',
        'previous_unlocked_level' => 'integer',
        'new_unlocked_level' => 'integer',
        'released_cp_amount' => 'double',
        'created_at' => 'datetime',
    ];

    /**
     * Get the member
     */
    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * Create unlock history record
     * 
     * @param array $data Unlock data
     * @return CpUnlockHistory
     */
    public static function createUnlockRecord($data)
    {
        return self::create([
            'member_id' => $data['member_id'],
            'previous_referrals' => $data['previous_referrals'],
            'new_referrals' => $data['new_referrals'],
            'previous_unlocked_level' => $data['previous_unlocked_level'],
            'new_unlocked_level' => $data['new_unlocked_level'],
            'released_cp_amount' => $data['released_cp_amount'] ?? 0,
        ]);
    }

    /**
     * Get unlock history for a member
     * 
     * @param int $memberId Member ID
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getMemberHistory($memberId)
    {
        return self::where('member_id', $memberId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get latest unlock for a member
     * 
     * @param int $memberId Member ID
     * @return CpUnlockHistory|null
     */
    public static function getLatestUnlock($memberId)
    {
        return self::where('member_id', $memberId)
            ->latest('created_at')
            ->first();
    }

    /**
     * Get total CP released for a member
     * 
     * @param int $memberId Member ID
     * @return float Total released CP
     */
    public static function getTotalReleasedCp($memberId)
    {
        return self::where('member_id', $memberId)
            ->sum('released_cp_amount');
    }

    /**
     * Scope to get recent unlocks
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $days Number of days
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to get unlocks by member
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $memberId Member ID
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByMember($query, $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Scope to get unlocks by level
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $level New unlocked level
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('new_unlocked_level', $level);
    }

    /**
     * Get unlock statistics
     * 
     * @return array Statistics
     */
    public static function getUnlockStatistics()
    {
        return [
            'total_unlocks' => self::count(),
            'total_released_cp' => self::sum('released_cp_amount'),
            'recent_unlocks_7_days' => self::recent(7)->count(),
            'recent_unlocks_30_days' => self::recent(30)->count(),
            'average_released_cp' => self::avg('released_cp_amount'),
            'max_released_cp' => self::max('released_cp_amount'),
        ];
    }

    /**
     * Get members who recently unlocked levels
     * 
     * @param int $days Number of days
     * @return \Illuminate\Support\Collection
     */
    public static function getRecentUnlockMembers($days = 7)
    {
        return self::with('member')
            ->recent($days)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($unlock) {
                return [
                    'member_id' => $unlock->member_id,
                    'member_name' => $unlock->member->name ?? 'N/A',
                    'previous_level' => $unlock->previous_unlocked_level,
                    'new_level' => $unlock->new_unlocked_level,
                    'released_cp' => $unlock->released_cp_amount,
                    'unlocked_at' => $unlock->created_at,
                ];
            });
    }

    /**
     * Check if member has unlocked any levels
     * 
     * @param int $memberId Member ID
     * @return bool
     */
    public static function hasUnlockedLevels($memberId)
    {
        return self::where('member_id', $memberId)->exists();
    }

    /**
     * Get unlock progression for a member
     * 
     * @param int $memberId Member ID
     * @return \Illuminate\Support\Collection
     */
    public static function getUnlockProgression($memberId)
    {
        return self::where('member_id', $memberId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function($unlock) {
                return [
                    'date' => $unlock->created_at->format('Y-m-d H:i:s'),
                    'referrals' => $unlock->new_referrals,
                    'level' => $unlock->new_unlocked_level,
                    'released_cp' => $unlock->released_cp_amount,
                ];
            });
    }

    /**
     * Get count of unlocks by level
     * 
     * @return array Level => count mapping
     */
    public static function getUnlockCountByLevel()
    {
        return self::selectRaw('new_unlocked_level, COUNT(*) as unlock_count')
            ->groupBy('new_unlocked_level')
            ->orderBy('new_unlocked_level')
            ->get()
            ->mapWithKeys(function($item) {
                return [$item->new_unlocked_level => $item->unlock_count];
            })
            ->toArray();
    }

    /**
     * Get members at specific unlocked level (their current level)
     * 
     * @param int $level Target level
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getMembersAtLevel($level)
    {
        return self::with('member')
            ->where('new_unlocked_level', $level)
            ->whereIn('id', function($query) {
                $query->selectRaw('MAX(id)')
                    ->from('cp_unlock_history')
                    ->groupBy('member_id');
            })
            ->get();
    }

    /**
     * Get unlock timeline (all unlocks in chronological order)
     * 
     * @param int $limit Number of records
     * @return \Illuminate\Support\Collection
     */
    public static function getUnlockTimeline($limit = 50)
    {
        return self::with('member')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($unlock) {
                return [
                    'member_name' => $unlock->member->name ?? 'N/A',
                    'from_level' => $unlock->previous_unlocked_level,
                    'to_level' => $unlock->new_unlocked_level,
                    'referrals' => $unlock->new_referrals,
                    'released_cp' => $unlock->released_cp_amount,
                    'date' => $unlock->created_at,
                ];
            });
    }

    /**
     * Get members who achieved full unlock (level 30)
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getFullyUnlockedMembers()
    {
        return self::with('member')
            ->where('new_unlocked_level', 30)
            ->whereIn('id', function($query) {
                $query->selectRaw('MAX(id)')
                    ->from('cp_unlock_history')
                    ->groupBy('member_id');
            })
            ->get();
    }

    /**
     * Get unlock distribution by level
     * 
     * @return \Illuminate\Support\Collection
     */
    public static function getUnlockDistribution()
    {
        return self::selectRaw('
                new_unlocked_level as level,
                COUNT(DISTINCT member_id) as unique_members,
                COUNT(*) as total_unlocks,
                AVG(released_cp_amount) as avg_released_cp,
                SUM(released_cp_amount) as total_released_cp
            ')
            ->groupBy('new_unlocked_level')
            ->orderBy('new_unlocked_level')
            ->get();
    }

    /**
     * Get top CP releases
     * 
     * @param int $limit Number of records
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getTopCpReleases($limit = 10)
    {
        return self::with('member')
            ->orderBy('released_cp_amount', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get unlock summary by referral count
     * 
     * @return \Illuminate\Support\Collection
     */
    public static function getUnlocksByReferralCount()
    {
        return self::selectRaw('
                new_referrals,
                COUNT(*) as unlock_count,
                AVG(released_cp_amount) as avg_released_cp
            ')
            ->groupBy('new_referrals')
            ->orderBy('new_referrals')
            ->get();
    }

    /**
     * Get member's unlock statistics
     * 
     * @param int $memberId Member ID
     * @return array Statistics
     */
    public static function getMemberUnlockStats($memberId)
    {
        $history = self::where('member_id', $memberId)->get();
        
        if ($history->isEmpty()) {
            return [
                'total_unlocks' => 0,
                'current_level' => 5, // Default starting level
                'total_released_cp' => 0,
                'last_unlock_date' => null,
            ];
        }

        $latest = $history->sortByDesc('created_at')->first();

        return [
            'total_unlocks' => $history->count(),
            'current_level' => $latest->new_unlocked_level,
            'total_released_cp' => $history->sum('released_cp_amount'),
            'last_unlock_date' => $latest->created_at,
            'first_unlock_date' => $history->sortBy('created_at')->first()->created_at,
        ];
    }

    /**
     * Check if member can unlock next level
     * 
     * @param int $memberId Member ID
     * @param int $currentReferrals Current referral count
     * @return array Can unlock status
     */
    public static function canUnlockNextLevel($memberId, $currentReferrals)
    {
        $latest = self::getLatestUnlock($memberId);
        $currentLevel = $latest ? $latest->new_unlocked_level : 5;

        // Unlock logic: 0→5, 1→10, 2→15, 3→20, 4→25, 5+→30
        $unlockMap = [
            0 => 5,
            1 => 10,
            2 => 15,
            3 => 20,
            4 => 25,
            5 => 30,
        ];

        $nextLevel = $unlockMap[$currentReferrals] ?? 30;
        
        return [
            'can_unlock' => $nextLevel > $currentLevel,
            'current_level' => $currentLevel,
            'next_level' => $nextLevel,
            'referrals_needed' => max(0, $currentReferrals),
        ];
    }
}
