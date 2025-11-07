<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommunityTreeService
{
    /**
     * 🎯 Place new member in BINARY community tree using LEFT-PRIORITY-FIRST algorithm
     */
    public function placeInCommunityTree($sponsorMemberId, $newMemberId)
    {
        try {
            DB::beginTransaction();

            Log::info("🌳 Starting Binary Tree Placement", [
                'sponsor_id' => $sponsorMemberId,
                'new_member_id' => $newMemberId
            ]);

            // Find optimal placement
            $placementPosition = $this->findCorrectPlacement($sponsorMemberId);

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
                'sponsor' => $sponsorMemberId,
                'parent' => $placementPosition['parent_id'],
                'child' => $newMemberId,
                'position' => $placementPosition['position'],
                'level' => $placementPosition['level']
            ]);

            DB::commit();

            return [
                'success' => true,
                'sponsor_member_id' => $sponsorMemberId,
                'placement_parent_id' => $placementPosition['parent_id'],
                'position' => $placementPosition['position'],
                'level' => $placementPosition['level'],
                'referral_id' => $referral->id,
                'message' => "Member {$newMemberId} placed at level {$placementPosition['level']} - {$placementPosition['position']} side under parent {$placementPosition['parent_id']}"
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Binary tree placement failed: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return [
                'success' => false,
                'message' => 'Failed to place member in binary tree',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 🔍 CORRECT: Find placement using Level-by-Level LEFT-FIRST then RIGHT
     * 
     * Strategy:
     * 1. Build complete level map using BFS
     * 2. Check ALL nodes level-by-level for empty LEFT positions
     * 3. Then check ALL nodes level-by-level for empty RIGHT positions
     */
    private function findCorrectPlacement($rootMemberId)
    {
        Log::info("🔍 Starting placement search", ['root' => $rootMemberId]);
        
        // Step 1: Build level map using BFS
        $levelMap = $this->buildLevelMap($rootMemberId);
        
        Log::info("📊 Level map built", [
            'total_levels' => count($levelMap),
            'structure' => array_map('count', $levelMap)
        ]);
        
        // Step 2: Find first empty LEFT position (level by level)
        foreach ($levelMap as $level => $nodes) {
            Log::info("🔍 Checking level {$level} for LEFT positions", [
                'nodes' => $nodes
            ]);
            
            foreach ($nodes as $nodeId) {
                if (!Referral::isLeftFilled($nodeId)) {
                    Log::info("✅ Found empty LEFT at node {$nodeId}");
                    
                    return [
                        'parent_id' => $nodeId,
                        'position' => 'left',
                        'level' => $level + 1
                    ];
                }
            }
        }
        
        // Step 3: All LEFT filled, check RIGHT positions (level by level)
        Log::info("🔄 All LEFT positions filled, checking RIGHT");
        
        foreach ($levelMap as $level => $nodes) {
            Log::info("🔍 Checking level {$level} for RIGHT positions", [
                'nodes' => $nodes
            ]);
            
            foreach ($nodes as $nodeId) {
                if (!Referral::isRightFilled($nodeId)) {
                    Log::info("✅ Found empty RIGHT at node {$nodeId}");
                    
                    return [
                        'parent_id' => $nodeId,
                        'position' => 'right',
                        'level' => $level + 1
                    ];
                }
            }
        }
        
        // Fallback
        Log::warning("⚠️ No empty position found");
        
        return [
            'parent_id' => $rootMemberId,
            'position' => 'left',
            'level' => 1
        ];
    }

    /**
     * Build level map using proper BFS traversal
     * 
     * @param int $rootId Root member ID
     * @return array Level map [level => [node_ids]]
     */
    private function buildLevelMap($rootId)
    {
        $levelMap = [
            0 => [$rootId]
        ];
        
        $maxDepth = 30;
        
        // Build tree level by level
        for ($level = 0; $level < $maxDepth; $level++) {
            if (!isset($levelMap[$level]) || empty($levelMap[$level])) {
                break;
            }
            
            $nextLevelNodes = [];
            
            // Process all nodes in current level
            foreach ($levelMap[$level] as $nodeId) {
                $children = Referral::getChildren($nodeId);
                
                // Add children to next level (left first, then right)
                if ($children['left']) {
                    $nextLevelNodes[] = $children['left'];
                }
                if ($children['right']) {
                    $nextLevelNodes[] = $children['right'];
                }
            }
            
            // Add next level to map if it has nodes
            if (!empty($nextLevelNodes)) {
                $levelMap[$level + 1] = $nextLevelNodes;
            }
        }
        
        Log::info("✅ Level map complete", [
            'levels' => count($levelMap),
            'details' => $levelMap
        ]);
        
        return $levelMap;
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

        $leftLegRoot = Referral::getLeftChildId($memberId);
        $rightLegRoot = Referral::getRightChildId($memberId);

        if ($leftLegRoot) {
            $leftTree = $this->getBinaryTree($leftLegRoot, 30);
            foreach ($leftTree as $members) {
                $stats['left_leg_count'] += count($members);
            }
            $stats['left_leg_count']++;
        }

        if ($rightLegRoot) {
            $rightTree = $this->getBinaryTree($rightLegRoot, 30);
            foreach ($rightTree as $members) {
                $stats['right_leg_count'] += count($members);
            }
            $stats['right_leg_count']++;
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