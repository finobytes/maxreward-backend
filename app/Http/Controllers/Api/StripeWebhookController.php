<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Models\RechargeRequestInfo;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    /**
     * Handle Stripe webhook events
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleWebhook(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret_key'));
        
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            // Verify webhook signature
            if ($webhookSecret) {
                $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } else {
                $event = json_decode($payload, false);
            }

            // Handle different event types
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutSessionCompleted($event->data->object);
                    break;

                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event->data->object);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event->data->object);
                    break;

                case 'charge.succeeded':
                    $this->handleChargeSucceeded($event->data->object);
                    break;

                case 'charge.failed':
                    $this->handleChargeFailed($event->data->object);
                    break;

                default:
                    \Log::info('Unhandled webhook event: ' . $event->type);
            }

            return response()->json(['success' => true], 200);

        } catch (SignatureVerificationException $e) {
            \Log::error('Webhook signature verification failed: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            \Log::error('Webhook error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle checkout session completed event
     * 
     * @param object $session
     * @return void
     */
    protected function handleCheckoutSessionCompleted($session)
    {
        $voucherId = $session->metadata->voucher_id ?? null;
        
        if (!$voucherId) {
            \Log::warning('Checkout session completed but no voucher_id in metadata');
            return;
        }

        $voucher = Voucher::find($voucherId);

        if (!$voucher) {
            \Log::warning('Voucher not found: ' . $voucherId);
            return;
        }

        // Update voucher if payment is successful
        if ($session->payment_status === 'paid') {
            $voucher->update([
                'status' => 'approved', // Auto-approved for online payment
                'stripe_checkout_session_id' => $session->id,
                'stripe_payment_intent_id' => $session->payment_intent,
                'paid_at' => now(),
            ]);

            // Log success
            RechargeRequestInfo::logAfterRequest(
                $voucher->member_id,
                $voucher->merchant_id,
                [
                    'voucher_id' => $voucher->id,
                    'event' => 'checkout.session.completed',
                    'session_id' => $session->id,
                    'payment_status' => $session->payment_status,
                    'status' => 'approved',
                ]
            );

            \Log::info('Voucher approved via webhook: ' . $voucher->id);
        }
    }

    /**
     * Handle payment intent succeeded event
     * 
     * @param object $paymentIntent
     * @return void
     */
    protected function handlePaymentIntentSucceeded($paymentIntent)
    {
        $voucherId = $paymentIntent->metadata->voucher_id ?? null;

        if (!$voucherId) {
            return;
        }

        $voucher = Voucher::find($voucherId);

        if (!$voucher) {
            return;
        }

        // Get FPX transaction ID from charges
        $fpxTransactionId = null;
        if (isset($paymentIntent->charges->data[0])) {
            $fpxTransactionId = $paymentIntent->charges->data[0]->id;
        }

        $voucher->update([
            'stripe_payment_intent_id' => $paymentIntent->id,
            'fpx_transaction_id' => $fpxTransactionId,
            'status' => 'approved',
            'paid_at' => now(),
        ]);

        // Log success
        RechargeRequestInfo::logAfterRequest(
            $voucher->member_id,
            // $voucher->merchant_id,
            null,
            [
                'voucher_id' => $voucher->id,
                'event' => 'payment_intent.succeeded',
                'payment_intent_id' => $paymentIntent->id,
                'fpx_transaction_id' => $fpxTransactionId,
                'amount' => $paymentIntent->amount / 100,
                'status' => 'approved',
            ]
        );

        \Log::info('Payment intent succeeded: ' . $paymentIntent->id);
    }

    /**
     * Handle payment intent failed event
     * 
     * @param object $paymentIntent
     * @return void
     */
    protected function handlePaymentIntentFailed($paymentIntent)
    {
        $voucherId = $paymentIntent->metadata->voucher_id ?? null;

        if (!$voucherId) {
            return;
        }

        $voucher = Voucher::find($voucherId);

        if (!$voucher) {
            return;
        }

        $voucher->update([
            'stripe_payment_intent_id' => $paymentIntent->id,
            'status' => 'failed',
        ]);

        // Log failure
        RechargeRequestInfo::logAfterRequest(
            $voucher->member_id,
            // $voucher->merchant_id,
            null,
            [
                'voucher_id' => $voucher->id,
                'event' => 'payment_intent.payment_failed',
                'payment_intent_id' => $paymentIntent->id,
                'error' => $paymentIntent->last_payment_error->message ?? 'Unknown error',
                'status' => 'failed',
            ]
        );

        \Log::warning('Payment intent failed: ' . $paymentIntent->id);
    }

    /**
     * Handle charge succeeded event
     * 
     * @param object $charge
     * @return void
     */
    protected function handleChargeSucceeded($charge)
    {
        \Log::info('Charge succeeded: ' . $charge->id . ' - Amount: ' . ($charge->amount / 100) . ' MYR');
    }

    /**
     * Handle charge failed event
     * 
     * @param object $charge
     * @return void
     */
    protected function handleChargeFailed($charge)
    {
        \Log::warning('Charge failed: ' . $charge->id . ' - Reason: ' . ($charge->failure_message ?? 'Unknown'));
    }
}