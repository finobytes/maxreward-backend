<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CompanyInfo;

class CompanyInfoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or update company info (only one record should exist)
        CompanyInfo::updateOrCreate(
            ['id' => 1], // Check by ID
            [
                'name' => 'MaxReward Sdn Bhd',
                'address' => 'Level 10, Menara MaxReward, Jalan Sultan Ismail, 50250 Kuala Lumpur, Malaysia',
                'phone' => '60123456789',
                'email' => 'info@maxreward.com',
                'logo' => null, // Will be updated when logo is uploaded
                'cr_points' => 0, // Company Reserve points start at 0
            ]
        );

        $this->command->info('✅ Company Info created successfully!');
        $this->command->info('   Name: MaxReward Sdn Bhd');
        $this->command->info('   CR Points: 0.00');
    }
}
