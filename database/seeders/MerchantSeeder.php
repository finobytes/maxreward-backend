<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Merchant;
use App\Models\MerchantStaff;
use App\Models\Member;
use App\Models\MemberWallet;
use App\Models\MerchantWallet;
use Illuminate\Support\Facades\Hash;
use App\Traits\MerchantHelperTrait;

class MerchantSeeder extends Seeder
{
    use MerchantHelperTrait;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ✅ Truncate tables before seeding
        $this->command->warn('🗑️  Truncating tables...');
    
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Truncate tables
        DB::table('merchant_staffs')->truncate();
        DB::table('merchant_wallets')->truncate();
        DB::table('merchants')->truncate();
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        $this->command->info('✅ Tables truncated successfully!');
        $this->command->info('');

        $now = Carbon::now();

        // ============================================
        // MERCHANT 1: Super Shop
        // ============================================
        
        $this->command->info('🏪 Creating Merchant 1: Shwapno Super Shop...');
        
        $uniqueNumber1 = $this->generateUniqueNumber();
        
        $merchant1 = Merchant::create([
            'business_name' => 'Shwapno Super Shop',
            'business_type_id' => 1,
            'business_description' => 'Leading retail supermarket chain offering groceries, fresh produce, and household items',
            'company_address' => 'House 25, Road 8, Dhanmondi, Dhaka-1205',
            'status' => 'approved',
            'license_number' => 'TRAD/DSCC/111222',
            'unique_number' => $uniqueNumber1,
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
            'merchant_created_by' => 'admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        
        // Create Corporate Member for Merchant 1
        $corporateUsername1 = $this->generateCorporateMemberUsername();
        
        $corporateMember1 = Member::create([
            'user_name' => $corporateUsername1,
            'name' => 'Shwapno Super Shop',
            'phone' => '01712345678',
            'email' => 'contact@shwapno.com',
            'password' => Hash::make('password123'),
            'address' => 'Dhanmondi, Dhaka',
            'member_type' => 'corporate',
            'gender_type' => 'male',
            'status' => 'active',
            'merchant_id' => $merchant1->id,
            'member_created_by' => 'merchant',
            'referral_code' => strtoupper(Str::random(8)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Update merchant with corporate_member_id
        $merchant1->update(['corporate_member_id' => $corporateMember1->id]);

        // Create Member Wallet for Corporate Member
        MemberWallet::create([
            'member_id' => $corporateMember1->id,
            'total_referrals' => 0,
            'unlocked_level' => 5,
            'onhold_points' => 0.00,
            'total_points' => 0.00,
            'available_points' => 0.00,
            'total_rp' => 0.00,
            'total_pp' => 0.00,
            'total_cp' => 0.00,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Create Merchant Wallet
        MerchantWallet::create([
            'merchant_id' => $merchant1->id,
            'total_referrals' => 0,
            'unlocked_level' => 5,
            'onhold_points' => 0.00,
            'total_points' => 0.00,
            'available_points' => 0.00,
            'total_rp' => 0.00,
            'total_pp' => 0.00,
            'total_cp' => 0.00,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->command->info("   ✅ Merchant created with unique_number: {$uniqueNumber1}");
        $this->command->info("   ✅ Corporate Member created: {$corporateUsername1}");
        $this->command->info("   ✅ Member Wallet created");
        $this->command->info("   ✅ Merchant Wallet created");
        $this->command->info('');

        // ============================================
        // MERCHANT 2: Electronics Store
        // ============================================
        
        $this->command->info('🏪 Creating Merchant 2: TechZone Electronics...');
        
        $uniqueNumber2 = $this->generateUniqueNumber();
        
        $merchant2 = Merchant::create([
            'business_name' => 'TechZone Electronics',
            'business_type_id' => 2,
            'business_description' => 'Leading electronics and gadgets retailer with authentic products',
            'company_address' => 'Shop 12, Level 4, Bashundhara City, Panthapath, Dhaka',
            'status' => 'approved',
            'license_number' => 'TRAD/DSCC/789012',
            'unique_number' => $uniqueNumber2,
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
            'merchant_created_by' => 'admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Create Corporate Member for Merchant 2
        $corporateUsername2 = $this->generateCorporateMemberUsername();
        
        $corporateMember2 = Member::create([
            'user_name' => $corporateUsername2,
            'name' => 'TechZone Electronics',
            'phone' => '01812345679',
            'email' => 'info@techzone.com',
            'password' => Hash::make('password123'),
            'address' => 'Panthapath, Dhaka',
            'member_type' => 'corporate',
            'gender_type' => 'female',
            'status' => 'active',
            'merchant_id' => $merchant2->id,
            'member_created_by' => 'merchant',
            'referral_code' => strtoupper(Str::random(8)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Update merchant with corporate_member_id
        $merchant2->update(['corporate_member_id' => $corporateMember2->id]);

        // Create Member Wallet for Corporate Member
        MemberWallet::create([
            'member_id' => $corporateMember2->id,
            'total_referrals' => 0,
            'unlocked_level' => 5,
            'onhold_points' => 0.00,
            'total_points' => 0.00,
            'available_points' => 0.00,
            'total_rp' => 0.00,
            'total_pp' => 0.00,
            'total_cp' => 0.00,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Create Merchant Wallet
        MerchantWallet::create([
            'merchant_id' => $merchant2->id,
            'total_referrals' => 0,
            'unlocked_level' => 5,
            'onhold_points' => 0.00,
            'total_points' => 0.00,
            'available_points' => 0.00,
            'total_rp' => 0.00,
            'total_pp' => 0.00,
            'total_cp' => 0.00,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->command->info("   ✅ Merchant created with unique_number: {$uniqueNumber2}");
        $this->command->info("   ✅ Corporate Member created: {$corporateUsername2}");
        $this->command->info("   ✅ Member Wallet created");
        $this->command->info("   ✅ Merchant Wallet created");
        $this->command->info('');

        // ============================================
        // STAFF MEMBERS
        // ============================================

        $this->command->info('👥 Creating staff members...');

        // Staff members for Merchant 1 (Shwapno Super Shop)
        $merchant1Staff = [
            [
                'merchant_id' => $merchant1->id,
                'user_name' => 'M100000001',
                'name' => 'Kamal Hossain',
                'phone' => '01712345678',
                'email' => 'kamal@shwapno.com',
                'password' => Hash::make('password123'),
                'address' => 'Panthapath, Dhaka',
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
                'password' => Hash::make('staff123'),
                'address' => 'Panthapath, Dhaka',
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
                'password' => Hash::make('staff123'),
                'address' => 'Panthapath, Dhaka',
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
                'password' => Hash::make('staff123'),
                'address' => 'Panthapath, Dhaka',
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
                'password' => Hash::make('password123'),
                'address' => 'Panthapath, Dhaka',
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
                'password' => Hash::make('staff123'),
                'address' => 'Panthapath, Dhaka',
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
                'password' => Hash::make('staff123'),
                'address' => 'Panthapath, Dhaka',
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
                'password' => Hash::make('staff123'),
                'address' => 'Panthapath, Dhaka',
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
                'password' => Hash::make('staff123'),
                'address' => 'Panthapath, Dhaka',
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

        $this->command->info('   ✅ All staff members created');
        $this->command->info('');

        // ============================================
        // SUMMARY
        // ============================================

        $this->command->info('✅ Successfully created 2 merchants with complete setup!');
        $this->command->info('');
        $this->command->info('📋 Test Credentials:');
        $this->command->info('');
        $this->command->info('🏪 Merchant 1 - Shwapno Super Shop:');
        $this->command->info("   Unique Number: {$uniqueNumber1}");
        $this->command->info("   Corporate Member: {$corporateUsername1} / password123");
        $this->command->info('   Owner: M100000001 / password123');
        $this->command->info('   Staff: M100000002 / staff123');
        $this->command->info('   Staff: M100000003 / staff123');
        $this->command->info('   Staff: M100000004 / staff123');
        $this->command->info('');
        $this->command->info('🏪 Merchant 2 - TechZone Electronics:');
        $this->command->info("   Unique Number: {$uniqueNumber2}");
        $this->command->info("   Corporate Member: {$corporateUsername2} / password123");
        $this->command->info('   Owner: M100000005 / password123');
        $this->command->info('   Staff: M100000006 / staff123');
        $this->command->info('   Staff: M100000007 / staff123');
        $this->command->info('   Staff: M100000008 / staff123');
        $this->command->info('   Staff: M100000009 / staff123 (inactive)');
    }
}