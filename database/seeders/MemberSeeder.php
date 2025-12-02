<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Member;
use App\Models\MemberWallet;
use Illuminate\Support\Facades\Hash;
use App\Traits\MemberHelperTrait;

class MemberSeeder extends Seeder
{
    use MemberHelperTrait;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->warn('🗑️  Truncating member_wallets and members tables...');
        
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Truncate tables
        DB::table('member_wallets')->truncate();
        DB::table('members')->truncate();
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        $this->command->info('✅ Tables truncated successfully!');
        $this->command->info('');
        $this->command->info('👥 Creating general members...');

        $now = Carbon::now();

        // Malaysian phone numbers (10-11 digits, starting with 01)
        $members = [
            // Members 1-10
            ['user_name' => '880123456789', 'name' => 'Ahmad bin Abdullah', 'phone' => '880123456789', 'email' => 'ahmad@example.com', 'gender_type' => 'male', 'address' => 'Kajang, Selangor'],
            ['user_name' => '880167891234', 'name' => 'Siti Nurhaliza', 'phone' => '880167891234', 'email' => 'siti@example.com', 'gender_type' => 'female', 'address' => 'Johor Bahru, Johor'],
            ['user_name' => '880198765432', 'name' => 'Lee Wei Ming', 'phone' => '880198765432', 'email' => 'lee@example.com', 'gender_type' => 'male', 'address' => 'George Town, Penang'],
            ['user_name' => '880134567890', 'name' => 'Priya Devi', 'phone' => '880134567890', 'email' => 'priya@example.com', 'gender_type' => 'female', 'address' => 'Shah Alam, Selangor'],
            ['user_name' => '880112233445', 'name' => 'Muhammad Hafiz', 'phone' => '880112233445', 'email' => 'hafiz@example.com', 'gender_type' => 'male', 'address' => 'Kota Bharu, Kelantan'],
            ['user_name' => '880145678901', 'name' => 'Nurul Ain', 'phone' => '880145678901', 'email' => 'nurul@example.com', 'gender_type' => 'female', 'address' => 'Ipoh, Perak'],
            ['user_name' => '880176543210', 'name' => 'Raj Kumar', 'phone' => '880176543210', 'email' => 'raj@example.com', 'gender_type' => 'male', 'address' => 'Melaka City, Melaka'],
            ['user_name' => '880189012345', 'name' => 'Fatimah Zahra', 'phone' => '880189012345', 'email' => 'fatimah@example.com', 'gender_type' => 'female', 'address' => 'Kota Kinabalu, Sabah'],
            ['user_name' => '880123334455', 'name' => 'Chen Wei Liang', 'phone' => '880123334455', 'email' => 'chen@example.com', 'gender_type' => 'male', 'address' => 'Kuching, Sarawak'],
            ['user_name' => '880165554444', 'name' => 'Aisha Binti Ali', 'phone' => '880165554444', 'email' => 'aisha@example.com', 'gender_type' => 'female', 'address' => 'Seremban, Negeri Sembilan'],
        
            // Members 11-20
            ['user_name' => '880128889999', 'name' => 'Aziz bin Hassan', 'phone' => '880128889999', 'email' => 'aziz@example.com', 'gender_type' => 'male', 'address' => 'Alor Setar, Kedah'],
            ['user_name' => '880167778888', 'name' => 'Nadia binti Ahmad', 'phone' => '880167778888', 'email' => 'nadia@example.com', 'gender_type' => 'female', 'address' => 'Petaling Jaya, Selangor'],
            ['user_name' => '880191112222', 'name' => 'Tan Ah Kow', 'phone' => '880191112222', 'email' => 'tan@example.com', 'gender_type' => 'male', 'address' => 'Miri, Sarawak'],
            ['user_name' => '880133334444', 'name' => 'Kavitha Murugan', 'phone' => '880133334444', 'email' => 'kavitha@example.com', 'gender_type' => 'female', 'address' => 'Batu Pahat, Johor'],
            ['user_name' => '880115556666', 'name' => 'Iskandar Shah', 'phone' => '880115556666', 'email' => 'iskandar@example.com', 'gender_type' => 'male', 'address' => 'Sungai Petani, Kedah'],
            ['user_name' => '880147778888', 'name' => 'Zainab Mohd', 'phone' => '880147778888', 'email' => 'zainab@example.com', 'gender_type' => 'female', 'address' => 'Kuantan, Pahang'],
            ['user_name' => '880172223333', 'name' => 'Wong Chee Meng', 'phone' => '880172223333', 'email' => 'wong@example.com', 'gender_type' => 'male', 'address' => 'Sandakan, Sabah'],
            ['user_name' => '880184445555', 'name' => 'Salmah Ibrahim', 'phone' => '880184445555', 'email' => 'salmah@example.com', 'gender_type' => 'female', 'address' => 'Putrajaya'],
            ['user_name' => '880126667777', 'name' => 'Kumar Rajan', 'phone' => '880126667777', 'email' => 'kumar@example.com', 'gender_type' => 'male', 'address' => 'Johor Bahru, Johor'],
            ['user_name' => '880168889999', 'name' => 'Mariam Zainal', 'phone' => '880168889999', 'email' => 'mariam@example.com', 'gender_type' => 'female', 'address' => 'Cyberjaya, Selangor'],
        
            // Members 21-30
            ['user_name' => '880197771111', 'name' => 'Ali bin Osman', 'phone' => '880197771111', 'email' => 'ali@example.com', 'gender_type' => 'male', 'address' => 'Kangar, Perlis'],
            ['user_name' => '880132223333', 'name' => 'Farah Hana', 'phone' => '880132223333', 'email' => 'farah@example.com', 'gender_type' => 'female', 'address' => 'Nilai, Negeri Sembilan'],
            ['user_name' => '880114445555', 'name' => 'Lim Teck Soon', 'phone' => '880114445555', 'email' => 'lim@example.com', 'gender_type' => 'male', 'address' => 'Taiping, Perak'],
            ['user_name' => '880146667777', 'name' => 'Indrani Nair', 'phone' => '880146667777', 'email' => 'indrani@example.com', 'gender_type' => 'female', 'address' => 'Labuan'],
            ['user_name' => '880178889999', 'name' => 'Hakim Rashid', 'phone' => '880178889999', 'email' => 'hakim@example.com', 'gender_type' => 'male', 'address' => 'Kuala Terengganu, Terengganu'],
            ['user_name' => '880181112222', 'name' => 'Aisyah Roslan', 'phone' => '880181112222', 'email' => 'aisyah@example.com', 'gender_type' => 'female', 'address' => 'Cheras, Kuala Lumpur'],
            ['user_name' => '880125556666', 'name' => 'Chong Wai Kit', 'phone' => '880125556666', 'email' => 'chong@example.com', 'gender_type' => 'male', 'address' => 'Manjung, Perak'],
            ['user_name' => '880163334444', 'name' => 'Laila Ahmad', 'phone' => '880163334444', 'email' => 'laila@example.com', 'gender_type' => 'female', 'address' => 'Bintulu, Sarawak'],
            ['user_name' => '880199998888', 'name' => 'Muthu Selvam', 'phone' => '880199998888', 'email' => 'muthu@example.com', 'gender_type' => 'male', 'address' => 'Bukit Mertajam, Penang'],
            ['user_name' => '880137776666', 'name' => 'Hana Safiya', 'phone' => '880137776666', 'email' => 'hana@example.com', 'gender_type' => 'female', 'address' => 'Setia Alam, Selangor'],
        ];
        

