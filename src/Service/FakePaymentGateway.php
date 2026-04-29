<?php

namespace App\Service;

class FakePaymentGateway
{
    /**
     * Simulate processing a payment with a 95% success rate.
     *
     * @return array{success: bool, transactionId: ?string, message: string}
     */
    public function processPayment(float $amount, string $method): array
    {
        $success = random_int(1, 100) <= 95;

        if ($success) {
            $transactionId = 'TND-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));

            return [
                'success' => true,
                'transactionId' => $transactionId,
                'message' => 'Payment processed successfully.',
            ];
        }

        return [
            'success' => false,
            'transactionId' => null,
            'message' => 'Payment declined. Please try again.',
        ];
    }

    /**
     * Simulate refunding a payment. Always succeeds.
     *
     * @return array{success: bool, message: string}
     */
    public function refundPayment(string $transactionId): array
    {
        return [
            'success' => true,
            'message' => 'Refund processed successfully for transaction ' . $transactionId . '.',
        ];
    }
}
