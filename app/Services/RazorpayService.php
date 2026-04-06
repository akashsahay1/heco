<?php

namespace App\Services;

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayService
{
    protected Api $api;

    public function __construct()
    {
        $this->api = new Api(
            config('services.razorpay.key_id'),
            config('services.razorpay.key_secret')
        );
    }

    /**
     * Create a Razorpay order.
     */
    public function createOrder(int $amountInPaise, string $currency = 'INR', string $receipt = null, array $notes = []): array
    {
        $order = $this->api->order->create([
            'amount'   => $amountInPaise,
            'currency' => $currency,
            'receipt'  => $receipt ?: 'rcpt_' . time(),
            'notes'    => $notes,
        ]);

        return $order->toArray();
    }

    /**
     * Verify payment signature from Razorpay checkout.
     */
    public function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        try {
            $this->api->utility->verifyPaymentSignature([
                'razorpay_order_id'   => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature'  => $signature,
            ]);
            return true;
        } catch (SignatureVerificationError $e) {
            return false;
        }
    }

    /**
     * Fetch payment details from Razorpay.
     */
    public function fetchPayment(string $paymentId): array
    {
        return $this->api->payment->fetch($paymentId)->toArray();
    }
}