        // Add common fields to all members
        $members = array_map(function($member) use ($now) {
            return array_merge($member, [
                'password' => Hash::make('password123'),
                'member_type' => 'general',
                'status' => 'active',
                'merchant_id' => null,
                'member_created_by' => 'admin',
                'referral_code' => $this->generateUniqueReferralCode(),
                'country_id' => 67,
                'country_code' => '880',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $members);

        $createdCount = 0;

        foreach ($members as $memberData) {
            // Create Member
            $member = Member::create($memberData);
            
            // Create Member Wallet automatically
            MemberWallet::create([
                'member_id' => $member->id,
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

            $createdCount++;
            $this->command->info("   ✅ Member created: {$memberData['user_name']} - {$memberData['name']} (Referral: {$memberData['referral_code']})");
        }

        $this->command->info('');
        $this->command->info("✅ Successfully created {$createdCount} general members with wallets!");
        $this->command->info('');
        
        // ============================================
        // CREATE 1 INDEPENDENT CORPORATE MEMBER (31st member)
        // ============================================
        
        $this->command->info('🏢 Creating independent corporate member...');
        
        $corporateUsername = 'C' . str_pad(rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        
        $corporateMember = Member::create([
            'user_name' => $corporateUsername,
            'name' => 'Global Trading Corporation',
            'phone' => '880191234567',  // Corporate member phone
            'email' => 'corporate.test@example.com',
            'password' => Hash::make('password123'),
            'member_type' => 'corporate',
            'gender_type' => 'male',
            'status' => 'active',
            'merchant_id' => null,  // ✅ Not linked to any merchant
            'member_created_by' => 'admin',
            'referral_code' => $this->generateUniqueReferralCode(), // this function coming from MemberHelperTrait
            'country_id' => 67,
            'country_code' => '880',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        
        // Create Wallet for Corporate Member
        MemberWallet::create([
            'member_id' => $corporateMember->id,
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
        
        $this->command->info("   ✅ Corporate Member created: {$corporateUsername} - {$corporateMember->name}");
        $this->command->info("   🔗 Status: Independent (not linked to any merchant)");
        $this->command->info("   💼 Referral Code: {$corporateMember->referral_code}");
        $createdCount++;
        
        $this->command->info('');
        $this->command->info("✅ Total members created: {$createdCount} (30 general + 1 corporate)");
        $this->command->info('');
        $this->command->info('📋 Test Credentials (All members):');
        $this->command->info('   Password: password123');
        $this->command->info('');
        $this->command->info('📱 Sample Logins:');
        $this->command->info('   General Member: 0123456789 (Ahmad bin Abdullah)');
        $this->command->info('   General Member: 0167891234 (Siti Nurhaliza)');
        $this->command->info("   Corporate Member: {$corporateUsername} (Global Trading Corporation)");
        $this->command->info('   Password: password123 (for all)');
    }
}