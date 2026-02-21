<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use App\Models\Voucher;
use App\Models\RechargeRequestInfo;
use Illuminate\Support\Str;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Member;
use App\Models\MemberWallet;
use App\Models\Transaction;

class FPXPaymentService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret_key'));
    }

    /**
     * Generate unique voucher ID using voucher_counters table.
     * AUTO_INCREMENT guarantees uniqueness even under high concurrency.
     * Starts from VID-2001.
     *
     * @return string
     */
    public function generateVoucherId(): string
    {
        $counterId = DB::table('voucher_counters')->insertGetId([
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Start from 2001
        $voucherNumber = $counterId + 2000;

        return 'VID-' . str_pad($voucherNumber, 4, '0', STR_PAD_LEFT);
    }



    /**
     * Create Stripe Checkout Session for FPX Payment
     * Only for Members
     * 
     * @param array $voucherData
     * @param string $userType (always 'member')
     * @param int $memberId
     * @return array
     */

    public function createCheckoutSession(array $voucherData, string $userType, int $memberId)
    {
        try {
            // STEP 1: Generate voucher ID
            // voucher_counters এ row insert হয় — Stripe fail হলে gap হবে, কিন্তু এটা acceptable
            $voucherId = $this->generateVoucherId();
    
            Log::info("Creating Stripe checkout session for voucher: {$voucherId}");
    
            // STEP 2: Stripe API call FIRST — DB transaction এর বাইরে
            // কারণ: Stripe external service, DB rollback দিয়ে এটাকে undo করা যায় না
            // Stripe fail করলে DB তে কিছুই লেখা হবে না
            $session = Session::create([
                'payment_method_types' => ['fpx'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'myr',
                        'product_data' => [
                            'name' => ucfirst($voucherData['voucher_type']) . ' Voucher',
                            'description' => "Quantity: {$voucherData['quantity']} vouchers",
                        ],
                        'unit_amount' => intval($voucherData['fpx_total_payment'] * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => config('app.frontend_url') . '/voucher/payment/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => config('app.frontend_url') . '/voucher/payment/cancel?session_id={CHECKOUT_SESSION_ID}',
                'metadata' => [
                    'voucher_custom_id' => $voucherId,
                    'user_type'         => 'member',
                    'member_id'         => $memberId,
                    'voucher_type'      => $voucherData['voucher_type'],
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'voucher_custom_id' => $voucherId,
                    ],
                ],
            ]);
    
            Log::info("Stripe session created: {$session->id}");
    
            // STEP 3: Stripe সফল — এখন DB transaction
            // সব DB write এক atomic block এ — যেকোনো একটা fail করলে সব rollback
            $voucher = DB::transaction(function () use ($voucherData, $memberId, $voucherId, $session) {
    
                // stripe_checkout_session_id সহ একসাথে create — আলাদা update() দরকার নেই
                $voucher = Voucher::create([
                    'voucher_id'                 => $voucherId,
                    'member_id'                  => $memberId,
                    'voucher_type'               => $voucherData['voucher_type'],
                    'denomination_history'       => $voucherData['denomination_history'],
                    'quantity'                   => $voucherData['quantity'],
                    'payment_method'             => 'online',
                    'total_amount'               => $voucherData['total_amount'],
                    'stripe_checkout_session_id' => $session->id, // ✅ একসাথেই save
                    'status'                     => 'pending',
                ]);
    
                Notification::create([
                    'member_id' => $memberId,
                    'type'      => 'voucher_created',
                    'title'     => 'Voucher Created',
                    'message'   => "Your voucher has been created successfully. Voucher ID: {$voucherId}. Total Amount: {$voucherData['total_amount']} points. Status: Pending",
                    'data'      => [
                        'voucher_id'     => $voucherId,
                        'voucher_type'   => $voucherData['voucher_type'],
                        'total_amount'   => $voucherData['total_amount'],
                        'quantity'       => $voucherData['quantity'],
                        'payment_method' => 'online',
                        'created_at'     => now()->toDateTimeString(),
                    ],
                    'status'  => 'unread',
                    'is_read' => false,
                ]);
    
                RechargeRequestInfo::logBeforeRequest(
                    $memberId,
                    null,
                    [
                        'voucher_id'        => $voucher->id,
                        'voucher_custom_id' => $voucherId,
                        'amount'            => $voucherData['fpx_total_payment'],
                        'voucher_type'      => $voucherData['voucher_type'],
                        'quantity'          => $voucherData['quantity'],
                        'payment_method'    => 'online',
                        'action'            => 'create_checkout_session',
                    ]
                );
    
                RechargeRequestInfo::logAfterRequest(
                    $memberId,
                    null,
                    [
                        'voucher_id'          => $voucher->id,
                        'checkout_session_id' => $session->id,
                        'checkout_url'        => $session->url,
                        'status'              => 'session_created',
                    ]
                );
    
                return $voucher;
            });
    
            return [
                'success'           => true,
                'checkout_url'      => $session->url,
                'session_id'        => $session->id,
                'voucher_id'        => $voucher->id,
                'voucher_custom_id' => $voucherId,
            ];
    
        } catch (\Exception $e) {
            Log::error("createCheckoutSession failed: " . $e->getMessage());
    
            RechargeRequestInfo::logAfterRequest(
                $memberId,
                null,
                [
                    'error'  => $e->getMessage(),
                    'status' => 'failed',
                    'action' => 'create_checkout_session',
                ]
            );
    
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    
    // public function createCheckoutSession_Old(array $voucherData, string $userType, int $memberId)
    // {
    //     try {
    //         // Generate unique voucher ID via counter table
    //         $voucherId = $this->generateVoucherId();

    //         Log::info("Start creating voucher");
    //         // Create voucher record first (status: pending)
    //         // Only member_id, no merchant_id
    //         $voucher = Voucher::create([
    //             'voucher_id' => $voucherId,
    //             'member_id' => $memberId,
    //             'voucher_type' => $voucherData['voucher_type'],
    //             'denomination_history' => $voucherData['denomination_history'],
    //             'quantity' => $voucherData['quantity'],
    //             'payment_method' => 'online',
    //             'total_amount' => $voucherData['total_amount'],
    //             'status' => 'pending',
    //         ]);

    //         Log::info("Voucher creation notification");

    //         Notification::create([
    //             'member_id' => $memberId,
    //             'type' => 'voucher_created',
    //             'title' => 'Voucher Created',
    //             'message' => "Your voucher has been created successfully. Voucher ID: {$voucherId}. Total Amount: {$voucherData['total_amount']} points. Status: Pending",
    //             'data' => [
    //                 'voucher_id' => $voucherId,
    //                 'voucher_type' => $voucherData['voucher_type'],
    //                 'total_amount' => $voucherData['total_amount'],
    //                 'quantity' => $voucherData['quantity'],
    //                 'payment_method' => 'online',
    //                 'created_at' => now()->toDateTimeString()
    //             ],
    //             'status'  => 'unread',
    //             'is_read' => false,
    //         ]);

    //         Log::info("Log before request");

    //         // Log before request
    //         RechargeRequestInfo::logBeforeRequest(
    //             $memberId,
    //             null, // No merchant_id
    //             [
    //                 'voucher_id' => $voucher->id,
    //                 'voucher_custom_id' => $voucherId,
    //                 'amount' => $voucherData['fpx_total_payment'],
    //                 'voucher_type' => $voucherData['voucher_type'],
    //                 'quantity' => $voucherData['quantity'],
    //                 'payment_method' => 'online',
    //                 'action' => 'create_checkout_session',
    //             ]
    //         );

    //         Log::info("Create checkout session");

    //         // Create Stripe Checkout Session
    //         $session = Session::create([
    //             'payment_method_types' => ['fpx'], // FPX payment method
    //             'line_items' => [[
    //                 'price_data' => [
    //                     'currency' => 'myr', // Malaysian Ringgit
    //                     'product_data' => [
    //                         'name' => ucfirst($voucherData['voucher_type']) . ' Voucher',
    //                         'description' => "Quantity: {$voucherData['quantity']} vouchers",
    //                     ],
    //                     'unit_amount' => intval($voucherData['fpx_total_payment'] * 100), // Convert to cents
    //                 ],
    //                 'quantity' => 1,
    //             ]],
    //             'mode' => 'payment',
    //             'success_url' => config('app.frontend_url') . '/voucher/payment/success?session_id={CHECKOUT_SESSION_ID}',
    //             'cancel_url' => config('app.frontend_url') . '/voucher/payment/cancel?session_id={CHECKOUT_SESSION_ID}',
    //             'metadata' => [
    //                 'voucher_id' => $voucher->id,
    //                 'voucher_custom_id' => $voucherId,
    //                 'user_type' => 'member',
    //                 'member_id' => $memberId,
    //                 'voucher_type' => $voucherData['voucher_type'],
    //             ],
    //             'payment_intent_data' => [
    //                 'metadata' => [
    //                     'voucher_id' => $voucher->id,
    //                     'voucher_custom_id' => $voucherId,
    //                 ],
    //             ],
    //         ]);

    //         Log::info("Update voucher with checkout session ID");

    //         // Update voucher with checkout session ID
    //         $voucher->update([
    //             'stripe_checkout_session_id' => $session->id,
    //         ]);

    //         Log::info("Log after request session_created");

    //         // Log after request
    //         RechargeRequestInfo::logAfterRequest(
    //             $memberId,
    //             null, // No merchant_id
    //             [
    //                 'voucher_id' => $voucher->id,
    //                 'checkout_session_id' => $session->id,
    //                 'checkout_url' => $session->url,
    //                 'status' => 'session_created',
    //             ]
    //         );

    //         return [
    //             'success' => true,
    //             'checkout_url' => $session->url,
    //             'session_id' => $session->id,
    //             'voucher_id' => $voucher->id,
    //             'voucher_custom_id' => $voucherId,
    //         ];

    //     } catch (\Exception $e) {
    //         // Log error
    //         RechargeRequestInfo::logAfterRequest(
    //             $memberId,
    //             null, // No merchant_id
    //             [
    //                 'error' => $e->getMessage(),
    //                 'status' => 'failed',
    //                 'action' => 'create_checkout_session',
    //             ]
    //         );

    //         return [
    //             'success' => false,
    //             'error' => $e->getMessage(),
    //         ];
    //     }
    // }

    /**
     * Verify payment after successful checkout.
     * On success, updates member wallet and creates a transaction record
     * using the same logic as approveVoucher() (manual payment flow).
     *
     * @param string $sessionId
     * @return array
     */
    public function verifyPayment(string $sessionId)
    {
        try {
            // Retrieve checkout session from Stripe
            $session = Session::retrieve($sessionId);

            if (!$session) {
                return ['success' => false, 'error' => 'Session not found'];
            }
            
            // $voucherId = $session->metadata->voucher_id ?? null;
            $voucherId = $session->metadata->voucher_custom_id ?? null;
            $voucher   = Voucher::where('voucher_id', $voucherId)->first();

            if (!$voucher) {
                return ['success' => false, 'error' => 'Voucher not found'];
            }

            // ✅ Already processed — দ্বিতীয় call আসলে এখানেই শেষ
            if ($voucher->status === 'approved') {
                return [
                    'success' => true,
                    'voucher' => $voucher,
                    'message' => 'Payment already verified',
                ];
            }

            // শুধু pending voucher এর জন্যই process করবো
            if ($voucher->status !== 'pending') {
                return [
                    'success' => false,
                    'error'   => 'Voucher cannot be verified. Current status: ' . $voucher->status,
                ];
            }

            if ($session->payment_status === 'paid') {

                DB::transaction(function () use ($session, $sessionId, $voucher) {

                    $paymentIntent = PaymentIntent::retrieve($session->payment_intent);

                    Log::info("Update voucher status to approved");

                    // Update voucher status (auto-approved for online payment)
                    $voucher->update([
                        'status'                   => 'approved',
                        'stripe_payment_intent_id' => $paymentIntent->id,
                        'fpx_transaction_id'       => $paymentIntent->charges->data[0]->id ?? null,
                        'paid_at'                  => now(),
                    ]);

                    // Get member wallet
                    $memberWallet = MemberWallet::where('member_id', $voucher->member_id)->first();

                    if (!$memberWallet) {
                        throw new \Exception('Member wallet not found for member_id: ' . $voucher->member_id);
                    }

                    $totalAmount = $voucher->total_amount;

                    Log::info("Update member wallet for voucher_type: {$voucher->voucher_type}");

                    // Update wallet and create transaction — same logic as approveVoucher()
                    if ($voucher->voucher_type === 'refer') {
                        $memberWallet->total_rp      += $totalAmount;
                        $memberWallet->total_points  += $totalAmount;

                        Transaction::create([
                            'member_id'           => $voucher->member_id,
                            'merchant_id'         => null,
                            'referral_member_id'  => null,
                            'transaction_points'  => $totalAmount,
                            'transaction_type'    => Transaction::TYPE_VRP, // vrp = voucher referral points
                            'points_type'         => Transaction::POINTS_CREDITED,
                            'transaction_reason'  => 'Voucher approved - Referral Points',
                            'brp'                 => $memberWallet->total_rp,        // balance referral points
                            'bap'                 => $memberWallet->available_points, // balance available points
                            'bop'                 => $memberWallet->onhold_points,   // balance onhold points
                            'is_referral_history' => 1,
                        ]);

                    } elseif ($voucher->voucher_type === 'max') {
                        $memberWallet->available_points += $totalAmount;
                        $memberWallet->total_points     += $totalAmount;

                        Transaction::create([
                            'member_id'          => $voucher->member_id,
                            'merchant_id'        => null,
                            'referral_member_id' => null,
                            'transaction_points' => $totalAmount,
                            'transaction_type'   => Transaction::TYPE_VAP, // vap = voucher available points
                            'points_type'        => Transaction::POINTS_CREDITED,
                            'transaction_reason' => 'Voucher approved - Available Points',
                            'bap'                => $memberWallet->available_points, // balance available points
                            'bop'                => $memberWallet->onhold_points,   // balance onhold points
                            'brp'                => $memberWallet->total_rp,        // balance referral points
                        ]);
                    }

                    $memberWallet->save();

                    Log::info("Create notification for voucher approved");

                    Notification::create([
                        'member_id' => $voucher->member_id,
                        'type'      => 'voucher_approved',
                        'title'     => 'Voucher Approved',
                        'message'   => "Your voucher has been approved successfully. Voucher ID: {$voucher->voucher_id}. Total Amount: {$totalAmount} points. Status: Approved",
                        'data'      => [
                            'voucher_id'     => $voucher->voucher_id,
                            'voucher_type'   => $voucher->voucher_type,
                            'total_amount'   => $totalAmount,
                            'quantity'       => $voucher->quantity,
                            'payment_method' => $voucher->payment_method,
                            'approved_at'    => now()->toDateTimeString(),
                        ],
                        'status'  => 'unread',
                        'is_read' => false,
                    ]);

                    Log::info("Log after request payment_successful");

                    RechargeRequestInfo::logAfterRequest(
                        $voucher->member_id,
                        null,
                        [
                            'voucher_id'        => $voucher->id,
                            'session_id'        => $sessionId,
                            'payment_intent_id' => $paymentIntent->id,
                            'status'            => 'payment_successful',
                            'amount'            => $totalAmount,
                        ]
                    );
                });

                return [
                    'success' => true,
                    'voucher' => $voucher->fresh(), // reload after update
                    'message' => 'Payment successful',
                ];
            }

            return [
                'success'        => false,
                'error'          => 'Payment not completed',
                'payment_status' => $session->payment_status,
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Handle payment cancellation
     * 
     * @param string $sessionId
     * @return array
     */
    public function handleCancellation(string $sessionId)
    {
        try {
            $session = Session::retrieve($sessionId);
            $voucherId = $session->metadata->voucher_id ?? null;
            
            if ($voucherId) {
                $voucher = Voucher::find($voucherId);
                if ($voucher && $voucher->status === 'pending') {
                    $voucher->update(['status' => 'failed']);

                    // Log cancellation
                    RechargeRequestInfo::logAfterRequest(
                        $voucher->member_id,
                        null, // No merchant_id
                        [
                            'voucher_id' => $voucher->id,
                            'session_id' => $sessionId,
                            'status' => 'payment_cancelled',
                        ]
                    );
                }
            }

            return [
                'success' => true,
                'message' => 'Payment cancelled',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get payment details
     * 
     * @param string $sessionId
     * @return array
     */
    public function getPaymentDetails(string $sessionId)
    {
        try {
            $session = Session::retrieve($sessionId);
            
            return [
                'success' => true,
                'session' => [
                    'id' => $session->id,
                    'payment_status' => $session->payment_status,
                    'amount_total' => $session->amount_total / 100, // Convert from cents
                    'currency' => $session->currency,
                    'customer_email' => $session->customer_details->email ?? null,
                    'payment_intent' => $session->payment_intent,
                ],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}