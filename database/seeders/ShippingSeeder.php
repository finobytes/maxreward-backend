<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ShippingZone;
use App\Models\ShippingZoneArea;
use App\Models\ShippingMethod;

class ShippingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Shipping Methods
        $methods = [
            [
                'name' => 'Express Delivery', 
                'code' => 'EXPRESS', 
                'description' => 'Fast delivery within 1-2 days',
                'min_days' => 1, 
                'max_days' => 2,
                'is_active' => true,
                'sort_order' => 1 // Highest priority
            ],
            [
                'name' => 'Standard Delivery', 
                'code' => 'STANDARD', 
                'description' => 'Regular delivery within 3-5 days',
                'min_days' => 3, 
                'max_days' => 5,
                'is_active' => true,
                'sort_order' => 2 // Medium priority
            ],
            // [
            //     'name' => 'Economy Delivery', 
            //     'code' => 'ECONOMY', 
            //     'description' => 'Budget-friendly delivery within 5-7 days',
            //     'min_days' => 3, 
            //     'max_days' => 5,
            //     'is_active' => true,
            //     'sort_order' => 3 // Lowest priority
            // ],
        ];

        foreach ($methods as $method) {
            ShippingMethod::create($method);
        }

        // 2. Create Zones with Malaysian postcodes
        $zones = [
            // West Malaysia
            [
                'name' => 'West Malaysia - Central',
                'zone_code' => 'WM_CENTRAL',
                'region' => 'west_malaysia',
                'description' => 'Kuala Lumpur, Selangor, Putrajaya',
                'postcodes' => ['40', '41', '42', '43', '44', '45', '46', '47', '48', '50', '51', '52', '53', '54', '55', '56', '57', '58', '59', '60', '68']
            ],
            [
                'name' => 'West Malaysia - North',
                'zone_code' => 'WM_NORTH',
                'region' => 'west_malaysia',
                'description' => 'Perlis, Kedah, Penang, Perak',
                'postcodes' => ['01', '02', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '30', '31', '32', '33', '34', '35', '36']
            ],
            [
                'name' => 'West Malaysia - South',
                'zone_code' => 'WM_SOUTH',
                'region' => 'west_malaysia',
                'description' => 'Negeri Sembilan, Malacca, Johor',
                'postcodes' => ['70', '71', '72', '73', '74', '75', '76', '77', '78', '79', '80', '81', '82', '83', '84', '85', '86']
            ],
            [
                'name' => 'West Malaysia - East Coast',
                'zone_code' => 'WM_EAST',
                'region' => 'west_malaysia',
                'description' => 'Kelantan, Terengganu, Pahang',
                'postcodes' => ['15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '39']
            ],
            // East Malaysia
            [
                'name' => 'East Malaysia - Sabah',
                'zone_code' => 'EM_SABAH',
                'region' => 'east_malaysia',
                'description' => 'Sabah and Federal Territory of Labuan',
                'postcodes' => ['87', '88', '89', '90', '91']
            ],
            [
                'name' => 'East Malaysia - Sarawak',
                'zone_code' => 'EM_SARAWAK',
                'region' => 'east_malaysia',
                'description' => 'Sarawak',
                'postcodes' => ['93', '94', '95', '96', '97', '98']
            ],
        ];

        foreach ($zones as $zoneData) {
            $zone = ShippingZone::create([
                'name' => $zoneData['name'],    
                'zone_code' => $zoneData['zone_code'],
                'region' => $zoneData['region'],
                'description' => $zoneData['description'],
                'is_active' => true,
            ]);

            // Add postcode prefixes
            foreach ($zoneData['postcodes'] as $prefix) {
                ShippingZoneArea::create([
                    'zone_id' => $zone->id,
                    'postcode_prefix' => $prefix,
                ]);
            }
        }

        $this->command->info('✅ Shipping methods and zones seeded successfully!');
        $this->command->info('   - 3 shipping methods created (Express, Standard, Economy)');
        $this->command->info('   - 6 zones created with Malaysian postcode mappings');
    }
}