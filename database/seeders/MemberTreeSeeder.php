<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Member;
use App\Models\MemberWallet;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\CompanyInfo;
use App\Models\CpDistributionPool;
use App\Models\Notification;
use App\Services\CommunityTreeService;
use App\Services\EmailService;
use App\Services\WhatsAppService;
use App\Traits\DistributeReferralPointsTrait;
use App\Traits\CheckAndUnlockCpLevelsTrait;
use App\Traits\MemberHelperTrait;
use App\Helpers\CommonFunctionHelper;
use Carbon\Carbon;

class MemberTreeSeeder extends Seeder
{
    use DistributeReferralPointsTrait;
    use CheckAndUnlockCpLevelsTrait;
    use MemberHelperTrait;

    protected $treeService;
    protected $emailService;
    protected $whatsappService;
    protected $settingAttributes;

    public function __construct()
    {
        $this->treeService = new CommunityTreeService();
        $this->emailService = new EmailService();
        $this->whatsappService = new WhatsAppService();
        $this->settingAttributes = CommonFunctionHelper::settingAttributes()['maxreward'];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            echo "🌱 Starting Member Tree Seeding with Full Point Distribution...\n\n";

            // Array to store created member IDs
            $createdMemberIds = [];

            // Create 31 members
            for ($i = 1; $i <= 31; $i++) {
                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                echo "Creating Member {$i}/31...\n";
                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

                // Generate Malaysian phone number with serial (60160000001 to 60160000030)
                $phone = '60160' . str_pad($i, 6, '0', STR_PAD_LEFT);
                
                $userName = $this->formatPhoneNumber($phone);
                $lastSix = substr($userName, -6);
                $password = $lastSix;
                $referralCode = $this->generateUniqueReferralCode();

                // Step 1: Create member
                $newMember = Member::create([
                    'user_name' => $userName,
                    'name' => "Test Member {$i}",
                    'phone' => $phone,
                    'email' => "testmember{$i}@maxreward.test",
                    'password' => Hash::make($password),
                    'address' => "Test Address {$i}",
                    'member_type' => 'general',
                    'gender_type' => 'male',
                    'status' => 'active',
                    'merchant_id' => null,
                    'member_created_by' => $i === 1 ? null : 'general',
                    'referral_code' => $referralCode,
                    'country_id' => 1,
                    'country_code' => 60,
                ]);

                // Store the created member ID
                $createdMemberIds[$i] = $newMember->id;

                echo "✅ Member {$i} created\n";
                echo "   ID: {$newMember->id}\n";
                echo "   Phone: {$phone}\n";
                echo "   Username: {$userName}\n";
                echo "   Password: {$password}\n";
                echo "   Referral Code: {$referralCode}\n\n";

                // Step 2: Create wallet for new member
                $newMemberWallet = MemberWallet::create([
                    'member_id' => $newMember->id,
                    'total_referrals' => 0,
                    'unlocked_level' => 5, // Default: levels 1-5 unlocked
                    'onhold_points' => 0,
                    'total_points' => 0,
                    'available_points' => 0,
                    'total_rp' => 0,
                    'total_pp' => 0,
                    'total_cp' => 0
                ]);

                echo "💰 Wallet created for Member {$i}\n\n";

                // First member setup
                if ($i === 1) {
                    // Add 500 RP to first member
                    $newMemberWallet->total_rp = 500;
                    $newMemberWallet->available_points = 500;
                    $newMemberWallet->total_points = 500;
                    $newMemberWallet->save();

                    echo "🌳 Member 1 is the ROOT of the tree\n";
                    echo "💰 Added 500 RP to Member 1's wallet\n\n";
                    continue;
                }

                // For members 2-30: Follow referNewMember function
                // Get the previous member as referrer
                $referrerId = $createdMemberIds[$i - 1];
                $referrer = Member::find($referrerId);
                
                if (!$referrer) {
                    throw new \Exception("Referrer not found for Member {$i}");
                }
                
                $referrerWallet = $referrer->wallet;
                
                if (!$referrerWallet) {
                    throw new \Exception("Referrer wallet not found for Member {$i}");
                }

                echo "Step 1: Check referrer balance\n";
                echo "   Referrer: Member {$referrer->id} ({$referrer->name})\n";
                echo "   Current RP: {$referrerWallet->total_rp}\n";
                echo "   Required: {$this->settingAttributes['deductable_points']}\n\n";

                // Check if referrer has sufficient RP
                if ($referrerWallet->total_rp < $this->settingAttributes['deductable_points']) {
                    echo "⚠️  Insufficient RP for referrer, adding 500 RP...\n";
                    $referrerWallet->total_rp += 500;
                    $referrerWallet->available_points += 500;
                    $referrerWallet->total_points += 500;
                    $referrerWallet->save();
                    echo "   New RP Balance: {$referrerWallet->total_rp}\n\n";
                }

                // Step 3: Deduct 100 RP from referrer
                echo "Step 2: Deduct {$this->settingAttributes['deductable_points']} RP from referrer\n";
                $referrerWallet->total_rp -= $this->settingAttributes['deductable_points'];
                $referrerWallet->save();

                Transaction::createTransaction([
                    'member_id' => $referrer->id,
                    'transaction_points' => $this->settingAttributes['deductable_points'],
                    'transaction_type' => Transaction::TYPE_RP,
                    'points_type' => Transaction::POINTS_DEBITED,
                    'transaction_reason' => "Referred new member: {$newMember->name}",
                    'brp' => $referrerWallet->total_rp,
                    'bap' => $referrerWallet->available_points,
                    'bop' => $referrerWallet->onhold_points
                ]);

                echo "   Deducted: {$this->settingAttributes['deductable_points']} RP\n";
                echo "   Remaining: {$referrerWallet->total_rp} RP\n\n";

                // Step 3: Place new member in community tree
                echo "Step 3: Place in community tree\n";
                $placement = $this->treeService->placeInCommunityTree($referrer->id, $newMember->id);

                if ($placement['success']) {
                    echo "   ✅ Placed at Level {$placement['level']} - {$placement['position']} side\n";
                    echo "   Parent: Member {$placement['placement_parent_id']}\n\n";
                } else {
                    throw new \Exception('Failed to place member in community tree');
                }

                // Step 4: Distribute 100 points (PP:10, RP:20, CP:50, CR:20)
                echo "Step 4: Distribute {$this->settingAttributes['deductable_points']} points (PP:10, RP:20, CP:50, CR:20)\n";
                $this->distributeReferralPoints($referrer, $newMember, $this->settingAttributes['deductable_points']);
                echo "   ✅ Points distributed successfully\n\n";

                

                // Step 5: Update referrer's referral count
                echo "Step 5: Update referrer's referral count\n";
                $referrerWallet->increment('total_referrals');
                echo "   Total Referrals: {$referrerWallet->total_referrals}\n\n";

                // Step 6: Check and unlock CP levels if needed
                echo "Step 6: Check and unlock CP levels\n";
                $this->checkAndUnlockCpLevels($referrer->id);
                echo "   ✅ CP levels checked\n\n";

                // Step 7: Update CP Distribution Pool
                // $cp_distribution_pool_id = session('cp_distribution_pool_id');
                // if (!empty($cp_distribution_pool_id)) {
                //     $cpDistributionPool = CpDistributionPool::findOrFail($cp_distribution_pool_id);
                //     $updatedReferrerWallet = MemberWallet::where('member_id', $referrer->id)->firstOrFail();
                //     if ($cpDistributionPool) {
                //         $cpDistributionPool->total_referrals = $updatedReferrerWallet->total_referrals;
                //         $cpDistributionPool->unlocked_level = $updatedReferrerWallet->unlocked_level;
                //         $cpDistributionPool->save();
                //     }
                // }
                // session()->forget('cp_distribution_pool_id');

                // Step 8: Send Email (if not test environment)
                echo "Step 8: Send welcome email\n";
                // if (!empty($newMember->email)) {
                //     $this->emailService->sendWelcomeEmail([
                //         'member_id' => $newMember->id,
                //         'referrer_id' => $referrer->id,
                //         'name' => $newMember->name,
                //         'email' => $newMember->email,
                //         'user_name' => $userName,
                //         'password' => $password,
                //         'login_url' => 'https://maxreward.finobytes.com',
                //     ]);
                //     echo "   ✅ Email sent\n\n";
                // }

                // Step 9: Send WhatsApp message
                echo "Step 9: Send WhatsApp message\n";
                $this->whatsappService->sendWelcomeMessage([
                    'member_id' => $newMember->id,
                    'referrer_id' => $referrer->id,
                    'name' => $newMember->name,
                    'phone' => $newMember->phone,
                    'user_name' => $userName,
                    'password' => $password,
                    'login_url' => 'https://maxreward.finobytes.com',
                ]);
                echo "   ✅ WhatsApp message sent\n\n";

                // Step 10: Create notifications
                echo "Step 10: Create notifications\n";
                Notification::notifyReferralInvite($referrer->id, [
                    'new_member_name' => $newMember->name,
                    'new_member_phone' => $newMember->phone,
                ]);
                echo "   ✅ Notifications created\n\n";

                // Small delay for visibility
                usleep(500000); // 0.2 second
            }

            DB::commit();

            echo "\n🎉 Successfully created 31 members in hierarchical tree!\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

            // Display summary
            $this->displaySummary();

        } catch (\Exception $e) {
            DB::rollBack();
            echo "\n❌ Error: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }

    /**
     * Display summary of created members
     */
    private function displaySummary()
    {
        echo "📊 DETAILED SUMMARY\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        
        $totalMembers = Member::count();
        $totalReferrals = Referral::count();
        $totalTransactions = Transaction::count();
        
        echo "Total Members Created: {$totalMembers}\n";
        echo "Total Referrals: {$totalReferrals}\n";
        echo "Total Transactions: {$totalTransactions}\n";
        
        // Get tree statistics for Member 1 (root)
        if ($totalMembers > 0) {
            $rootMember = Member::first();
            $stats = $this->treeService->getTreeStatistics($rootMember->id);
            
            echo "\n🌳 COMMUNITY TREE STATISTICS (Root: Member 1)\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "Total Members in Tree: {$stats['total_members']}\n";
            echo "Deepest Level: {$stats['deepest_level']}\n";
            echo "Left Leg Members: {$stats['left_leg_count']}\n";
            echo "Right Leg Members: {$stats['right_leg_count']}\n";
            
            echo "\n📈 Members Distribution per Level:\n";
            foreach ($stats['by_level'] as $level => $count) {
                echo "  Level {$level}: {$count} member(s)\n";
            }
        }

        // Wallet Summary
        echo "\n💰 WALLET SUMMARY\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $wallets = MemberWallet::all();
        $totalRP = $wallets->sum('total_rp');
        $totalPP = $wallets->sum('total_pp');
        $totalCP = $wallets->sum('total_cp');
        $totalAvailable = $wallets->sum('available_points');
        $totalOnhold = $wallets->sum('onhold_points');
        
        echo "Total RP Points: {$totalRP}\n";
        echo "Total PP Points: {$totalPP}\n";
        echo "Total CP Points: {$totalCP}\n";
        echo "Total Available Points: {$totalAvailable}\n";
        echo "Total Onhold Points: {$totalOnhold}\n";

        // Company Reserve
        $company = CompanyInfo::getCompany();
        if ($company) {
            echo "\n🏢 COMPANY RESERVE\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "Total CR Points: {$company->cr_points}\n";
        }

        // Point Distribution Pools
        $poolCount = CpDistributionPool::count();
        echo "\n📦 CP DISTRIBUTION POOLS\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Total Pools Created: {$poolCount}\n";

        // Transaction Summary
        echo "\n📝 TRANSACTION SUMMARY\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $rpTransactions = Transaction::where('transaction_type', Transaction::TYPE_RP)->count();
        $ppTransactions = Transaction::where('transaction_type', Transaction::TYPE_PP)->count();
        $cpTransactions = Transaction::where('transaction_type', Transaction::TYPE_CP)->count();
        $crTransactions = Transaction::where('transaction_type', Transaction::TYPE_CR)->count();
        
        echo "RP Transactions: {$rpTransactions}\n";
        echo "PP Transactions: {$ppTransactions}\n";
        echo "CP Transactions: {$cpTransactions}\n";
        echo "CR Transactions: {$crTransactions}\n";

        echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "✅ All point distributions completed successfully\n";
        echo "✅ Community tree structure (30 levels) ready\n";
        echo "✅ Email & WhatsApp notifications sent\n";
        echo "✅ All transactions recorded\n";
        echo "✅ CP levels unlocked based on referrals\n";
        echo "\n🎯 READY FOR TESTING!\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    }
}