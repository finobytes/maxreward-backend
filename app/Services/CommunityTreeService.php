<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommunityTreeService
{
    /**
     * 🎯 Place new member in BINARY community tree using Level-by-Level, Left-First BFS
     */
    public function placeInCommunityTree($sponsorMemberId, $newMemberId)
    {
        try {
            DB::beginTransaction();

            Log::info("🌳 Starting Level-by-Level Left-First Binary Tree Placement", [
                'sponsor_id' => $sponsorMemberId,
                'new_member_id' => $newMemberId
            ]);

            // Find optimal placement position
            $placementPosition = $this->findLevelByLevelLeftFirstPlacement($sponsorMemberId);

            Log::info("📍 Found placement position", $placementPosition);

            // Create the referral relationship
            $referral = Referral::create([
                'sponsor_member_id' => $sponsorMemberId,
                'parent_member_id' => $placementPosition['parent_id'],
                'child_member_id' => $newMemberId,
                'position' => $placementPosition['position'],
            ]);

            Log::info("✅ Referral created successfully", [
                'referral_id' => $referral->id,
                'position' => $placementPosition['position'],
                'level' => $placementPosition['level']
            ]);

            DB::commit();

            return [
                'success' => true,
                'placement_parent_id' => $placementPosition['parent_id'],
                'position' => $placementPosition['position'],
                'level' => $placementPosition['level'],
                'referral_id' => $referral->id,
                'message' => "Member placed at level {$placementPosition['level']} - {$placementPosition['position']} side"
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Binary tree placement failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to place member in binary tree',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 🔍 Level-by-Level, Left-First Placement Algorithm
     * 
     * Steps:
     * 1. Build complete level-by-level tree
     * 2. For each level, check ALL nodes for empty LEFT
     * 3. Then for same level, check ALL nodes for empty RIGHT
     * 4. Move to next level
     */
    private function findLevelByLevelLeftFirstPlacement($rootMemberId)
    {
        $maxLevel = 30;
        
        // Build complete level-by-level tree
        $levels = [];
        $levels[0] = [$rootMemberId];
        
        for ($level = 0; $level < $maxLevel; $level++) {
            if (!isset($levels[$level]) || empty($levels[$level])) {
                break;
            }
            
            $nextLevel = [];
            
            // First pass: Check all nodes in current level for LEFT positions
            foreach ($levels[$level] as $parentId) {
                if (!Referral::isLeftFilled($parentId)) {
                    Log::info("✅ Found empty LEFT at level {$level}", [
                        'parent_id' => $parentId,
                        'level' => $level + 1
                    ]);
                    
                    return [
                        'parent_id' => $parentId,
                        'position' => 'left',
                        'level' => $level + 1
                    ];
                }
                
                // Collect children for next level
                $children = Referral::getChildren($parentId);
                if ($children['left']) {
                    $nextLevel[] = $children['left'];
                }
                if ($children['right']) {
                    $nextLevel[] = $children['right'];
                }
            }
            
            // Second pass: Check all nodes in current level for RIGHT positions
            foreach ($levels[$level] as $parentId) {
                if (!Referral::isRightFilled($parentId)) {
                    Log::info("✅ Found empty RIGHT at level {$level}", [
                        'parent_id' => $parentId,
                        'level' => $level + 1
                    ]);
                    
                    return [
                        'parent_id' => $parentId,
                        'position' => 'right',
                        'level' => $level + 1
                    ];
                }
            }
            
            // Store next level for continuation
            if (!empty($nextLevel)) {
                $levels[$level + 1] = $nextLevel;
            }
        }
        
        // Fallback: If tree is full up to level 30
        Log::warning("⚠️ Tree full up to level {$maxLevel}, using root fallback");
        
        return [
            'parent_id' => $rootMemberId,
            'position' => 'left',
            'level' => 1
        ];
    }

    /**
     * 📊 Get binary tree statistics
     */
    public function getTreeStatistics($memberId)
    {
        $tree = $this->getBinaryTree($memberId, 30);
        
        $stats = [
            'total_members' => 0,
            'by_level' => [],
            'deepest_level' => 0,
            'left_leg_count' => 0,
            'right_leg_count' => 0,
            'width_at_each_level' => []
        ];

        foreach ($tree as $level => $members) {
            $count = count($members);
            $stats['total_members'] += $count;
            $stats['by_level'][$level] = $count;
            $stats['deepest_level'] = max($stats['deepest_level'], $level);
            $stats['width_at_each_level'][$level] = $count;
        }

        // Calculate left vs right leg members
        $leftLegRoot = Referral::getLeftChildId($memberId);
        $rightLegRoot = Referral::getRightChildId($memberId);

        if ($leftLegRoot) {
            $leftTree = $this->getBinaryTree($leftLegRoot, 30);
            foreach ($leftTree as $members) {
                $stats['left_leg_count'] += count($members);
            }
            $stats['left_leg_count']++; // Include the root of left leg
        }

        if ($rightLegRoot) {
            $rightTree = $this->getBinaryTree($rightLegRoot, 30);
            foreach ($rightTree as $members) {
                $stats['right_leg_count'] += count($members);
            }
            $stats['right_leg_count']++; // Include the root of right leg
        }

        return $stats;
    }

    /**
     * 🌲 Get complete binary tree structure
     */
    private function getBinaryTree($memberId, $maxLevel = 30)
    {
        $tree = [];
        $currentLevel = [$memberId];
        
        for ($level = 1; $level <= $maxLevel; $level++) {
            $nextLevel = [];
            
            foreach ($currentLevel as $parentId) {
                $children = Referral::getChildren($parentId);
                
                if ($children['left']) {
                    $nextLevel[] = $children['left'];
                }
                if ($children['right']) {
                    $nextLevel[] = $children['right'];
                }
            }
            
            if (empty($nextLevel)) {
                break;
            }
            
            $tree[$level] = $nextLevel;
            $currentLevel = $nextLevel;
        }
        
        return $tree;
    }

    /**
     * Get member's position in tree
     */
    public function getMemberTreePosition($memberId, $rootMemberId)
    {
        $referral = Referral::where('child_member_id', $memberId)->first();
        
        if (!$referral) {
            return null;
        }

        $path = [];
        $currentMemberId = $memberId;
        $level = 0;
        
        while ($currentMemberId && $level < 30) {
            $ref = Referral::where('child_member_id', $currentMemberId)->first();
            
            if (!$ref) {
                break;
            }
            
            $level++;
            $path[] = [
                'member_id' => $ref->parent_member_id,
                'position' => $ref->position,
                'level' => $level
            ];
            
            if ($ref->parent_member_id == $rootMemberId) {
                return [
                    'level' => $level,
                    'position' => $ref->position,
                    'path' => $path
                ];
            }
            
            $currentMemberId = $ref->parent_member_id;
        }

        return null;
    }

    
    /**
     * Validate tree integrity
     */

    public function validateTreeIntegrity($rootMemberId)
    {
        $issues = [];
        $tree = $this->getBinaryTree($rootMemberId, 30);
        $allMembers = [];

        foreach ($tree as $level => $members) {
            foreach ($members as $memberId) {
                if (in_array($memberId, $allMembers)) {
                    $issues[] = "Duplicate member ID {$memberId} at level {$level}";
                }
                $allMembers[] = $memberId;

                if (!Member::find($memberId)) {
                    $issues[] = "Member ID {$memberId} at level {$level} does not exist";
                }

                $childrenCount = Referral::getChildrenCount($memberId);
                if ($childrenCount > 2) {
                    $issues[] = "Member ID {$memberId} has {$childrenCount} children (max 2 allowed)";
                }
            }
        }

        return [
            'is_valid' => empty($issues),
            'total_members' => count($allMembers),
            'issues' => $issues,
            'max_level' => count($tree)
        ];
    }

     /**
     * Get tree balance report
     */
    public function getTreeBalance($memberId)
    {
        $stats = $this->getTreeStatistics($memberId);
        
        $leftCount = $stats['left_leg_count'];
        $rightCount = $stats['right_leg_count'];
        $total = $leftCount + $rightCount;
        
        return [
            'left_leg_members' => $leftCount,
            'right_leg_members' => $rightCount,
            'total_members' => $total,
            'left_percentage' => $total > 0 ? round(($leftCount / $total) * 100, 2) : 0,
            'right_percentage' => $total > 0 ? round(($rightCount / $total) * 100, 2) : 0,
            'is_balanced' => abs($leftCount - $rightCount) <= 1,
            'difference' => abs($leftCount - $rightCount),
        ];
    }
}