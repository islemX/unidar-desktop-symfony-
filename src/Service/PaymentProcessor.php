<?php

namespace App\Service;

use App\Entity\Contract;
use App\Entity\Payment;
use App\Entity\PaymentTransaction;
use App\Entity\PlatformCommission;
use App\Enum\ContractStatus;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use Doctrine\ORM\EntityManagerInterface;

class PaymentProcessor
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommissionCalculator $commissionCalculator,
        private FakePaymentGateway $gateway,
    ) {
    }

    /**
     * Process a payment for a contract.
     */
    public function processPayment(
        Contract $contract,
        string $method,
        string $paymentType = 'monthly_rent',
    ): Payment {
        $amount = (float) $contract->getMonthlyRent();
        $amounts = $this->calculateAmounts($amount);

        // Call the fake gateway
        $gatewayResponse = $this->gateway->processPayment($amount, $method);

        // Create Payment entity
        $payment = new Payment();
        $payment->setContract($contract);
        $payment->setAmount((string) $amount);
        $payment->setOwnerAmount($amounts['ownerAmount']);
        $payment->setPlatformFee($amounts['platformFee']);
        $payment->setPaymentMethod($method);
        $payment->setPaymentType(PaymentType::from($paymentType));
        $payment->setTransactionId($gatewayResponse['transactionId']);

        // Create PaymentTransaction holding the full gateway response payload
        $transaction = new PaymentTransaction();
        $transaction->setPayment($payment);
        $transaction->setTransactionData(array_merge($gatewayResponse, [
            'method' => $method,
            'amount' => (string) $amount,
        ]));

        // Create PlatformCommission record
        $commission = new PlatformCommission();
        $commission->setPayment($payment);
        $commission->setContract($contract);
        $commission->setAmount($amounts['platformFee']);
        $commission->setCalculatedOn((string) $amount);

        if ($gatewayResponse['success']) {
            $payment->setStatus(PaymentStatus::Completed);
            $contract->setStatus(ContractStatus::Active);
        } else {
            $payment->setStatus(PaymentStatus::Failed);
        }

        $this->entityManager->persist($payment);
        $this->entityManager->persist($transaction);
        $this->entityManager->persist($commission);
        $this->entityManager->flush();

        return $payment;
    }

    /**
     * Calculate the owner amount and platform fee for a given amount.
     *
     * @return array{ownerAmount: string, platformFee: string}
     */
    public function calculateAmounts(float $amount): array
    {
        return $this->commissionCalculator->calculate((string) $amount);
    }

    /**
     * Get the status details of a payment.
     *
     * @return array{id: ?int, status: string, amount: ?string, method: ?string, date: ?string}
     */
    public function getPaymentStatus(Payment $payment): array
    {
        return [
            'id' => $payment->getId(),
            'status' => $payment->getStatus()->value,
            'amount' => $payment->getAmount(),
            'method' => $payment->getPaymentMethod(),
            'date' => $payment->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
