<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Models\MemberWallet;
use App\Models\Denomination;
use App\Models\Setting;
use App\Helpers\CloudinaryHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Merchant;

class VoucherController extends Controller
{
    /**
     * Create a new voucher with manual payment
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */



    public function createVoucher(Request $request)
    {

       
        // Validate request
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|exists:members,id',
            'voucher_type' => 'required|in:max,refer',
            'denomination_history' => 'required|array|min:1',
            'denomination_history.*.denomination_id' => 'required|exists:denominations,id',
            'denomination_history.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:online,manual',
            'total_amount' => 'required|numeric|min:0',
            'manual_payment_docs' => 'required_if:payment_method,manual|file|mimes:jpeg,jpg,png,pdf|max:5120', // max 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Get rm_points from settings
            $setting = Setting::first();

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Settings not found'
                ], 500);
            }

            // Decode setting_attribute if it's a string
            $settingAttribute = is_string($setting->setting_attribute)
                ? json_decode($setting->setting_attribute, true)
                : $setting->setting_attribute;


              

            if (!isset($settingAttribute['maxreward']['rm_points'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'rm_points not configured in settings'
                ], 500);
            }

            $rmPoints = $settingAttribute['maxreward']['rm_points'];

            // Process denomination_history and build JSON structure
            $denominationHistoryData = [];
            $calculatedTotalAmount = 0;
            $totalQuantity = 0;

            foreach ($request->denomination_history as $item) {
                // Get denomination details from database
                $denomination = Denomination::find($item['denomination_id']);

                if (!$denomination) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Denomination not found with ID: ' . $item['denomination_id']
                    ], 404);
                }

                $quantity = $item['quantity'];
                $value = $denomination->value;
                $totalAmount = $value * $quantity;

                // Build the history item
                $denominationHistoryData[] = [
                    'denomination_id' => $item['denomination_id'],
                    'value' => $value,
                    'quantity' => $quantity,
                    'totalAmount' => $totalAmount,
                ];

                $calculatedTotalAmount += $totalAmount;
                $totalQuantity += $quantity;
            }

            // Verify total amount matches expected calculation
            if ($request->total_amount != $calculatedTotalAmount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total amount mismatch. Expected: ' . $calculatedTotalAmount . ', Received: ' . $request->total_amount
                ], 400);
            }

            // Calculate final total amount with rm_points
            $finalTotalAmount = $calculatedTotalAmount * $rmPoints;

            // Handle manual payment document upload to Cloudinary
            $manualPaymentDocsUrl = null;
            $manualPaymentDocsCloudinaryId = null;

            if ($request->payment_method === 'manual' && $request->hasFile('manual_payment_docs')) {
                $uploadResult = CloudinaryHelper::uploadImage(
                    $request->file('manual_payment_docs'),
                    'maxreward/vouchers/payment-docs'
                );
                $manualPaymentDocsUrl = $uploadResult['url'];
                $manualPaymentDocsCloudinaryId = $uploadResult['public_id'];
            }

            // Create voucher
            $voucher = Voucher::create([
                'member_id' => $request->member_id,
                'voucher_type' => $request->voucher_type,
                'denomination_history' => $denominationHistoryData,
                'quantity' => $totalQuantity,
                'payment_method' => $request->payment_method,
                'total_amount' => $finalTotalAmount,
                'manual_payment_docs_url' => $manualPaymentDocsUrl,
                'manual_payment_docs_cloudinary_id' => $manualPaymentDocsCloudinaryId,
                'status' => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Voucher created successfully',
                'data' => [
                    'voucher' => $voucher,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create voucher',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function createVoucherOld(Request $request)
    {

        // dd($request->all());

        // Validate request
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|exists:members,id',
            'voucher_type' => 'required|in:max,refer',
            'denomination_history' => 'required',
            'quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:online,manual',
            'total_amount' => 'required|numeric|min:0',
            'manual_payment_docs' => 'required_if:payment_method,manual|file|mimes:jpeg,jpg,png,pdf|max:5120', // max 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Get denomination to verify total amount
            $denomination = Denomination::find($request->denomination_id);

            
            
            if (!$denomination) {
                return response()->json([
                    'success' => false,
                    'message' => 'Denomination not found'
                ], 404);
            }

            // Get rm_points from settings
            $setting = Setting::first();

            

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Settings not found'
                ], 500);
            }

            // Decode setting_attribute if it's a string
            $settingAttribute = is_string($setting->setting_attribute)
                ? json_decode($setting->setting_attribute, true)
                : $setting->setting_attribute;

            
            
            if (!isset($settingAttribute['maxreward']['rm_points'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'rm_points not configured in settings'
                ], 500);
            }

            $rmPoints = $settingAttribute['maxreward']['rm_points'];

            $expectedAmount = $denomination->value * $request->quantity;

            // Verify total amount matches expected calculation
            if ($request->total_amount != $expectedAmount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total amount mismatch. Expected: ' . $expectedAmount
                ], 400);
            }

            $totalAmount = $expectedAmount * $rmPoints;


            // Handle manual payment document upload to Cloudinary
            $manualPaymentDocsUrl = null;
            $manualPaymentDocsCloudinaryId = null;

            if ($request->payment_method === 'manual' && $request->hasFile('manual_payment_docs')) {
                $uploadResult = CloudinaryHelper::uploadImage(
                    $request->file('manual_payment_docs'),
                    'maxreward/vouchers/payment-docs'
                );
                $manualPaymentDocsUrl = $uploadResult['url'];
                $manualPaymentDocsCloudinaryId = $uploadResult['public_id'];
            }


            // dd($totalAmount);

            // Create voucher
            $voucher = Voucher::create([
                'member_id' => $request->member_id,
                'voucher_type' => $request->voucher_type,
                'denomination_id' => $request->denomination_id,
                'quantity' => $request->quantity,
                'payment_method' => $request->payment_method,
                'total_amount' => $totalAmount,
                'manual_payment_docs_url' => $manualPaymentDocsUrl,
                'manual_payment_docs_cloudinary_id' => $manualPaymentDocsCloudinaryId,
                'status' => 'pending',
            ]);

            // // Get member wallet
            // $memberWallet = MemberWallet::where('member_id', $request->member_id)->first();

            // if (!$memberWallet) {
            //     DB::rollBack();
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Member wallet not found'
            //     ], 404);
            // }

            // // Update member wallet based on voucher type
            // if ($request->voucher_type === 'refer') {
            //     $memberWallet->total_rp += $totalAmount;
            //     $memberWallet->total_points += $totalAmount;
            // } elseif ($request->voucher_type === 'max') {
            //     $memberWallet->available_points += $totalAmount;
            //     $memberWallet->total_points += $totalAmount;
            // }

            // $memberWallet->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Voucher created successfully',
                'data' => [
                    'voucher' => $voucher->load('denomination'),
                    // 'wallet' => $memberWallet
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create voucher',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        $vouchers = Voucher::with('denomination')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'vouchers' => $vouchers
            ]
        ], 200);
    }




    public function getMemberVouchers(){
        try{
            $Auth = auth()->user();
            $member_id = '';
            if ($Auth->member_type == 'general' || $Auth->member_type == 'corporate') {
                $member_id = $Auth->id;
            } 
            if ($Auth->type == "merchant" || $Auth->type == "staff") {
                $merchant = Merchant::where('id', $Auth->merchant_id)->first();
                $member_id = $merchant->corporateMember->id;
            }
            $vouchers = Voucher::with('denomination')->where('member_id', $member_id)->paginate(20);
            if (count($vouchers) == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vouchers not found'
                ], 404);
            }
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


    public function getSingleVoucher(Request $request){
        try {
            $voucher = Voucher::with(['denomination', 'member', 'merchant'])->findOrFail($request->id);
            return response()->json([
                'success' => true,
                'message' => 'Voucher retrieved successfully',
                'data' => $voucher
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found'
            ], 404);
        }
    }


}
