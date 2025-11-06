<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommunityTreeService
{
    /**
     * 🎯 Place new member in BINARY community tree using BFS algorithm
     * 
     * Binary Rule: Maximum 2 children per parent (LEFT & RIGHT only)
     * Tree grows level by level, left to right
     * Maximum 30 levels supported
     * 
     * @param int $sponsorMemberId The referrer/sponsor member ID
     * @param int $newMemberId The new member to be placed
     * @return array Placement details
     */
    public function placeInCommunityTree($sponsorMemberId, $newMemberId)
    {
        try {
            DB::beginTransaction();

            Log::info("🌳 Starting Binary Tree Placement", [
                'sponsor_id' => $sponsorMemberId,
                'new_member_id' => $newMemberId
            ]);

            // Find optimal placement position using BFS
            $placementPosition = $this->findBinaryPlacementPosition($sponsorMemberId);

            Log::info("📍 Found placement position", $placementPosition);

            // Create the referral relationship with position
            $referral = Referral::create([
                'sponsor_member_id' => $sponsorMemberId,  // ⭐ ADD THIS - যে refer করেছে
                'parent_member_id' => $placementPosition['parent_id'], // tree তে কার নিচে
                'child_member_id' => $newMemberId,
                'position' => $placementPosition['position'], // 'left' or 'right'
            ]);

            Log::info("✅ Referral created successfully", [
                'referral_id' => $sponsorMemberId,
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
     * 🔍 Find optimal placement position using BFS (Breadth-First Search)
     * 
     * Binary Tree Rule:
     * - Each parent can have MAXIMUM 2 children (left & right)
     * - Tree grows level by level, left to right
     * - Search order: Check left first, then right
     * - Maximum 30 levels
     * 
     * Algorithm:
     * 1. Start with sponsor at level 1
     * 2. Check if left position empty → place there
     * 3. Check if right position empty → place there
     * 4. If both filled, add children to queue
     * 5. Move to next level and repeat
     * 
     * @param int $rootMemberId Starting point (sponsor/referrer)
     * @return array ['parent_id' => int, 'position' => 'left'|'right', 'level' => int]
     */
    private function findBinaryPlacementPosition($rootMemberId)
    {
        // Initialize BFS queue with root member at level 1
        $queue = [
            [
                'member_id' => $rootMemberId, 
                'level' => 1
            ]
        ];
        
        $visited = [$rootMemberId => true];
        $maxLevel = 30;

        Log::info("🔍 Starting BFS search for empty position");

        while (!empty($queue)) {
            // Get first item from queue (FIFO)
            $current = array_shift($queue);
            $currentMemberId = $current['member_id'];
            $currentLevel = $current['level'];

            Log::info("🔎 Checking member", [
                'member_id' => $currentMemberId,
                'level' => $currentLevel
            ]);

            // Don't go beyond level 30
            if ($currentLevel > $maxLevel) {
                Log::warning("⚠️ Reached max level {$maxLevel}, stopping search");
                break;
            }

            // ===================================
            // STEP 1: Check LEFT position
            // ===================================
            $hasLeftChild = Referral::isLeftFilled($currentMemberId);
            
            if (!$hasLeftChild) {
                Log::info("✅ Found empty LEFT position", [
                    'parent_id' => $currentMemberId,
                    'level' => $currentLevel
                ]);
                
                return [
                    'parent_id' => $currentMemberId,
                    'position' => 'left',
                    'level' => $currentLevel
                ];
            }

            // ===================================
            // STEP 2: Check RIGHT position
            // ===================================
            $hasRightChild = Referral::isRightFilled($currentMemberId);
            
            if (!$hasRightChild) {
                Log::info("✅ Found empty RIGHT position", [
                    'parent_id' => $currentMemberId,
                    'level' => $currentLevel
                ]);
                
                return [
                    'parent_id' => $currentMemberId,
                    'position' => 'right',
                    'level' => $currentLevel
                ];
            }

            // ===================================
            // STEP 3: Both positions filled
            // Add children to queue for next level
            // ===================================
            $leftChildId = Referral::getLeftChildId($currentMemberId);
            $rightChildId = Referral::getRightChildId($currentMemberId);

            // Add left child to queue
            if ($leftChildId && !isset($visited[$leftChildId]) && $currentLevel < $maxLevel) {
                $queue[] = [
                    'member_id' => $leftChildId,
                    'level' => $currentLevel + 1
                ];
                $visited[$leftChildId] = true;
                
                Log::info("➕ Added LEFT child to queue", [
                    'child_id' => $leftChildId,
                    'next_level' => $currentLevel + 1
                ]);
            }

            // Add right child to queue
            if ($rightChildId && !isset($visited[$rightChildId]) && $currentLevel < $maxLevel) {
                $queue[] = [
                    'member_id' => $rightChildId,
                    'level' => $currentLevel + 1
                ];
                $visited[$rightChildId] = true;
                
                Log::info("➕ Added RIGHT child to queue", [
                    'child_id' => $rightChildId,
                    'next_level' => $currentLevel + 1
                ]);
            }
        }

        // Fallback: If tree is completely full up to level 30
        // This should rarely happen
        Log::warning("⚠️ Tree full or search exhausted, using root fallback");
        
        return [
            'parent_id' => $rootMemberId,
            'position' => 'left', // Default to left
            'level' => 1
        ];
    }

    /**
     * 📊 Get binary tree statistics for a member
     * 
     * @param int $memberId Root member
     * @return array Statistics including left/right leg counts
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
     * 
     * @param int $memberId Root member
     * @param int $maxLevel Maximum depth
     * @return array Tree structure [level => [member_ids]]
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
     * 🔍 Get member's position in tree
     * 
     * @param int $memberId Member to locate
     * @param int $rootMemberId Tree root
     * @return array|null Position info
     */
    public function getMemberTreePosition($memberId, $rootMemberId)
    {
        // Get parent referral
        $referral = Referral::where('child_member_id', $memberId)->first();
        
        if (!$referral) {
            return null;
        }

        // Build path to root
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
     * ✅ Validate binary tree integrity
     * Checks for:
     * - Duplicate members
     * - Non-existent members
     * - Parents with more than 2 children
     * - Circular references
     * 
     * @param int $rootMemberId Root to validate from
     * @return array Validation results
     */
    public function validateTreeIntegrity($rootMemberId)
    {
        $issues = [];
        $tree = $this->getBinaryTree($rootMemberId, 30);
        $allMembers = [];

        foreach ($tree as $level => $members) {
            foreach ($members as $memberId) {
                // Check for duplicates (indicates circular reference)
                if (in_array($memberId, $allMembers)) {
                    $issues[] = "Duplicate member ID {$memberId} at level {$level}";
                }
                $allMembers[] = $memberId;

                // Check if member exists
                if (!Member::find($memberId)) {
                    $issues[] = "Member ID {$memberId} at level {$level} does not exist";
                }

                // Check if parent has more than 2 children
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
     * 📈 Get tree balance report
     * Shows if tree is balanced between left and right legs
     * 
     * @param int $memberId Root member
     * @return array Balance report
     */
    public function getTreeBalance($memberId)
    {
        $stats = $this->getTreeStatistics($memberId);
        
        $leftCount = $stats['left_leg_count'];
        $rightCount = $stats['right_leg_count'];
        $total = $leftCount + $rightCount;
        
        $balance = [
            'left_leg_members' => $leftCount,
            'right_leg_members' => $rightCount,
            'total_members' => $total,
            'left_percentage' => $total > 0 ? round(($leftCount / $total) * 100, 2) : 0,
            'right_percentage' => $total > 0 ? round(($rightCount / $total) * 100, 2) : 0,
            'is_balanced' => abs($leftCount - $rightCount) <= 1,
            'difference' => abs($leftCount - $rightCount),
        ];
        
        return $balance;
    }
}