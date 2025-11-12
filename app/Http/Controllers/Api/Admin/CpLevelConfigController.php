<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CpLevelConfig;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CpLevelConfigController extends Controller
{

    // // ✅ UPDATED Default Seeder Insert CP level configuration data
    // $configs = [
    //     [
    //         'level_from' => 1,
    //         'level_to' => 3,
    //         'cp_percentage_per_level' => 5.00,
    //         'total_percentage_for_range' => 15.00,
    //     ],
    //     [
    //         'level_from' => 4,
    //         'level_to' => 6,
    //         'cp_percentage_per_level' => 15.00,
    //         'total_percentage_for_range' => 45.00,
    //     ],
    //     [
    //         'level_from' => 7,
    //         'level_to' => 9,
    //         'cp_percentage_per_level' => 8.00,
    //         'total_percentage_for_range' => 24.00,
    //     ],
    //     [
    //         'level_from' => 10,
    //         'level_to' => 20,
    //         'cp_percentage_per_level' => 1.00,
    //         'total_percentage_for_range' => 11.00,
    //     ],
    //     [
    //         'level_from' => 21,
    //         'level_to' => 30,
    //         'cp_percentage_per_level' => 0.50,
    //         'total_percentage_for_range' => 5.00,
    //     ],
    // ];
    // TOTAL = 100%

    /**
     * Display all CP Level Configurations.
     */
    public function index()
    {
        $configs = CpLevelConfig::all();

        // Calculate total percentage for verification
        $totalPercentage = $configs->sum('total_percentage_for_range');

        return response()->json([
            'success' => true,
            'message' => 'CP Level Configurations fetched successfully.',
            'data' => $configs,
            'summary' => [
                'total_percentage' => round($totalPercentage, 2),
                'expected_percentage' => 100.00,
                'is_valid' => round($totalPercentage, 2) == 100.00
            ]
        ], 200);
    }


    /**
     * Bulk update multiple CP Level Configs at once.
     * ⚠️ UPDATED: Total percentage must equal 100% (changed from 50%)
     */
    public function bulkUpdate(Request $request)
    {
        $configs = $request->get('configs');

        // ✅ Check array validity
        if (!is_array($configs) || empty($configs)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or empty configs array.'
            ], 422);
        }

        // ✅ Must be exactly 5 items
        if (count($configs) !== 5) {
            return response()->json([
                'success' => false,
                'message' => 'You must provide exactly 5 configuration items.'
            ], 422);
        }

        $totalPercentageSum = 0;
        $expectedLevelRanges = [
            ['from' => 1, 'to' => 3],
            ['from' => 4, 'to' => 6],
            ['from' => 7, 'to' => 9],
            ['from' => 10, 'to' => 20],
            ['from' => 21, 'to' => 30],
        ];

        foreach ($configs as $index => $item) {
            $validator = Validator::make($item, [
                'id' => 'required|exists:cp_level_configs,id',
                'level_from' => 'required|integer|min:1|max:30',
                'level_to' => 'required|integer|min:1|max:30|gte:level_from',
                'cp_percentage_per_level' => 'required|numeric|min:0|max:100',
                'total_percentage_for_range' => 'required|numeric|min:0|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed for one or more items.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // ✅ Verify level ranges match expected structure
            if (isset($expectedLevelRanges[$index])) {
                $expected = $expectedLevelRanges[$index];
                if ($item['level_from'] != $expected['from'] || $item['level_to'] != $expected['to']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Config item at index {$index} must have level_from={$expected['from']} and level_to={$expected['to']}."
                    ], 422);
                }
            }

            // ✅ Calculate and verify per-level percentage matches range total
            $levelCount = $item['level_to'] - $item['level_from'] + 1;
            $calculatedTotal = round($item['cp_percentage_per_level'] * $levelCount, 2);
            
            if ($calculatedTotal != round($item['total_percentage_for_range'], 2)) {
                return response()->json([
                    'success' => false,
                    'message' => "Level {$item['level_from']}-{$item['level_to']}: total_percentage_for_range ({$item['total_percentage_for_range']}) doesn't match calculated total ({$calculatedTotal})."
                ], 422);
            }

            // ✅ Calculate total sum
            $totalPercentageSum += $item['total_percentage_for_range'];
        }

        // ✅ Check total sum = 100 (UPDATED from 50)
        if (round($totalPercentageSum, 2) !== 100.00) {
            return response()->json([
                'success' => false,
                'message' => "The sum of all total_percentage_for_range must be exactly 100.00. Current sum: " . round($totalPercentageSum, 2)
            ], 422);
        }

        // ✅ Check for overlapping level ranges
        $allLevels = [];
        foreach ($configs as $item) {
            for ($level = $item['level_from']; $level <= $item['level_to']; $level++) {
                if (in_array($level, $allLevels)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Level {$level} appears in multiple ranges. Each level must be unique."
                    ], 422);
                }
                $allLevels[] = $level;
            }
        }

        // ✅ Verify all levels 1-30 are covered
        sort($allLevels);
        $expectedLevels = range(1, 30);
        if ($allLevels !== $expectedLevels) {
            $missing = array_diff($expectedLevels, $allLevels);
            return response()->json([
                'success' => false,
                'message' => 'All levels from 1 to 30 must be covered. Missing levels: ' . implode(', ', $missing)
            ], 422);
        }

        // ✅ If all checks pass, perform update
        try {
            foreach ($configs as $item) {
                CpLevelConfig::where('id', $item['id'])->update([
                    'level_from' => $item['level_from'],
                    'level_to' => $item['level_to'],
                    'cp_percentage_per_level' => $item['cp_percentage_per_level'],
                    'total_percentage_for_range' => $item['total_percentage_for_range'],
                ]);
            }

            // Log the update
            Log::info('CP Level Config bulk updated', [
                'total_configs' => count($configs),
                'total_percentage' => round($totalPercentageSum, 2),
                'updated_by' => auth()->id() ?? 'system'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'All 5 CP Level Configs updated successfully. Total percentage = 100.00',
                'data' => CpLevelConfig::all()
            ], 200);

        } catch (\Exception $e) {
            Log::error('CP Level Config bulk update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update configurations. Please try again.'
            ], 500);
        }
    }

    /**
     * Get CP distribution summary
     */
    public function summary()
    {
        $summary = CpLevelConfig::getDistributionSummary();
        $verification = CpLevelConfig::verifyTotalPercentage();

        return response()->json([
            'success' => true,
            'message' => 'CP distribution summary fetched successfully.',
            'data' => [
                'breakdown' => $summary,
                'verification' => $verification,
                'distribution_rules' => [
                    'Level 1-3' => '5% each = 15%',
                    'Level 4-6' => '15% each = 45%',
                    'Level 7-9' => '8% each = 24%',
                    'Level 10-20' => '1% each = 11%',
                    'Level 21-30' => '0.5% each = 5%',
                    'TOTAL' => '100%'
                ]
            ]
        ], 200);
    }

    /**
     * Calculate CP distribution for a given amount
     */
    public function calculateDistribution(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'total_cp_amount' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $totalCpAmount = $request->input('total_cp_amount');
        $distribution = CpLevelConfig::calculateCpDistribution($totalCpAmount);

        return response()->json([
            'success' => true,
            'message' => 'CP distribution calculated successfully.',
            'data' => [
                'total_cp_amount' => $totalCpAmount,
                'distribution_by_level' => $distribution
            ]
        ], 200);
    }

    /**
     * Get CP percentage for a specific level
     */
    public function getLevelPercentage($level)
    {
        if (!CpLevelConfig::levelExists($level)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid level. Level must be between 1 and 30.'
            ], 422);
        }

        $percentage = CpLevelConfig::getCpPercentageForLevel($level);

        return response()->json([
            'success' => true,
            'message' => "CP percentage for level {$level} fetched successfully.",
            'data' => [
                'level' => $level,
                'cp_percentage' => $percentage
            ]
        ], 200);
    }
}