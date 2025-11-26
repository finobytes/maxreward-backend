<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Country;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Exception;

class CountryController extends Controller
{
    /**
     * Fetch countries from external API and save to database
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchAndSaveCountries()
    {
        try {
            // Fetch data from external API
            $response = Http::get('https://api.first.org/data/v1/countries', [
                'limit' => 250
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch countries from external API',
                ], 500);
            }

            $data = $response->json();

            // dd($data);

            if (!isset($data['data']) || !is_array($data['data'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid data format received from API',
                ], 500);
            }

            $countries = $data['data'];
            $savedCount = 0;
            $updatedCount = 0;
            $errors = [];

            DB::beginTransaction();

            try {
                foreach ($countries as $countryCode => $countryData) {
                    // Skip if required fields are missing
                    if (!isset($countryData['country']) || !isset($countryData['region'])) {
                        $errors[] = "Skipped {$countryCode}: Missing required fields";
                        continue;
                    }

                    // Update or create country
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
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Countries fetched and saved successfully',
                    'data' => [
                        'total_processed' => count($countries),
                        'newly_saved' => $savedCount,
                        'updated' => $updatedCount,
                        'errors' => $errors,
                    ],
                ], 200);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing countries',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all countries from database
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllCountries()
    {
        try {
            $countries = Country::orderBy('country', 'asc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Countries retrieved successfully',
                'data' => $countries,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving countries',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
