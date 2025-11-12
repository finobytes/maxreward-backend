<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CpLevelConfig;
use Illuminate\Support\Facades\DB;

class CpLevelConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * NEW CP DISTRIBUTION RULES:
     * Gen 1-3:    5% x 3  = 15%
     * Gen 4-6:   15% x 3  = 45%
     * Gen 7-9:    8% x 3  = 24%
     * Gen 10-20:  1% x 11 = 11%
     * Gen 21-30: 0.5% x 10 = 5%
     * ========================
     * TOTAL              = 100%
     */
    public function run(): void
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Clear existing data to prevent duplicates
        DB::table('cp_level_configs')->truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Insert NEW CP level configuration data
        $configs = [
            [
                'level_from' => 1,
                'level_to' => 3,
                'cp_percentage_per_level' => 5.00,
                'total_percentage_for_range' => 15.00,
            ],
            [
                'level_from' => 4,
                'level_to' => 6,
                'cp_percentage_per_level' => 15.00,
                'total_percentage_for_range' => 45.00,
            ],
            [
                'level_from' => 7,
                'level_to' => 9,
                'cp_percentage_per_level' => 8.00,
                'total_percentage_for_range' => 24.00,
            ],
            [
                'level_from' => 10,
                'level_to' => 20,
                'cp_percentage_per_level' => 1.00,
                'total_percentage_for_range' => 11.00,
            ],
            [
                'level_from' => 21,
                'level_to' => 30,
                'cp_percentage_per_level' => 0.50,
                'total_percentage_for_range' => 5.00,
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
        
        // Update validation to check for 100% instead of 50%
        $isValid = round($verification['total'], 2) == 100.00;
        $this->command->info('   Validation: ' . ($isValid ? 'PASSED ✓' : 'FAILED ✗'));

        if (!$isValid) {
            $this->command->warn('   Expected: 100.00%');
            $this->command->warn('   Actual: ' . $verification['total'] . '%');
            $this->command->warn('   Difference: ' . round(100.00 - $verification['total'], 2) . '%');
        }

        // Display breakdown
        $this->command->info('');
        $this->command->info('📊 CP Distribution Breakdown:');
        $this->command->info('   Level 1-3:   5% each   = 15%');
        $this->command->info('   Level 4-6:  15% each   = 45%');
        $this->command->info('   Level 7-9:   8% each   = 24%');
        $this->command->info('   Level 10-20: 1% each   = 11%');
        $this->command->info('   Level 21-30: 0.5% each = 5%');
        $this->command->info('   ─────────────────────────────');
        $this->command->info('   TOTAL                  = 100%');
    }
}