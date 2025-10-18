<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CpLevelConfig;

class CpLevelConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data to prevent duplicates
        CpLevelConfig::truncate();

        // Insert CP level configuration data
        $configs = [
            [
                'level_from' => 1,
                'level_to' => 3,
                'cp_percentage_per_level' => 0.67,
                'total_percentage_for_range' => 2.00,
            ],
            [
                'level_from' => 4,
                'level_to' => 6,
                'cp_percentage_per_level' => 6.00,
                'total_percentage_for_range' => 18.00,
            ],
            [
                'level_from' => 7,
                'level_to' => 10,
                'cp_percentage_per_level' => 2.50,
                'total_percentage_for_range' => 10.00,
            ],
            [
                'level_from' => 11,
                'level_to' => 20,
                'cp_percentage_per_level' => 1.00,
                'total_percentage_for_range' => 10.00,
            ],
            [
                'level_from' => 21,
                'level_to' => 30,
                'cp_percentage_per_level' => 1.00,
                'total_percentage_for_range' => 10.00,
            ],
        ];

        foreach ($configs as $config) {
            CpLevelConfig::create($config);
        }

        // Verify total percentage
        $verification = CpLevelConfig::verifyTotalPercentage();

        $this->command->info('✅ CP Level Config seeded successfully!');
        $this->command->info('   Total Ranges: ' . count($configs));
        $this->command->info('   Total Percentage: ' . $verification['total'] . '%');
        $this->command->info('   Validation: ' . ($verification['is_valid'] ? 'PASSED ✓' : 'FAILED ✗'));

        if (!$verification['is_valid']) {
            $this->command->warn('   Expected: ' . $verification['expected'] . '%');
            $this->command->warn('   Difference: ' . $verification['difference'] . '%');
        }
    }
}
