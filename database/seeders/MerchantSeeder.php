<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\Merchant;
use App\Models\MerchantStaff;

class MerchantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Merchant 1: Super Shop
        $merchant1 = Merchant::create([
            'business_name' => 'Shwapno Super Shop',
            'business_type' => 'Super Shop',
            'business_description' => 'Leading retail supermarket chain offering groceries, fresh produce, and household items',
            'company_address' => 'House 25, Road 8, Dhanmondi, Dhaka-1205',
            'status' => 'approved',
            'license_number' => 'TRAD/DSCC/111222',
            'bank_name' => 'Dutch Bangla Bank',
            'account_holder_name' => 'Shwapno Super Shop Ltd',
            'account_number' => '1234567890123',
            'preferred_payment_method' => 'Bank Transfer',
            'routing_number' => '090270567',
            'owner_name' => 'Kamal Hossain',
            'phone' => '01712345678',
            'gender' => 'male',
            'address' => 'Dhanmondi, Dhaka',
            'email' => 'contact@shwapno.com',
            'commission_rate' => 4.00,
            'settlement_period' => 'weekly',
            'state' => 'Dhaka',
            'country' => 'Bangladesh',
            'products_services' => 'Groceries, Fresh Food, Household Items, Personal Care',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Merchant 2: Electronics Store
        $merchant2 = Merchant::create([
            'business_name' => 'TechZone Electronics',
            'business_type' => 'Electronics Retail',
            'business_description' => 'Leading electronics and gadgets retailer with authentic products',
            'company_address' => 'Shop 12, Level 4, Bashundhara City, Panthapath, Dhaka',
            'status' => 'approved',
            'license_number' => 'TRAD/DSCC/789012',
            'bank_name' => 'BRAC Bank',
            'account_holder_name' => 'TechZone Electronics Ltd',
            'account_number' => '9876543210987',
            'preferred_payment_method' => 'Bank Transfer',
            'routing_number' => '060270587',
            'owner_name' => 'Sadia Rahman',
            'phone' => '01812345679',
            'gender' => 'female',
            'address' => 'Panthapath, Dhaka',
            'email' => 'info@techzone.com',
            'commission_rate' => 3.50,
            'settlement_period' => 'monthly',
            'state' => 'Dhaka',
            'country' => 'Bangladesh',
            'products_services' => 'Mobile, Laptop, Accessories, Gaming',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Staff members for Merchant 1 (Shwapno Super Shop)
        $merchant1Staff = [
            [
                'merchant_id' => $merchant1->id,
                'user_name' => 'M100000001',
                'name' => 'Kamal Hossain',
                'phone' => '01712345678',
                'email' => 'kamal@shwapno.com',
                'password' => 'password123',
                'type' => 'merchant',
                'status' => 'active',
                'gender_type' => 'male',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'merchant_id' => $merchant1->id,
                'user_name' => 'M100000002',
                'name' => 'Rahim Uddin',
                'phone' => '01712345680',
                'email' => 'rahim@shwapno.com',
                'password' => 'staff123',
                'type' => 'staff',
                'status' => 'active',
                'gender_type' => 'male',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'merchant_id' => $merchant1->id,
                'user_name' => 'M100000003',
                'name' => 'Fatima Begum',
                'phone' => '01712345681',
                'email' => 'fatima@shwapno.com',
                'password' => 'staff123',
                'type' => 'staff',
                'status' => 'active',
                'gender_type' => 'female',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'merchant_id' => $merchant1->id,
                'user_name' => 'M100000004',
                'name' => 'Jamal Hossain',
                'phone' => '01712345682',
                'email' => 'jamal@shwapno.com',
                'password' => 'staff123',
                'type' => 'staff',
                'status' => 'active',
                'gender_type' => 'male',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        // Staff members for Merchant 2 (TechZone Electronics)
        $merchant2Staff = [
            [
                'merchant_id' => $merchant2->id,
                'user_name' => 'M100000005',
                'name' => 'Sadia Rahman',
                'phone' => '01812345679',
                'email' => 'sadia@techzone.com',
                'password' => 'password123',
                'type' => 'merchant',
                'status' => 'active',
                'gender_type' => 'female',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'merchant_id' => $merchant2->id,
                'user_name' => 'M100000006',
                'name' => 'Kamal Ahmed',
                'phone' => '01812345683',
                'email' => 'kamal@techzone.com',
                'password' => 'staff123',
                'type' => 'staff',
                'status' => 'active',
                'gender_type' => 'male',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'merchant_id' => $merchant2->id,
                'user_name' => 'M100000007',
                'name' => 'Nusrat Jahan',
                'phone' => '01812345684',
                'email' => 'nusrat@techzone.com',
                'password' => 'staff123',
                'type' => 'staff',
                'status' => 'active',
                'gender_type' => 'female',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'merchant_id' => $merchant2->id,
                'user_name' => 'M100000008',
                'name' => 'Rakib Hasan',
                'phone' => '01812345685',
                'email' => 'rakib@techzone.com',
                'password' => 'staff123',
                'type' => 'staff',
                'status' => 'active',
                'gender_type' => 'male',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'merchant_id' => $merchant2->id,
                'user_name' => 'M100000009',
                'name' => 'Salma Akter',
                'phone' => '01812345686',
                'email' => 'salma@techzone.com',
                'password' => 'staff123',
                'type' => 'staff',
                'status' => 'inactive',
                'gender_type' => 'female',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        // Insert all staff members individually
        foreach ($merchant1Staff as $staff) {
            MerchantStaff::create($staff);
        }

        foreach ($merchant2Staff as $staff) {
            MerchantStaff::create($staff);
        }

        $this->command->info('✅ Successfully created 2 merchants with multiple staff members!');
        $this->command->info('');
        $this->command->info('📋 Test Credentials:');
        $this->command->info('');
        $this->command->info('🏪 Merchant 1 - Shwapno Super Shop:');
        $this->command->info('   Owner: M100000001 / password123');
        $this->command->info('   Staff: M100000002 / staff123');
        $this->command->info('   Staff: M100000003 / staff123');
        $this->command->info('   Staff: M100000004 / staff123');
        $this->command->info('');
        $this->command->info('🏪 Merchant 2 - TechZone Electronics:');
        $this->command->info('   Owner: M100000005 / password123');
        $this->command->info('   Staff: M100000006 / staff123');
        $this->command->info('   Staff: M100000007 / staff123');
        $this->command->info('   Staff: M100000008 / staff123');
        $this->command->info('   Staff: M100000009 / staff123 (inactive)');
    }
}