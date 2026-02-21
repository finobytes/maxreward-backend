<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Models\Denomination;
use App\Models\Setting;
use App\Models\Member;
use App\Models\RechargeRequestInfo;
use App\Helpers\CloudinaryHelper;
use App\Services\FPXPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Notification;
use App\Models\Merchant;

class VoucherController extends Controller
{
    protected $fpxService;

    public function __construct(FPXPaymentService $fpxService)
    {
        $this->fpxService = $fpxService;
    }

    /**
     * Create a new voucher (handles both online FPX and manual payment)
     * Only for Members - no merchant logic
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
            'manual_payment_docs' => 'required_if:payment_method,manual|file|mimes:jpeg,jpg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $memberId = $request->member_id;

            // Verify member exists
            $member = Member::find($memberId);
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member not found'
                ], 404);
            }

            // Handle ONLINE PAYMENT (FPX)
            if ($request->payment_method === 'online') {
                return $this->handleOnlinePayment($request, $memberId);
            }

            // Handle MANUAL PAYMENT
            if ($request->payment_method === 'manual') {
                return $this->handleManualPayment($request, $memberId);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create voucher',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Online Payment (FPX via Stripe)
     */
    private function handleOnlinePayment($request, $memberId)
    {
        DB::beginTransaction();

        try {
            // Load settings for rm_points
            $setting = Setting::first();
            if (!$setting) {
                throw new \Exception("Settings not found");
            }

            $settingAttribute = is_string($setting->setting_attribute)
                ? json_decode($setting->setting_attribute, true)
                : $setting->setting_attribute;

            if (!isset($settingAttribute['maxreward']['rm_points'])) {
                throw new \Exception("rm_points not configured in settings");
            }

            $rmPoints = $settingAttribute['maxreward']['rm_points'];

            // Process denomination_history and calculate total
            $denominationHistoryData = [];
            $calculatedTotalAmount = 0;
            $totalQuantity = 0;

            foreach ($request->denomination_history as $item) {
                $denomination = Denomination::find($item['denomination_id']);

                if (!$denomination) {
                    throw new \Exception('Denomination not found with ID: ' . $item['denomination_id']);
                }

                $quantity = $item['quantity'];
                $value = $denomination->value;
                $totalAmount = $value * $quantity;

                $denominationHistoryData[] = [
                    'denomination_id' => $item['denomination_id'],
                    'value' => $value,
                    'quantity' => $quantity,
                    'totalAmount' => $totalAmount,
                ];

                $calculatedTotalAmount += $totalAmount;
                $totalQuantity += $quantity;
            }

            // Verify total amount
            if ($request->total_amount != $calculatedTotalAmount) {
                throw new \Exception('Total amount mismatch. Expected: ' . $calculatedTotalAmount . ', Received: ' . $request->total_amount);
            }

            // Calculate final total with rm_points
            $finalTotalAmount = $calculatedTotalAmount * $rmPoints;

            // Create Stripe Checkout Session
            $result = $this->fpxService->createCheckoutSession(
                [
                    'voucher_type' => $request->voucher_type,
                    'denomination_history' => $denominationHistoryData,
                    'quantity' => $totalQuantity,
                    'total_amount' => $finalTotalAmount,
                    'fpx_total_payment' => $calculatedTotalAmount,
                ],
                'member',
                $memberId
            );

            if (!$result['success']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create payment session',
                    'error' => $result['error']
                ], 500);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment session created successfully. Redirect to checkout.',
                'data' => [
                    'checkout_url' => $result['checkout_url'],
                    'session_id' => $result['session_id'],
                    'voucher_id' => $result['voucher_id'],
                    'voucher_custom_id' => $result['voucher_custom_id'],
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create online payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Manual Payment
     */
    private function handleManualPayment($request, $memberId)
    {
        return DB::transaction(function () use ($request, $memberId) {
            
            // Load settings
            $setting = Setting::first();
            if (!$setting) {
                throw new \Exception("Settings not found");
            }

            $settingAttribute = is_string($setting->setting_attribute)
                ? json_decode($setting->setting_attribute, true)
                : $setting->setting_attribute;

            if (!isset($settingAttribute['maxreward']['rm_points'])) {
                throw new \Exception("rm_points not configured in settings");
            }

            $rmPoints = $settingAttribute['maxreward']['rm_points'];

            // Process denomination_history
            $denominationHistoryData = [];
            $calculatedTotalAmount = 0;
            $totalQuantity = 0;

            foreach ($request->denomination_history as $item) {
                $denomination = Denomination::find($item['denomination_id']);

                if (!$denomination) {
                    throw new \Exception('Denomination not found with ID: ' . $item['denomination_id']);
                }

                $quantity = $item['quantity'];
                $value = $denomination->value;
                $totalAmount = $value * $quantity;

                $denominationHistoryData[] = [
                    'denomination_id' => $item['denomination_id'],
                    'value' => $value,
                    'quantity' => $quantity,
                    'totalAmount' => $totalAmount,
                ];

                $calculatedTotalAmount += $totalAmount;
                $totalQuantity += $quantity;
            }

            // Verify total amount
            if ($request->total_amount != $calculatedTotalAmount) {
                throw new \Exception('Total amount mismatch. Expected: ' . $calculatedTotalAmount . ', Received: ' . $request->total_amount);
            }

            // Calculate final total with rm_points
            $finalTotalAmount = $calculatedTotalAmount * $rmPoints;

            // Handle manual payment document upload to Cloudinary
            $manualPaymentDocsUrl = null;
            $manualPaymentDocsCloudinaryId = null;

            if ($request->hasFile('manual_payment_docs')) {
                $uploadResult = CloudinaryHelper::uploadImage(
                    $request->file('manual_payment_docs'),
                    'maxreward/vouchers/payment-docs'
                );
                $manualPaymentDocsUrl = $uploadResult['url'];
                $manualPaymentDocsCloudinaryId = $uploadResult['public_id'];
            }

            // Generate voucher ID using the shared counter-based method
            // Same method used by FPXPaymentService for online payments
            $voucherId = $this->fpxService->generateVoucherId();

            // Create voucher - ONLY member_id, no merchant_id
            $voucher = Voucher::create([
                'voucher_id' => $voucherId,
                'member_id' => $memberId,
                'voucher_type' => $request->voucher_type,
                'denomination_history' => $denominationHistoryData,
                'quantity' => $totalQuantity,
                'payment_method' => 'manual',
                'total_amount' => $finalTotalAmount,
                'manual_payment_docs_url' => $manualPaymentDocsUrl,
                'manual_payment_docs_cloudinary_id' => $manualPaymentDocsCloudinaryId,
                'status' => 'pending', // Requires admin approval
            ]);

            // Create notification for voucher creation
            Notification::create([
                'member_id' => $request->member_id,
                'type' => 'voucher_created',
                'title' => 'Voucher Created',
                'message' => "Your voucher has been created successfully. Voucher ID: {$voucher->id}. Total Amount: {$finalTotalAmount} points. Status: Pending",
                'data' => [
                    'voucher_id' => $voucher->id,
                    'voucher_type' => $request->voucher_type,
                    'total_amount' => $finalTotalAmount,
                    'quantity' => $totalQuantity,
                    'payment_method' => $request->payment_method,
                    'created_at' => now()->toDateTimeString()
                ],
                'status' => 'unread',
                'is_read' => false
            ]);

            // Log request
            RechargeRequestInfo::logBeforeRequest(
                $memberId,
                null, // No merchant_id
                [
                    'voucher_id' => $voucher->id,
                    'voucher_custom_id' => $voucherId,
                    'amount' => $finalTotalAmount,
                    'voucher_type' => $request->voucher_type,
                    'payment_method' => 'manual',
                ]
            );

            RechargeRequestInfo::logAfterRequest(
                $memberId,
                null, // No merchant_id
                [
                    'voucher_id' => $voucher->id,
                    'status' => 'pending_approval',
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Voucher created successfully. Pending admin approval.',
                'data' => [
                    'voucher' => $voucher
                ]
            ], 201);
        });
    }

    /**
     * Verify payment after successful FPX checkout
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->fpxService->verifyPayment($request->session_id);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Payment verification failed'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['voucher']
        ], 200);
    }

    /**
     * Handle payment cancellation
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->fpxService->handleCancellation($request->session_id);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? 'Payment cancelled'
        ], 200);
    }

    /**
     * Get payment details by session ID
     *
     * @param string $sessionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentDetails($sessionId)
    {
        $result = $this->fpxService->getPaymentDetails($sessionId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment details',
                'error' => $result['error']
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $result['session']
        ], 200);
    }

    /**
     * Get voucher statistics for authenticated member
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVoucherStats(Request $request)
    {
        try {
            // Get authenticated member
            $auth = auth()->user();
            
            if (!$auth || !isset($auth->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $memberId = $auth->id;
            $stats = Voucher::getMemberVoucherStats($memberId);

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all vouchers (existing method - kept for compatibility)
     */
    public function index()
    {
        $vouchers = Voucher::with('member,denomination')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'vouchers' => $vouchers
            ]
        ], 200);
    }

    /**
     * Get member vouchers (existing method - kept for compatibility)
     * Now simplified - only for members
     */
    public function getMemberVouchers()
    {
        try {
            $Auth = auth()->user();
            
            $member_id = '';
            if ($Auth->member_type == 'general' || $Auth->member_type == 'corporate') {
                $member_id = $Auth->id;
            } 
            if ($Auth->type == "merchant" || $Auth->type == "staff") {
                $merchant = Merchant::where('id', $Auth->merchant_id)->first();
                $member_id = $merchant->corporateMember->id;
            }
            
            $vouchers = Voucher::with('denomination')->where('member_id', $member_id)
                ->orderBy('created_at', 'desc')
                ->paginate(20);
                
            if (count($vouchers) == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No vouchers found'
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

    /**
     * Get single voucher by ID (existing method - kept for compatibility)
     */
    public function getSingleVoucher(Request $request)
    {
        try {
            $voucher = Voucher::with(['denomination', 'member', 'merchant', 'rejectedBy'])->findOrFail($request->id);
            
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