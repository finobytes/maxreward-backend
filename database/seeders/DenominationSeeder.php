<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Denomination;

class DenominationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Check if denominations already exist to avoid duplicates
        if (Denomination::count() > 0) {
            $this->command->info('Denominations already exist. Skipping seeding.');
            return;
        }

        // Define the denominations to seed
        $denominations = [
            [
                'title' => 'RM 10',
                'value' => '10',
            ],
            [
                'title' => 'RM 100',
                'value' => '100',
            ],
            [
                'title' => 'RM 1000',
                'value' => '1000',
            ],
        ];

        try {
            // Start transaction
            DB::beginTransaction();

            foreach ($denominations as $denomination) {
                Denomination::create([
                    'title' => $denomination['title'],
                    'value' => $denomination['value'],
                ]);
            }

            // Commit transaction
            DB::commit();

            $this->command->info('Successfully seeded 3 denominations.');

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();
            $this->command->error('Failed to seed denominations: ' . $e->getMessage());
        }
    }
}
