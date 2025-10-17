<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class CpLevelConfig extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'cp_level_config';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'level_from',
        'level_to',
        'cp_percentage_per_level',
        'total_percentage_for_range',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'level_from' => 'integer',
        'level_to' => 'integer',
        'cp_percentage_per_level' => 'decimal:2',
        'total_percentage_for_range' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get CP percentage for a specific level
     * 
     * @param int $level Level number (1-30)
     * @return float CP percentage for this level
     */
    public static function getCpPercentageForLevel($level)
    {
        $config = self::where('level_from', '<=', $level)
            ->where('level_to', '>=', $level)
            ->first();
        
        return $config ? (float) $config->cp_percentage_per_level : 0;
    }

    /**
     * Get all CP percentages for levels 1-30
     * 
     * @return array Array of level => percentage
     */
    public static function getAllLevelPercentages()
    {
        $percentages = [];
        
        for ($level = 1; $level <= 30; $level++) {
            $percentages[$level] = self::getCpPercentageForLevel($level);
        }
        
        return $percentages;
    }

    /**
     * Get CP distribution breakdown
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getDistributionBreakdown()
    {
        return self::select('level_from', 'level_to', 'cp_percentage_per_level', 'total_percentage_for_range')
            ->orderBy('level_from')
            ->get();
    }

    /**
     * Verify total percentage equals 50%
     * 
     * @return array Verification result
     */
    public static function verifyTotalPercentage()
    {
        $total = self::sum('total_percentage_for_range');
        
        return [
            'total' => (float) $total,
            'is_valid' => round($total, 2) == 50.00,
            'expected' => 50.00,
            'difference' => round(50.00 - $total, 2)
        ];
    }

    /**
     * Get config for specific level range
     * 
     * @param int $levelFrom Starting level
     * @param int $levelTo Ending level
     * @return CpLevelConfig|null
     */
    public static function getConfigForRange($levelFrom, $levelTo)
    {
        return self::where('level_from', $levelFrom)
            ->where('level_to', $levelTo)
            ->first();
    }

    /**
     * Calculate CP amount for each level based on total CP pool
     * 
     * @param float $totalCpPool Total CP to distribute
     * @return array Distribution breakdown
     */
    public static function calculateCpDistribution($totalCpPool)
    {
        $distribution = [];
        
        for ($level = 1; $level <= 30; $level++) {
            $percentage = self::getCpPercentageForLevel($level);
            $amount = ($totalCpPool * $percentage) / 100;
            
            $distribution[$level] = [
                'level' => $level,
                'percentage' => $percentage,
                'amount' => round($amount, 2)
            ];
        }
        
        return $distribution;
    }

    /**
     * Get levels in a specific range
     * 
     * @param int $levelFrom Starting level
     * @param int $levelTo Ending level
     * @return array
     */
    public static function getLevelsInRange($levelFrom, $levelTo)
    {
        $levels = [];
        
        for ($level = $levelFrom; $level <= $levelTo; $level++) {
            $levels[] = [
                'level' => $level,
                'percentage' => self::getCpPercentageForLevel($level)
            ];
        }
        
        return $levels;
    }

    /**
     * Get total CP percentage for a range of levels
     * 
     * @param int $levelFrom Starting level
     * @param int $levelTo Ending level
     * @return float Total percentage
     */
    public static function getTotalPercentageForRange($levelFrom, $levelTo)
    {
        $total = 0;
        
        for ($level = $levelFrom; $level <= $levelTo; $level++) {
            $total += self::getCpPercentageForLevel($level);
        }
        
        return round($total, 2);
    }

    /**
     * Get distribution summary
     * 
     * @return array Summary of distribution
     */
    public static function getDistributionSummary()
    {
        $breakdown = self::getDistributionBreakdown();
        
        return $breakdown->map(function($config) {
            return [
                'range' => "Level {$config->level_from}-{$config->level_to}",
                'per_level' => (float) $config->cp_percentage_per_level . '%',
                'total' => (float) $config->total_percentage_for_range . '%',
                'level_count' => ($config->level_to - $config->level_from + 1),
            ];
        })->toArray();
    }

    /**
     * Get CP amount for a specific level from a pool
     * 
     * @param int $level Level number
     * @param float $totalCpPool Total CP pool
     * @return float CP amount for this level
     */
    public static function getCpAmountForLevel($level, $totalCpPool)
    {
        $percentage = self::getCpPercentageForLevel($level);
        return round(($totalCpPool * $percentage) / 100, 2);
    }

    /**
     * Check if a level exists in configuration
     * 
     * @param int $level Level to check
     * @return bool
     */
    public static function levelExists($level)
    {
        return $level >= 1 && $level <= 30;
    }

    /**
     * Get all configured level ranges
     * 
     * @return array
     */
    public static function getAllRanges()
    {
        return self::select('level_from', 'level_to')
            ->orderBy('level_from')
            ->get()
            ->map(function($config) {
                return [
                    'from' => $config->level_from,
                    'to' => $config->level_to,
                    'count' => $config->level_to - $config->level_from + 1
                ];
            })
            ->toArray();
    }
}
