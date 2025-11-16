<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\Transaction;
use App\Models\MemberWallet;
use App\Models\Voucher;
 
class DashboardController extends Controller
{
        /**
     * Get member dashboard statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardStats()
    {
        try {
            // Get total members count
            $totalMembers = Member::count();

            // Get approved merchants count
            $approvedMerchants = Merchant::where('status', 'approved')->count();

            // Get pending merchants count
            $pendingMerchants = Merchant::where('status', 'pending')->count();

            // Get total transactions count
            $totalTransactions = Transaction::count();

            // Get total merchant approvals (merchants with 'approved' status)
            $totalPendingVouchers = Voucher::where('status', 'pending')->count();

            return response()->json([
                'success' => true,
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => [
                    'total_members' => $totalMembers,
                    'approved_merchants' => $approvedMerchants,
                    'total_transactions' => $totalTransactions,
                    'pending_merchants' => $pendingMerchants,
                    'total_pending_vouchers' => $totalPendingVouchers
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
