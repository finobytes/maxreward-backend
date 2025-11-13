<?php

namespace App\Helpers;

use App\Models\Setting;
use App\Models\Referral;
use App\Services\CommunityTreeService;
use App\Models\Member;

class CommonFunctionHelper
{
    public static function settingAttributes() : array
    {
        $setting = Setting::first();
        return $setting ? $setting->setting_attribute : [];
    }

    public static function sponsoredMembers($member_id)
    {
        $sponsored = Referral::with('childMember.wallet')
            ->where('sponsor_member_id', $member_id)
            ->orderBy('created_at', 'desc')
            ->paginate(20); 

            // Extract only the childMember relation from each referral
            $sponsored->getCollection()->transform(function ($referral) {
                return $referral->childMember;
            });

            return $sponsored;
    }


    public static function getMemberCommunityTree($member_id, CommunityTreeService $treeService)
    {
        // Get tree structure with positions
        $treeStructure = Referral::getBinaryTreeStructure($member_id, 30);
        $statistics = $treeService->getTreeStatistics($member_id);

        // Format tree with member details and positions
        $formattedTree = [];
        
        foreach ($treeStructure as $level => $levelNodes) {
            $levelData = [
                'level' => $level,
                'node_count' => count($levelNodes),
                'nodes' => []
            ];

            foreach ($levelNodes as $node) {
                $parentMember = Member::find($node['parent_id']);
                $leftChildMember = $node['left_child'] ? Member::find($node['left_child']) : null;
                $rightChildMember = $node['right_child'] ? Member::find($node['right_child']) : null;
                
                $nodeInfo = [
                    'parent' => [
                        'id' => $parentMember->id,
                        'name' => $parentMember->name,
                        'user_name' => $parentMember->user_name,
                        'phone' => $parentMember->phone,
                        'referral_code' => $parentMember->referral_code,
                        'image' => $parentMember->image,
                    ],
                    'left_child' => $leftChildMember ? [
                        'id' => $leftChildMember->id,
                        'name' => $leftChildMember->name,
                        'user_name' => $leftChildMember->user_name,
                        'phone' => $leftChildMember->phone,
                        'referral_code' => $leftChildMember->referral_code,
                        'image' => $leftChildMember->image,
                        'position' => 'left'
                    ] : null,
                    'right_child' => $rightChildMember ? [
                        'id' => $rightChildMember->id,
                        'name' => $rightChildMember->name,
                        'user_name' => $rightChildMember->user_name,
                        'phone' => $rightChildMember->phone,
                        'referral_code' => $rightChildMember->referral_code,
                        'image' => $rightChildMember->image,
                        'position' => 'right'
                    ] : null
                ];
                
                $levelData['nodes'][] = $nodeInfo;
            }

            $formattedTree[] = $levelData;
        }

        return [
            'tree' => $formattedTree,
            'statistics' => $statistics
        ];

    }
}