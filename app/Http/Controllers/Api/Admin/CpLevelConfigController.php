<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CpLevelConfig;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CpLevelConfigController extends Controller
{

    // // Default Seeder Insert CP level configuration data
    // $configs = [
    //     [
    //         'level_from' => 1,
    //         'level_to' => 3,
    //         'cp_percentage_per_level' => 0.67,
    //         'total_percentage_for_range' => 2.00,
    //     ],
    //     [
    //         'level_from' => 4,
    //         'level_to' => 6,
    //         'cp_percentage_per_level' => 6.00,
    //         'total_percentage_for_range' => 18.00,
    //     ],
    //     [
    //         'level_from' => 7,
    //         'level_to' => 10,
    //         'cp_percentage_per_level' => 2.50,
    //         'total_percentage_for_range' => 10.00,
    //     ],
    //     [
    //         'level_from' => 11,
    //         'level_to' => 20,
    //         'cp_percentage_per_level' => 1.00,
    //         'total_percentage_for_range' => 10.00,
    //     ],
    //     [
    //         'level_from' => 21,
    //         'level_to' => 30,
    //         'cp_percentage_per_level' => 1.00,
    //         'total_percentage_for_range' => 10.00,
    //     ],
    // ];

    /**
     * Display all CP Level Configurations.
     */
    public function index()
    {
        $configs = CpLevelConfig::all();

        return response()->json([
            'success' => true,
            'message' => 'CP Level Configurations fetched successfully.',
            'data' => $configs
        ], 200);
    }


    /**
     * Bulk update multiple CP Level Configs at once.
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

        foreach ($configs as $item) {
            $validator = Validator::make($item, [
                'id' => 'required|exists:cp_level_configs,id',
                'level_from' => 'required|integer|min:1',
                'level_to' => 'required|integer|min:1|gte:level_from',
                'cp_percentage_per_level' => 'required|numeric|min:0',
                'total_percentage_for_range' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed for one or more items.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // ✅ Calculate total sum
            $totalPercentageSum += $item['total_percentage_for_range'];
        }

        // ✅ Check total sum = 50
        if (round($totalPercentageSum, 2) !== 50.00) {
            return response()->json([
                'success' => false,
                'message' => 'The sum of all total_percentage_for_range must be exactly 50.00.'
            ], 422);
        }

        // ✅ If all checks pass, perform update
        foreach ($configs as $item) {
            CpLevelConfig::where('id', $item['id'])->update([
                'level_from' => $item['level_from'],
                'level_to' => $item['level_to'],
                'cp_percentage_per_level' => $item['cp_percentage_per_level'],
                'total_percentage_for_range' => $item['total_percentage_for_range'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'All 5 CP Level Configs updated successfully. Total percentage = 50.00'
        ], 200);
    }

}
