<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\Transaction;
use App\Models\MemberWallet;
use App\Models\Voucher;
use App\Models\Purchase;
use Carbon\Carbon;
 
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
            $now = Carbon::now();

            // Get total members count
            $totalMembers = Member::count();

            // Get total active members count
            $totalActiveMembers = Member::where('status', 'active')->count();

            // Get new members joined in last 7 days
            $newMembersLast7Days = Member::where('created_at', '>=', $now->copy()->subDays(7))->count();

            // Get approved merchants count
            $approvedMerchants = Merchant::where('status', 'approved')->count();

            // Get pending merchants count
            $pendingMerchants = Merchant::where('status', 'pending')->count();

            // Get total transactions count
            $totalTransactions = Transaction::count();

            // Get total earned points (sum of available points in member wallets)
            $totalPointsEarned = (float) MemberWallet::sum('available_points');

            // Get total merchant approvals (merchants with 'approved' status)
            $totalPendingVouchers = Voucher::where('status', 'pending')->count();

            $voucherRange = request()->get('voucher_range', 'all');
            $redeemedRange = request()->get('redeemed_range', 'all');
            $pointsIssuedRange = request()->get('points_issued_redeemed_range', $voucherRange);
            $now = Carbon::now();

            $redeemedBaseQuery = Transaction::where('points_type', Transaction::POINTS_DEBITED);
            $purchaseBaseQuery = Purchase::where('status', 'approved');

            $redeemedStart = $this->getRangeStartDate($redeemedRange, $now);
            if ($redeemedStart) {
                $redeemedBaseQuery->whereBetween('created_at', [$redeemedStart, $now]);
                $purchaseBaseQuery->whereBetween('created_at', [$redeemedStart, $now]);
            }

            $newRegistrationRedeemed = (float) (clone $redeemedBaseQuery)
                ->where('transaction_points', 100)
                ->sum('transaction_points');
            $shoppingRedeemed = (float) $purchaseBaseQuery->sum('redeem_amount');
            $totalPointsRedeemed = $shoppingRedeemed + $newRegistrationRedeemed;

            $voucherBaseQuery = Voucher::where('status', 'success');
            $voucherStart = $this->getRangeStartDate($voucherRange, $now);
            if ($voucherStart) {
                $voucherBaseQuery->whereBetween('created_at', [$voucherStart, $now]);
            }
            $maxPurchased = (int) (clone $voucherBaseQuery)->where('voucher_type', 'max')->sum('quantity');
            $referPurchased = (int) (clone $voucherBaseQuery)->where('voucher_type', 'refer')->sum('quantity');
            $totalPurchased = $maxPurchased + $referPurchased;

            $pointsIssuedQuery = Voucher::where('status', 'success');
            $pointsIssuedStart = $this->getRangeStartDate($pointsIssuedRange, $now);
            if ($pointsIssuedStart) {
                $pointsIssuedQuery->whereBetween('created_at', [$pointsIssuedStart, $now]);
            }
            $pointsIssued = (float) $pointsIssuedQuery->sum('total_amount');
            $pointsLiability = $pointsIssued + $totalPointsRedeemed;

            return response()->json([
                'success' => true,
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => [
                    'total_members' => $totalMembers,
                    'total_active_members' => $totalActiveMembers,
                    'new_members_last_7_days' => $newMembersLast7Days,
                    'approved_merchants' => $approvedMerchants,
                    'total_transactions' => $totalTransactions,
                    'pending_merchants' => $pendingMerchants,
                    'total_points_earned' => $totalPointsEarned,
                    'total_pending_vouchers' => $totalPendingVouchers,
                    'points_redeemed_statistics' => [
                        'total_points_redeemed' => $totalPointsRedeemed,
                        'shopping_points_redeemed' => $shoppingRedeemed,
                        'new_registration_points_redeemed' => $newRegistrationRedeemed
                    ],
                    'points_issued_vs_redeemed' => [
                        'points_issued' => $pointsIssued,
                        'points_redeemed' => $totalPointsRedeemed,
                        'points_liability' => $pointsLiability
                    ],
                    'voucher_statistics' => [
                        'total_vouchers_purchased' => $totalPurchased,
                        'max_vouchers_purchased' => $maxPurchased,
                        'refer_vouchers_purchased' => $referPurchased
                    ]
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

    private function getRangeStartDate($range, Carbon $now)
    {
        switch ($range) {
            case 'week':
                return $now->copy()->subWeek()->startOfDay();
            case 'month':
                return $now->copy()->subMonth()->startOfDay();
            case 'year':
                return $now->copy()->subYear()->startOfDay();
            case 'all':
            default:
                return null;
        }
    }

    /**
     * Get voucher purchased statistics for admin dashboard
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVoucherPurchaseStats()
    {
        try {
            $baseQuery = Voucher::where('status', 'success');

            $maxPurchased = (int) (clone $baseQuery)->where('voucher_type', 'max')->sum('quantity');
            $referPurchased = (int) (clone $baseQuery)->where('voucher_type', 'refer')->sum('quantity');
            $totalPurchased = $maxPurchased + $referPurchased;

            return response()->json([
                'success' => true,
                'message' => 'Voucher purchase statistics retrieved successfully',
                'data' => [
                    'total_vouchers_purchased' => $totalPurchased,
                    'max_vouchers_purchased' => $maxPurchased,
                    'refer_vouchers_purchased' => $referPurchased
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve voucher purchase statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get last 12 months purchased vs redeemed amounts for real-time chart
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public function getRealTimeTransactions()
    {
        try {
            $now = Carbon::now();
            $start = $now->copy()->subMonths(11)->startOfMonth();

            $rows = Purchase::where('status', 'approved')
                ->whereBetween('created_at', [$start, $now->copy()->endOfMonth()])
                ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month')
                ->selectRaw('SUM(transaction_amount) as purchased_amount')
                ->selectRaw('SUM(redeem_amount) as redeemed_amount')
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->keyBy(function ($row) {
                    return $row->year . '-' . str_pad($row->month, 2, '0', STR_PAD_LEFT);
                });

            $labels = [];
            $purchased = [];
            $redeemed = [];

            for ($i = 0; $i < 12; $i++) {
                $date = $start->copy()->addMonths($i);
                $key = $date->format('Y-m');
                $labels[] = $date->format('M');

                $row = $rows->get($key);
                $purchased[] = $row ? (float) $row->purchased_amount : 0.0;
                $redeemed[] = $row ? (float) $row->redeemed_amount : 0.0;
            }

            return response()->json([
                'success' => true,
                'message' => 'Real-time transactions retrieved successfully',
                'data' => [
                    'labels' => $labels,
                    'purchased' => $purchased,
                    'redeemed' => $redeemed
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve real-time transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
 
}
