<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Country;
use Exception;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $this->command->info('Starting country seeder...');
        
        // Check if countries already exist (optional)
        $existingCount = Country::count();
        if ($existingCount > 0) {
            $this->command->info("{$existingCount} countries already exist in database.");
            if (!$this->command->confirm('Do you want to refresh countries from API? This will update existing records.')) {
                $this->command->info('Skipping country seeding.');
                return;
            }
        }

        try {
            $this->command->info('Fetching countries from external API...');
            
            // Fetch data from external API (same as controller)
            $response = Http::get('https://api.first.org/data/v1/countries', [
                'limit' => 250
            ]);

            if (!$response->successful()) {
                $this->command->error('Failed to fetch countries from external API');
                return;
            }

            $data = $response->json();

            if (!isset($data['data']) || !is_array($data['data'])) {
                $this->command->error('Invalid data format received from API');
                return;
            }

            $countries = $data['data'];
            $this->command->info("Processing " . count($countries) . " countries from API...");

            $savedCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;

            DB::beginTransaction();

            try {
                $progressBar = $this->command->getOutput()->createProgressBar(count($countries));
                $progressBar->start();

                foreach ($countries as $countryCode => $countryData) {
                    // Skip if required fields are missing
                    if (!isset($countryData['country']) || !isset($countryData['region'])) {
                        $skippedCount++;
                        $progressBar->advance();
                        continue;
                    }

                    // Update or create country (same logic as controller)
                    $country = Country::updateOrCreate(
                        ['country_code' => $countryCode],
                        [
                            'country' => $countryData['country'],
                            'region' => $countryData['region'],
                        ]
                    );

                    if ($country->wasRecentlyCreated) {
                        $savedCount++;
                    } else {
                        $updatedCount++;
                    }

                    $progressBar->advance();
                }

                $progressBar->finish();
                $this->command->newLine(); // Move to next line after progress bar

                DB::commit();

                $this->command->info("✅ Country seeding completed successfully!");
                $this->command->info("📊 Statistics:");
                $this->command->info("   Total processed: " . count($countries));
                $this->command->info("   Newly created: {$savedCount}");
                $this->command->info("   Updated: {$updatedCount}");
                $this->command->info("   Skipped: {$skippedCount}");
                $this->command->info("   Total in database: " . Country::count());

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            $this->command->error('❌ An error occurred while processing countries: ' . $e->getMessage());
        }
    }
}
