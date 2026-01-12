<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\BusinessType;

class BusinessTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Check if business types already exist to avoid duplicates
        if (BusinessType::count() > 0) {
            $this->command->info('Business types already exist. Skipping seeding.');
            return;
        }

        // Define the business types to seed
        $businessTypes = [
            [
                'name' => 'Electronics',
            ],
            [
                'name' => 'Clothing',
            ],
        ];

        try {
            // Start transaction
            DB::beginTransaction();

            foreach ($businessTypes as $businessType) {
                BusinessType::create([
                    'name' => $businessType['name'],
                ]);
            }

            // Commit transaction
            DB::commit();

            $this->command->info('Successfully seeded 2 business types: Electronics and Clothing.');

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();
            $this->command->error('Failed to seed business types: ' . $e->getMessage());
        }
    }
}
