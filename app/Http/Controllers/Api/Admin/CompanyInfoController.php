<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Member;
use App\Models\Merchant;
// use App\Models\Transaction;

class CompanyInfoController extends Controller
{
    /**
     * Update company information
     * Admin only endpoint
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'logo' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Update or create company info
        $company = CompanyInfo::updateCompanyInfo($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Company information updated successfully',
            'data' => [
                'company' => $company
            ]
        ]);
    }

    /**
     * Get company CR points (Admin only)
     * Shows company reserve points and statistics
     */
    public function getCrPoints()
    {
        $company = CompanyInfo::first();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company information not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'company_name' => $company->name,
                'cr_points' => $company->cr_points,
                'formatted_cr_point' => $company->formatted_cr_point,
                'last_updated' => $company->updated_at,
            ]
        ]);
    }

    /**
     * Get full company details including CR points (Admin only)
     */
    public function getFullDetails()
    {
        $company = CompanyInfo::first();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company information not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'address' => $company->address,
                    'phone' => $company->phone,
                    'email' => $company->email,
                    'logo' => $company->logo,
                    'cr_points' => $company->cr_points,
                    'formatted_cr_point' => $company->formatted_cr_point,
                    'created_at' => $company->created_at,
                    'updated_at' => $company->updated_at,
                ]
            ]
        ]);
    }

    /**
     * Adjust CR points manually (Admin only - for corrections)
     */
    public function adjustCrPoints(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric',
            'type' => 'required|in:increment,decrement',
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $company = CompanyInfo::first();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company information not found'
            ], 404);
        }

        $amount = $request->amount;
        $previousCrPoints = $company->cr_points;

        // Adjust CR points based on type
        if ($request->type === 'increment') {
            $company->incrementCrPoint($amount);
            $message = "Added {$amount} CR points";
        } else {
            // Check if sufficient points for decrement
            if (!$company->hasSufficientCrPoints($amount)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient CR points for this operation',
                    'current_cr_point' => $company->cr_point
                ], 400);
            }
            
            $company->decrementCrPoint($amount);
            $message = "Deducted {$amount} CR points";
        }

        // Log the adjustment in transactions table (optional)
        // Transaction::create([...]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'previous_cr_point' => $previousCrPoints,
                'adjusted_amount' => $amount,
                'adjustment_type' => $request->type,
                'new_cr_point' => $company->fresh()->cr_points,
                'reason' => $request->reason
            ]
        ]);
    }

    /**
     * Get company statistics (Admin dashboard)
     */
    public function getStatistics()
    {
        $company = CompanyInfo::first();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company information not found'
            ], 404);
        }

        // You can expand this with more statistics
        $stats = [
            'company_info' => [
                'name' => $company->name,
                'cr_points' => $company->cr_points,
                'formatted_cr_point' => $company->formatted_cr_point,
            ],
            'system_stats' => [
                'total_members' => Member::count(),
                'active_members' => Member::where('status', 'active')->count(),
                'total_merchants' => Merchant::count(),
                'approved_merchants' => Merchant::where('status', 'approved')->count(),
                // 'total_transactions' => Transaction::count(),
            ],
            // Add more stats as needed
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}