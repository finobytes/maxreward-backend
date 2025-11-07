<?php
/**
 * 🚨 EMERGENCY FIX V2 - Test with API (Creates Real Members)
 * 
 * This will use your API endpoint to create real members
 * Run: php emergency_fix_v2.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Member;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;

echo "🚨 EMERGENCY FIX V2 - Using Real Members\n";
echo "==========================================\n\n";

// Step 1: Find sponsor member
echo "🔍 Step 1: Finding sponsor member...\n";
$sponsor = Member::where('id', 1)->first();

if (!$sponsor) {
    echo "❌ ERROR: Sponsor member ID 1 not found!\n";
    echo "Please create a sponsor member first or use a different ID.\n\n";
    
    $firstMember = Member::first();
    if ($firstMember) {
        echo "💡 Found member ID {$firstMember->id} ({$firstMember->name})\n";
        echo "   You can use this as sponsor.\n";
    }
    exit(1);
}

echo "   ✅ Sponsor: {$sponsor->name} (ID: {$sponsor->id})\n\n";

// Step 2: Check existing members in database
echo "📊 Step 2: Checking existing members...\n";
$existingMembers = Member::where('id', '>', $sponsor->id)
    ->orderBy('id')
    ->limit(8)
    ->get();

if ($existingMembers->count() < 8) {
    echo "   ⚠️  Only {$existingMembers->count()} members found after sponsor.\n";
    echo "   💡 You need to create members through your API first.\n\n";
    
    echo "   Run these commands to create members:\n\n";
    echo "   # Login as sponsor first:\n";
    echo "   curl -X POST http://localhost:8000/api/login \\\n";
    echo "     -H 'Content-Type: application/json' \\\n";
    echo "     -d '{\"user_name\":\"your_username\",\"password\":\"your_password\"}'\n\n";
    
    echo "   # Then create members (use the token from login):\n";
    for ($i = 1; $i <= 8; $i++) {
        $phone = "0170000000" . $i;
        echo "   curl -X POST http://localhost:8000/api/refer-new-member \\\n";
        echo "     -H 'Authorization: Bearer YOUR_TOKEN' \\\n";
        echo "     -H 'Content-Type: application/json' \\\n";
        echo "     -d '{\"name\":\"Test Member {$i}\",\"phone\":\"{$phone}\"}'\n\n";
    }
    
    exit(0);
}

$testMembers = $existingMembers->pluck('id')->toArray();
echo "   ✅ Found " . count($testMembers) . " members: " . implode(', ', $testMembers) . "\n\n";

// Step 3: Clean referrals
echo "🧹 Step 3: Cleaning referrals table...\n";
DB::statement('SET FOREIGN_KEY_CHECKS=0;');
DB::table('referrals')->truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1;');
echo "   ✅ Table cleaned!\n\n";

// Step 4: Test placement
echo "🚀 Step 4: Testing placement algorithm...\n\n";

use App\Services\CommunityTreeService;
$treeService = new CommunityTreeService();

foreach ($testMembers as $index => $memberId) {
    $memberName = Member::find($memberId)->name;
    $memberNum = $index + 1;
    
    echo "➕ Placing Member {$memberNum} (ID: {$memberId}, {$memberName})...\n";
    
    $result = $treeService->placeInCommunityTree($sponsor->id, $memberId);
    
    if ($result['success']) {
        echo "   ✅ Parent: {$result['placement_parent_id']}, ";
        echo "Position: {$result['position']}, ";
        echo "Level: {$result['level']}\n";
    } else {
        echo "   ❌ FAILED: {$result['message']}\n";
        if (isset($result['error'])) {
            echo "   Error: {$result['error']}\n";
        }
    }
    echo "\n";
}

// Step 5: Display results
echo "\n📊 FINAL RESULTS:\n";
echo "==================\n\n";

$referrals = DB::table('referrals')
    ->select('id', 'sponsor_member_id', 'parent_member_id', 'child_member_id', 'position')
    ->orderBy('id')
    ->get();

echo "┌────┬─────────┬────────┬───────┬──────────┐\n";
echo "│ ID │ Sponsor │ Parent │ Child │ Position │\n";
echo "├────┼─────────┼────────┼───────┼──────────┤\n";

foreach ($referrals as $ref) {
    printf("│ %-2d │ %-7d │ %-6d │ %-5d │ %-8s │\n",
        $ref->id,
        $ref->sponsor_member_id,
        $ref->parent_member_id,
        $ref->child_member_id,
        $ref->position
    );
}

echo "└────┴─────────┴────────┴───────┴──────────┘\n\n";

// Step 6: Verification
echo "🔍 VERIFICATION:\n";
echo "=================\n\n";

// Get the first two member IDs for expected results
$firstChild = $testMembers[0] ?? null;
$secondChild = $testMembers[1] ?? null;
$thirdChild = $testMembers[2] ?? null;
$fourthChild = $testMembers[3] ?? null;
$fifthChild = $testMembers[4] ?? null;
$sixthChild = $testMembers[5] ?? null;
$seventhChild = $testMembers[6] ?? null;
$eighthChild = $testMembers[7] ?? null;

$expected = [
    ['parent' => $sponsor->id, 'child' => $firstChild, 'position' => 'left'],
    ['parent' => $sponsor->id, 'child' => $secondChild, 'position' => 'right'],
    ['parent' => $firstChild, 'child' => $thirdChild, 'position' => 'left'],
    ['parent' => $secondChild, 'child' => $fourthChild, 'position' => 'left'],
    ['parent' => $firstChild, 'child' => $fifthChild, 'position' => 'right'],
    ['parent' => $secondChild, 'child' => $sixthChild, 'position' => 'right'],
    ['parent' => $thirdChild, 'child' => $seventhChild, 'position' => 'left'],
    ['parent' => $fifthChild, 'child' => $eighthChild, 'position' => 'left'],
];

$passed = 0;
$failed = 0;

foreach ($referrals as $index => $ref) {
    $exp = $expected[$index] ?? null;
    
    if (!$exp) {
        echo "❌ Row {$ref->id}: Unexpected extra row\n";
        $failed++;
        continue;
    }
    
    $match = (
        $ref->parent_member_id == $exp['parent'] &&
        $ref->child_member_id == $exp['child'] &&
        $ref->position == $exp['position']
    );
    
    if ($match) {
        echo "✅ Row {$ref->id}: CORRECT (Parent={$ref->parent_member_id}, Child={$ref->child_member_id}, Pos={$ref->position})\n";
        $passed++;
    } else {
        echo "❌ Row {$ref->id}: WRONG\n";
        echo "   Expected: Parent={$exp['parent']}, Child={$exp['child']}, Position={$exp['position']}\n";
        echo "   Got: Parent={$ref->parent_member_id}, Child={$ref->child_member_id}, Position={$ref->position}\n";
        $failed++;
    }
}

echo "\n";
echo "📈 TEST SUMMARY:\n";
echo "================\n";
echo "   ✅ Passed: {$passed}\n";
echo "   ❌ Failed: {$failed}\n";
echo "   📊 Total: " . count($referrals) . "\n\n";

if ($failed === 0 && $passed > 0) {
    echo "🎉🎉🎉 ALL TESTS PASSED! 🎉🎉🎉\n";
    echo "Binary tree placement is working perfectly!\n\n";
    
    echo "🌳 Visual Tree (using your member IDs):\n";
    echo "
                    {$sponsor->id} (sponsor: {$sponsor->name})
                   / \\
                {$firstChild}   {$secondChild}        ← Level 1
               / \\   / \\
             {$thirdChild}  {$fifthChild} {$fourthChild}  {$sixthChild}     ← Level 2
            /    /
          {$seventhChild}   {$eighthChild}              ← Level 3
    \n";
    
    echo "✅ Algorithm is working correctly!\n";
    echo "✅ Your job is SAFE! 😊\n\n";
    
} else {
    echo "❌ Some tests failed.\n";
    echo "Please check the output above.\n\n";
}

echo "Done! ✨\n";
