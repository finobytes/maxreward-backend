<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\CommonFunctionHelper;

class CommunityTreeService
{
    /**
     * Place new member in balanced community tree using BFS algorithm
     * 
     * @param int $parentMemberId The referrer's member ID
     * @param int $newMemberId The new member to be placed
     * @return array Placement details
     */
    public function placeInCommunityTree($parentMemberId, $newMemberId)
    {
        try {
            DB::beginTransaction();

            // Get the parent member's referral tree
            $placementPosition = $this->findOptimalPlacementPosition($parentMemberId);

            // Create the referral relationship
            $referral = Referral::create([
                'parent_member_id' => $placementPosition['parent_id'],
                'child_member_id' => $newMemberId,
            ]);

            Log::info('Referrer created :::');

            DB::commit();

            return [
                'success' => true,
                'placement_parent_id' => $placementPosition['parent_id'],
                'level' => $placementPosition['level'],
                'referral_id' => $referral->id,
                'message' => "Member placed at level {$placementPosition['level']}"
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Community tree placement failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to place member in community tree',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Find optimal placement position using Breadth-First Search (BFS)
     * Ensures balanced tree growth across 30 levels
     * 
     * @param int $rootMemberId Starting point (referrer)
     * @return array ['parent_id' => int, 'level' => int]
     */
    private function findOptimalPlacementPosition($rootMemberId)
    {
        // Initialize BFS queue with root member at level 1
        $queue = [
            ['member_id' => $rootMemberId, 'level' => 1]
        ];
        
        $visited = [$rootMemberId => true];
        $maxLevel = 30;

        while (!empty($queue)) {
            $current = array_shift($queue);
            $currentMemberId = $current['member_id'];
            $currentLevel = $current['level'];

            // Don't go beyond level 30
            if ($currentLevel > $maxLevel) {
                break;
            }

            // Get direct children count for current member
            $childrenCount = Referral::where('parent_member_id', $currentMemberId)->count();

            // If this member has less than ideal children, place here
            // Ideal: Each member should have multiple downlines for balanced growth
            $idealChildrenPerLevel = $this->getIdealChildrenCount($currentLevel);
            
            if ($childrenCount < $idealChildrenPerLevel) {
                return [
                    'parent_id' => $currentMemberId,
                    'level' => $currentLevel
                ];
            }

            // Add this member's children to the queue for next level exploration
            $children = Referral::where('parent_member_id', $currentMemberId)
                ->pluck('child_member_id')
                ->toArray();

            foreach ($children as $childId) {
                if (!isset($visited[$childId]) && $currentLevel < $maxLevel) {
                    $queue[] = [
                        'member_id' => $childId,
                        'level' => $currentLevel + 1
                    ];
                    $visited[$childId] = true;
                }
            }
        }

        // Fallback: Place directly under root if tree is full or complex
        return [
            'parent_id' => $rootMemberId,
            'level' => 1
        ];
    }

    /**
     * Get ideal children count based on level
     * Early levels can have more children for faster growth
     * 
     * @param int $level Current tree level
     * @return int Ideal number of children
     */
    private function getIdealChildrenCount($level)
    {
        if ($level <= 3) {
            return 5; // Early levels: Allow more direct children
        } elseif ($level <= 10) {
            return 3; // Mid levels: Moderate growth
        } else {
            return 2; // Deep levels: Controlled growth
        }
    }

    /**
     * Get member's position in referrer's tree
     * 
     * @param int $memberId Member to locate
     * @param int $rootMemberId Tree root (referrer)
     * @return array|null ['level' => int, 'path' => array]
     */
    public function getMemberTreePosition($memberId, $rootMemberId)
    {
        $path = Referral::getReferralPath($memberId);
        
        // Find level relative to root
        foreach ($path as $index => $node) {
            if ($node['member_id'] == $rootMemberId) {
                return [
                    'level' => $index + 1,
                    'path' => array_slice($path, 0, $index + 1)
                ];
            }
        }

        return null; // Member not in this referrer's tree
    }

    /**
     * Get tree statistics for a member
     * 
     * @param int $memberId Root member
     * @return array Statistics
     */
    public function getTreeStatistics($memberId)
    {
        $tree = Referral::getReferralTree($memberId, 30);
        
        $stats = [
            'total_members' => 0,
            'by_level' => [],
            'deepest_level' => 0,
            'width_at_each_level' => []
        ];

        foreach ($tree as $level => $members) {
            $count = count($members);
            $stats['total_members'] += $count;
            $stats['by_level'][$level] = $count;
            $stats['deepest_level'] = max($stats['deepest_level'], $level);
            $stats['width_at_each_level'][$level] = $count;
        }

        return $stats;
    }

    /**
     * Validate tree integrity
     * Ensures no circular references or orphaned nodes
     * 
     * @param int $rootMemberId Root to validate from
     * @return array Validation results
     */
    public function validateTreeIntegrity($rootMemberId)
    {
        $issues = [];
        $tree = Referral::getReferralTree($rootMemberId, 30);
        $allMembers = [];

        foreach ($tree as $level => $members) {
            foreach ($members as $memberId) {
                // Check for duplicates (circular reference indicator)
                if (in_array($memberId, $allMembers)) {
                    $issues[] = "Duplicate member ID {$memberId} found at level {$level}";
                }
                $allMembers[] = $memberId;

                // Check if member actually exists
                if (!Member::find($memberId)) {
                    $issues[] = "Member ID {$memberId} at level {$level} does not exist";
                }
            }
        }

        return [
            'is_valid' => empty($issues),
            'total_members' => count($allMembers),
            'issues' => $issues
        ];
    }
}