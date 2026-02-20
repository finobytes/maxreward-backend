<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use App\Models\Voucher;
use App\Models\RechargeRequestInfo;
use Illuminate\Support\Str;

class FPXPaymentService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret_key'));
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
            // Generate unique voucher ID
            $voucherId = $this->generateVoucherId();

            // Create voucher record first (status: pending)
            // Only member_id, no merchant_id
            $voucher = Voucher::create([
                'voucher_id' => $voucherId,
                'member_id' => $memberId,
                'voucher_type' => $voucherData['voucher_type'],
                'denomination_history' => $voucherData['denomination_history'],
                'quantity' => $voucherData['quantity'],
                'payment_method' => 'online',
                'total_amount' => $voucherData['total_amount'],
                'status' => 'pending',
            ]);

            // Log before request
            RechargeRequestInfo::logBeforeRequest(
                $memberId,
                null, // No merchant_id
                [
                    'voucher_id' => $voucher->id,
                    'voucher_custom_id' => $voucherId,
                    'amount' => $voucherData['fpx_total_payment'],
                    'voucher_type' => $voucherData['voucher_type'],
                    'quantity' => $voucherData['quantity'],
                    'payment_method' => 'online',
                    'action' => 'create_checkout_session',
                ]
            );

            // Create Stripe Checkout Session
            $session = Session::create([
                'payment_method_types' => ['fpx'], // FPX payment method
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'myr', // Malaysian Ringgit
                        'product_data' => [
                            'name' => ucfirst($voucherData['voucher_type']) . ' Voucher',
                            'description' => "Quantity: {$voucherData['quantity']} vouchers",
                        ],
                        'unit_amount' => intval($voucherData['fpx_total_payment'] * 100), // Convert to cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => config('app.frontend_url') . '/voucher/payment/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.frontend_url') . '/voucher/payment/cancel?session_id={CHECKOUT_SESSION_ID}',
                'metadata' => [
                    'voucher_id' => $voucher->id,
                    'voucher_custom_id' => $voucherId,
                    'user_type' => 'member',
                    'member_id' => $memberId,
                    'voucher_type' => $voucherData['voucher_type'],
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'voucher_id' => $voucher->id,
                        'voucher_custom_id' => $voucherId,
                    ],
                ],
            ]);

            // Update voucher with checkout session ID
            $voucher->update([
                'stripe_checkout_session_id' => $session->id,
            ]);

            // Log after request
            RechargeRequestInfo::logAfterRequest(
                $memberId,
                null, // No merchant_id
                [
                    'voucher_id' => $voucher->id,
                    'checkout_session_id' => $session->id,
                    'checkout_url' => $session->url,
                    'status' => 'session_created',
                ]
            );

            return [
                'success' => true,
                'checkout_url' => $session->url,
                'session_id' => $session->id,
                'voucher_id' => $voucher->id,
                'voucher_custom_id' => $voucherId,
            ];

        } catch (\Exception $e) {
            // Log error
            RechargeRequestInfo::logAfterRequest(
                $memberId,
                null, // No merchant_id
                [
                    'error' => $e->getMessage(),
                    'status' => 'failed',
                    'action' => 'create_checkout_session',
                ]
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify payment after successful checkout
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
                return [
                    'success' => false,
                    'error' => 'Session not found',
                ];
            }

            // Get voucher
            $voucherId = $session->metadata->voucher_id ?? null;
            $voucher = Voucher::find($voucherId);

            if (!$voucher) {
                return [
                    'success' => false,
                    'error' => 'Voucher not found',
                ];
            }

            // Check payment status
            if ($session->payment_status === 'paid') {
                // Retrieve payment intent
                $paymentIntent = PaymentIntent::retrieve($session->payment_intent);

                // Update voucher status to approved (auto-approved for online payment)
                $voucher->update([
                    'status' => 'approved',
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'fpx_transaction_id' => $paymentIntent->charges->data[0]->id ?? null,
                    'paid_at' => now(),
                ]);

                // Log successful payment
                RechargeRequestInfo::logAfterRequest(
                    $voucher->member_id,
                    null, // No merchant_id
                    [
                        'voucher_id' => $voucher->id,
                        'session_id' => $sessionId,
                        'payment_intent_id' => $paymentIntent->id,
                        'status' => 'payment_successful',
                        'amount' => $voucher->total_amount,
                    ]
                );

                return [
                    'success' => true,
                    'voucher' => $voucher,
                    'message' => 'Payment successful',
                ];
            }

            return [
                'success' => false,
                'error' => 'Payment not completed',
                'payment_status' => $session->payment_status,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
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
     * Generate unique voucher ID
     * 
     * @return string
     */
    private function generateVoucherId()
    {
        do {
            $voucherId = 'VCH-' . strtoupper(Str::random(10));
        } while (Voucher::where('voucher_id', $voucherId)->exists());

        return $voucherId;
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