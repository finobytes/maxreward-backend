<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Models\MemberWallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VoucherController extends Controller
{
    /**
     * Get all vouchers with filters
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllVouchers(Request $request)
    {
        try {
            $query = Voucher::with(['member', 'denomination']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by voucher type
            if ($request->has('voucher_type')) {
                $query->where('voucher_type', $request->voucher_type);
            }

            // Filter by payment method
            if ($request->has('payment_method')) {
                $query->where('payment_method', $request->payment_method);
            }

            // Filter by member_id
            if ($request->has('member_id')) {
                $query->where('member_id', $request->member_id);
            }

            // Order by created_at descending (newest first)
            $query->orderBy('created_at', 'desc');

            // Paginate results
            $perPage = $request->get('per_page', 15);
            $vouchers = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'vouchers' => $vouchers
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve vouchers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a voucher and process wallet update & transaction
     *
     * @param Request $request
     * @param int $voucherId
     * @return \Illuminate\Http\JsonResponse
     */
    public function approveVoucher(Request $request, $voucherId)
    {
        try {
            DB::beginTransaction();

            // Find the voucher
            $voucher = Voucher::find($voucherId);

            if (!$voucher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher not found'
                ], 404);
            }

            // Check if voucher is pending
            if ($voucher->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending vouchers can be approved. Current status: ' . $voucher->status
                ], 400);
            }

            // Get member wallet
            $memberWallet = MemberWallet::where('member_id', $voucher->member_id)->first();

            if (!$memberWallet) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Member wallet not found'
                ], 404);
            }

            $totalAmount = $voucher->total_amount;

            // Update member wallet based on voucher type (logic from lines 135-155)
            if ($voucher->voucher_type === 'refer') {
                $memberWallet->total_rp += $totalAmount;
                $memberWallet->total_points += $totalAmount;

                // Create transaction record for referral points
                Transaction::create([
                    'member_id' => $voucher->member_id,
                    'merchant_id' => null,
                    'referral_member_id' => null,
                    'transaction_points' => $totalAmount,
                    'transaction_type' => Transaction::TYPE_VRP, // vrp = voucher referral points
                    'points_type' => Transaction::POINTS_CREDITED,
                    'transaction_reason' => 'Voucher approved - Referral Points',
                    'brp' => $memberWallet->total_rp // brp = balance referral points
                ]);

            } elseif ($voucher->voucher_type === 'max') {
                $memberWallet->available_points += $totalAmount;
                $memberWallet->total_points += $totalAmount;

                // Create transaction record for available points
                Transaction::create([
                    'member_id' => $voucher->member_id,
                    'merchant_id' => null,
                    'referral_member_id' => null,
                    'transaction_points' => $totalAmount,
                    'transaction_type' => Transaction::TYPE_VAP, // vap = voucher available points
                    'points_type' => Transaction::POINTS_CREDITED,
                    'transaction_reason' => 'Voucher approved - Available Points',
                    'bap' => $memberWallet->available_points // bap = balance available points
                ]);
            }

            $memberWallet->save();

            // Update voucher status to success
            $voucher->status = 'success';
            $voucher->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Voucher approved successfully',
                'data' => [
                    'voucher' => $voucher->load('denomination', 'member'),
                    'wallet' => $memberWallet,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve voucher',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function approveVoucherOld(Request $request, $voucherId)
    {
        try {
            DB::beginTransaction();

            // Find the voucher
            $voucher = Voucher::find($voucherId);

            if (!$voucher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher not found'
                ], 404);
            }

            // Check if voucher is pending
            if ($voucher->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending vouchers can be approved. Current status: ' . $voucher->status
                ], 400);
            }

            // Get member wallet
            $memberWallet = MemberWallet::where('member_id', $voucher->member_id)->first();

            if (!$memberWallet) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Member wallet not found'
                ], 404);
            }

            $totalAmount = $voucher->total_amount;

            // Update member wallet based on voucher type (logic from lines 135-155)
            if ($voucher->voucher_type === 'refer') {
                $memberWallet->total_rp += $totalAmount;
                $memberWallet->total_points += $totalAmount;

                // Create transaction record for referral points
                Transaction::create([
                    'member_id' => $voucher->member_id,
                    'merchant_id' => null,
                    'referral_member_id' => null,
                    'transaction_points' => $totalAmount,
                    'transaction_type' => Transaction::TYPE_VRP, // vrp = voucher referral points
                    'points_type' => Transaction::POINTS_CREDITED,
                    'transaction_reason' => 'Voucher approved - Referral Points',
                    'brp' => $memberWallet->total_rp
                ]);

            } elseif ($voucher->voucher_type === 'max') {
                $memberWallet->available_points += $totalAmount;
                $memberWallet->total_points += $totalAmount;

                // Create transaction record for available points
                Transaction::create([
                    'member_id' => $voucher->member_id,
                    'merchant_id' => null,
                    'referral_member_id' => null,
                    'transaction_points' => $totalAmount,
                    'transaction_type' => Transaction::TYPE_VAP, // vap = voucher available points
                    'points_type' => Transaction::POINTS_CREDITED,
                    'transaction_reason' => 'Voucher approved - Available Points',
                    'bap' => $memberWallet->available_points
                ]);
            }

            $memberWallet->save();

            // Update voucher status to success
            $voucher->status = 'success';
            $voucher->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Voucher approved successfully',
                'data' => [
                    'voucher' => $voucher->load('denomination', 'member'),
                    'wallet' => $memberWallet,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve voucher',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function rejectVoucher(Request $request, $voucherId) {
        try {
            // Find the voucher
            $voucher = Voucher::findOrFail($voucherId);

            if (!$voucher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher not found'
                ], 404);
            }

            // Check if voucher is pending
            if ($voucher->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending vouchers can be rejected. Current status: ' . $voucher->status
                ], 400);
            }

            // Update voucher status to failed
            $voucher->status = $request->status;
            $voucher->rejected_reason = $request->reason;
            $voucher->save();

            return response()->json([
                'success' => true,
                'message' => "Voucher status {$request->status} successfully",
                'data' => [
                    'voucher' => $voucher->load('denomination', 'member'),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Failed to {$request->status} voucher",
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getVoucher($voucherId) {
        try {
            $voucher = Voucher::with(['denomination', 'member'])->findOrFail($voucherId);
            return response()->json([
                'success' => true,
                'message' => 'Voucher retrieved successfully',
                'data' => $voucher
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve voucher',
                'error' => $e->getMessage()
            ], 500);
        }
    }

   
}
