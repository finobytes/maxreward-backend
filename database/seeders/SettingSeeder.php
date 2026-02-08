<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         // Create or update the setting record (only one record should exist)
         Setting::updateOrCreate(
            ['id' => 1], // Ensure only one record exists
            [
                'setting_attribute' => [
                    'maxreward' => [
                        'rm_points' => 100,
                        'pp_points' => 10,
                        'rp_points' => 20,
                        'cp_points' => 50,
                        'cr_points' => 20,
                        'max_level' => 30,
                        'deductable_points' => 100,
                        'auto_release_days' => 5
                    ]
                ]
            ]
        );

        $this->command->info('✅ Setting created successfully!');
        $this->command->info('   MaxReward points: RM=100, PP=10, RP=20, CP=50, CR=20');
    }
}
